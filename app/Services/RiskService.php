<?php

namespace App\Services;

use App\Flood\Services\EnvironmentAgencyFloodService;
use App\Flood\Services\FloodForecastService;
use App\Flood\Services\RiverLevelService;
use App\Roads\Services\NationalHighwaysService;
use Illuminate\Support\Facades\Concurrency;

class RiskService
{
    public function __construct(
        protected EnvironmentAgencyFloodService $floodService,
        protected NationalHighwaysService $highwaysService,
        protected RiverLevelService $riverLevelService,
        protected FloodForecastService $forecastService
    ) {}

    /**
     * Calculate the regional risk index (0–100) for the South West.
     *
     * @return array{index: int, label: string, summary: string, rawScore: float}
     */
    public function calculate(): array
    {
        $lat = config('flood-watch.default_lat');
        $long = config('flood-watch.default_long');
        $radiusKm = config('flood-watch.default_radius_km', 15);

        [$floods, $incidents, $riverLevels, $forecast] = Concurrency::run([
            fn () => $this->floodService->getFloods($lat, $long, $radiusKm),
            fn () => $this->highwaysService->getIncidents(),
            fn () => $this->riverLevelService->getLevels($lat, $long, $radiusKm),
            fn () => $this->forecastService->getForecast(),
        ]);

        $incidents = $this->filterIncidentsToSouthWest($incidents);

        $floodScore = $this->floodScore($floods);
        $incidentScore = $this->incidentScore($incidents);
        $riverScore = $this->riverScore($riverLevels);
        $forecastScore = $this->forecastScore($forecast);

        $rawScore = $floodScore + $incidentScore + $riverScore + $forecastScore;
        $maxRaw = 142;
        $index = (int) min(100, round($rawScore * (100 / $maxRaw)));
        $label = $this->labelForIndex($index);
        $summary = $this->buildSummary($floods, $incidents, $index);

        return [
            'index' => $index,
            'label' => $label,
            'summary' => $summary,
            'rawScore' => $rawScore,
        ];
    }

    /**
     * @param  array<int, array{severityLevel?: int}>  $floods
     */
    private function floodScore(array $floods): float
    {
        $severe = 0;
        $warning = 0;
        $alert = 0;
        foreach ($floods as $f) {
            $level = (int) ($f['severityLevel'] ?? 4);
            if ($level === 1) {
                $severe++;
            } elseif ($level === 2) {
                $warning++;
            } elseif ($level === 3) {
                $alert++;
            }
        }

        $wSevere = (int) config('flood-watch.risk_weight_severe', 25);
        $wWarning = (int) config('flood-watch.risk_weight_warning', 12);
        $wAlert = (int) config('flood-watch.risk_weight_alert', 5);

        return min($severe * $wSevere, 50) + min($warning * $wWarning, 36) + min($alert * $wAlert, 15);
    }

    /**
     * @param  array<int, array{isFloodRelated?: bool, road?: string, managementType?: string}>  $incidents
     */
    private function incidentScore(array $incidents): float
    {
        $floodRelated = 0;
        $aRoad = 0;
        $other = 0;
        foreach ($incidents as $i) {
            if ($i['isFloodRelated'] ?? false) {
                $floodRelated++;
            } elseif (preg_match('/^A\d+/', trim($i['road'] ?? ''))) {
                $aRoad++;
            } else {
                $other++;
            }
        }

        $wFlood = (int) config('flood-watch.risk_weight_flood_closure', 10);
        $wARoad = (int) config('flood-watch.risk_weight_a_road', 5);
        $wOther = 3;

        return min($floodRelated * $wFlood, 30) + min($aRoad * $wARoad, 25) + min($other * $wOther, 15);
    }

    /**
     * @param  array<int, array{levelStatus?: string}>  $riverLevels
     */
    private function riverScore(array $riverLevels): float
    {
        $elevated = 0;
        $expected = 0;
        foreach ($riverLevels as $r) {
            $status = $r['levelStatus'] ?? '';
            if ($status === 'elevated') {
                $elevated++;
            } elseif ($status === 'expected') {
                $expected++;
            }
        }

        $wElevated = (int) config('flood-watch.risk_weight_elevated', 3);

        return min($elevated * $wElevated, 15) + min($expected * 1, 5);
    }

    /**
     * @param  array{flood_risk_trend?: array}  $forecast
     */
    private function forecastScore(array $forecast): float
    {
        $trend = $forecast['flood_risk_trend'] ?? [];
        if (! is_array($trend)) {
            return 0;
        }
        $hasIncreasing = false;
        $allDecreasing = true;
        foreach ($trend as $day) {
            $t = is_array($day) ? ($day['trend'] ?? $day) : $day;
            if ($t === 'increasing') {
                $hasIncreasing = true;
                $allDecreasing = false;
            } elseif ($t !== 'decreasing') {
                $allDecreasing = false;
            }
        }

        return $hasIncreasing ? 2 : ($allDecreasing && count($trend) > 0 ? -1 : 0);
    }

    private function labelForIndex(int $index): string
    {
        return match (true) {
            $index <= 20 => 'Low',
            $index <= 40 => 'Moderate',
            $index <= 60 => 'High',
            default => 'Severe',
        };
    }

    /**
     * Filter incidents to only those on South West monitored routes (A30, A303, A361, A372, A38, M4, M5).
     *
     * @param  array<int, array{road?: string}>  $incidents
     * @return array<int, array{road?: string}>
     */
    private function filterIncidentsToSouthWest(array $incidents): array
    {
        $allowed = config('flood-watch.incident_allowed_roads', []);
        if (empty($allowed)) {
            return $incidents;
        }

        return array_values(array_filter($incidents, function (array $incident) use ($allowed): bool {
            $road = trim((string) ($incident['road'] ?? ''));
            if ($road === '') {
                return false;
            }
            $baseRoad = $this->extractBaseRoad($road);

            return in_array($baseRoad, $allowed, true);
        }));
    }

    private function extractBaseRoad(string $roadOrKeyRoute): string
    {
        if (preg_match('/^([AM]\d+[A-Z]?)/', trim($roadOrKeyRoute), $m)) {
            return $m[1];
        }

        return '';
    }

    /**
     * @param  array<int, array{severity?: string, severityLevel?: int}>  $floods
     * @param  array<int, array>  $incidents
     */
    private function buildSummary(array $floods, array $incidents, int $index): string
    {
        $parts = [];
        $severe = count(array_filter($floods, fn ($f) => ($f['severityLevel'] ?? 4) === 1));
        $warnings = count(array_filter($floods, fn ($f) => ($f['severityLevel'] ?? 4) === 2));
        if ($severe > 0) {
            $parts[] = $severe.' severe '.($severe === 1 ? 'warning' : 'warnings');
        }
        if ($warnings > 0 && $severe === 0) {
            $parts[] = $warnings.' flood '.($warnings === 1 ? 'warning' : 'warnings');
        }
        if (count($incidents) > 0) {
            $parts[] = count($incidents).' road '.(count($incidents) === 1 ? 'closure' : 'closures');
        }
        if (empty($parts)) {
            return $index <= 20 ? 'No active alerts.' : 'Monitor conditions.';
        }

        return implode(', ', $parts);
    }
}
