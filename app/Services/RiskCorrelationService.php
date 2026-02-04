<?php

namespace App\Services;

use App\DTOs\RiskAssessment;
use App\Flood\Enums\SeverityLevel;

final class RiskCorrelationService
{
    /**
     * Correlate flood warnings, road incidents, and river levels into a deterministic assessment.
     *
     * @param  array<int, array<string, mixed>>  $floods
     * @param  array<int, array<string, mixed>>  $incidents
     * @param  array<int, array{station?: string, river?: string, levelStatus?: string}>  $riverLevels
     */
    public function correlate(
        array $floods,
        array $incidents,
        array $riverLevels,
        ?string $region = null
    ): RiskAssessment {
        $severeFloods = [];
        $floodWarnings = [];
        foreach ($floods as $flood) {
            $level = $flood['severityLevel'] ?? 4;
            if ($level === SeverityLevel::Severe->value) {
                $severeFloods[] = $flood;
            } elseif ($level <= SeverityLevel::Alert->value) {
                $floodWarnings[] = $flood;
            }
        }

        $crossReferences = $this->computeCrossReferences($floods, $incidents, $region);
        $predictiveWarnings = array_merge(
            $this->computeRiverPredictiveWarnings($riverLevels, $region),
            $this->computeFloodPredictiveWarnings($floods, $region)
        );
        $keyRoutes = $this->getKeyRoutes($region);

        return new RiskAssessment(
            severeFloods: $severeFloods,
            floodWarnings: $floodWarnings,
            roadIncidents: $incidents,
            crossReferences: $crossReferences,
            predictiveWarnings: $predictiveWarnings,
            keyRoutes: $keyRoutes,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $floods
     * @param  array<int, array<string, mixed>>  $incidents
     * @return array<int, array{floodArea: string, road: string, hasIncident: bool, note?: string}>
     */
    private function computeCrossReferences(array $floods, array $incidents, ?string $region): array
    {
        $pairs = $this->getFloodAreaRoadPairs($region);
        if (empty($pairs)) {
            return [];
        }

        $incidentRoads = $this->normaliseIncidentRoads($incidents);
        $result = [];

        foreach ($pairs as [$floodAreaPattern, $roadPattern]) {
            foreach ($floods as $flood) {
                $description = (string) ($flood['description'] ?? '');
                if (! $this->matchesPattern($description, $floodAreaPattern)) {
                    continue;
                }
                $hasIncident = $this->roadHasIncident($roadPattern, $incidentRoads);
                $result[] = [
                    'floodArea' => $description,
                    'road' => $roadPattern,
                    'hasIncident' => $hasIncident,
                    'note' => $hasIncident ? 'Road incident reported' : 'No incident on road yet',
                ];
            }
        }

        return $result;
    }

    /**
     * @param  array<int, array{station?: string, river?: string, levelStatus?: string}>  $riverLevels
     * @return array<int, array{type: string, message: string, reason: string}>
     */
    private function computeRiverPredictiveWarnings(array $riverLevels, ?string $region): array
    {
        $rules = $this->getPredictiveRules($region);
        $warnings = [];

        foreach ($rules as $rule) {
            if (isset($rule['flood_pattern'])) {
                continue;
            }
            $riverPattern = strtolower((string) ($rule['river_pattern'] ?? ''));
            $triggerLevel = $rule['trigger_level'] ?? 'elevated';
            $message = $rule['warning'] ?? '';

            foreach ($riverLevels as $level) {
                $river = strtolower((string) ($level['river'] ?? ''));
                $status = strtolower((string) ($level['levelStatus'] ?? ''));
                if (str_contains($river, $riverPattern) && $status === $triggerLevel) {
                    $warnings[] = [
                        'type' => 'predictive',
                        'message' => $message,
                        'reason' => "River {$river} level is {$status}",
                    ];
                    break;
                }
            }
        }

        return $warnings;
    }

    /**
     * @param  array<int, array<string, mixed>>  $floods
     * @return array<int, array{type: string, message: string, reason: string}>
     */
    private function computeFloodPredictiveWarnings(array $floods, ?string $region): array
    {
        $rules = $this->getPredictiveRules($region);
        $warnings = [];

        foreach ($rules as $rule) {
            $floodPattern = strtolower((string) ($rule['flood_pattern'] ?? ''));
            if ($floodPattern === '') {
                continue;
            }
            $triggerSeverityMax = (int) ($rule['trigger_severity_max'] ?? 4);
            $message = $rule['warning'] ?? '';

            foreach ($floods as $flood) {
                $description = strtolower((string) ($flood['description'] ?? ''));
                $severityLevel = (int) ($flood['severityLevel'] ?? 4);
                if (str_contains($description, $floodPattern) && $severityLevel <= $triggerSeverityMax) {
                    $warnings[] = [
                        'type' => 'predictive',
                        'message' => $message,
                        'reason' => "Flood warning for {$description} (severity {$severityLevel})",
                    ];
                    break;
                }
            }
        }

        return $warnings;
    }

    /**
     * @return array<int, array{string, string}>
     */
    private function getFloodAreaRoadPairs(?string $region): array
    {
        $config = $region ? config("flood-watch.correlation.{$region}", []) : [];
        $pairs = $config['flood_area_road_pairs'] ?? [];

        return array_map(fn ($p) => [(string) $p[0], (string) $p[1]], $pairs);
    }

    /**
     * @return array<int, array{river_pattern: string, trigger_level: string, warning: string}>
     */
    private function getPredictiveRules(?string $region): array
    {
        $config = $region ? config("flood-watch.correlation.{$region}", []) : [];

        return $config['predictive_rules'] ?? [];
    }

    /**
     * @return array<int, string>
     */
    private function getKeyRoutes(?string $region): array
    {
        $config = $region ? config("flood-watch.correlation.{$region}", []) : [];
        $routes = $config['key_routes'] ?? [];

        return array_values(array_map('strval', $routes));
    }

    /**
     * @param  array<int, array<string, mixed>>  $incidents
     * @return array<int, string>
     */
    private function normaliseIncidentRoads(array $incidents): array
    {
        $roads = [];
        foreach ($incidents as $incident) {
            $road = (string) ($incident['road'] ?? '');
            if ($road !== '') {
                $roads[] = strtolower($road);
            }
        }

        return $roads;
    }

    private function matchesPattern(string $haystack, string $pattern): bool
    {
        return str_contains(strtolower($haystack), strtolower($pattern));
    }

    private function roadHasIncident(string $roadPattern, array $incidentRoads): bool
    {
        $pattern = strtolower($roadPattern);
        foreach ($incidentRoads as $road) {
            if (str_contains($road, $pattern) || str_contains($pattern, $road)) {
                return true;
            }
        }

        return false;
    }
}
