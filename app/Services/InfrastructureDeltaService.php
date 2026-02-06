<?php

namespace App\Services;

use App\Events\InfrastructureStatusChanged;
use App\Models\SystemActivity;
use Illuminate\Support\Facades\Event;

class InfrastructureDeltaService
{
    /**
     * Compare previous and current infrastructure state, create SystemActivity records for changes.
     *
     * @param  array{floods: array, incidents: array, riverLevels: array}  $previous
     * @param  array{floods: array, incidents: array, riverLevels: array}  $current
     * @return array<int, SystemActivity>
     */
    public function compareAndCreateActivities(array $previous, array $current): array
    {
        $activities = [];

        $activities = array_merge(
            $activities,
            $this->detectFloodChanges($previous['floods'] ?? [], $current['floods'] ?? []),
            $this->detectIncidentChanges($previous['incidents'] ?? [], $current['incidents'] ?? []),
            $this->detectRiverLevelChanges($previous['riverLevels'] ?? [], $current['riverLevels'] ?? [])
        );

        foreach ($activities as $activity) {
            Event::dispatch(new InfrastructureStatusChanged($activity));
        }

        return $activities;
    }

    /**
     * @param  array<int, array{floodAreaID?: string, description?: string, severity?: string, severityLevel?: int}>  $previous
     * @param  array<int, array{floodAreaID?: string, description?: string, severity?: string, severityLevel?: int}>  $current
     * @return array<int, SystemActivity>
     */
    private function detectFloodChanges(array $previous, array $current): array
    {
        $activities = [];
        $prevIds = $this->floodKeys($previous);
        $currIds = $this->floodKeys($current);

        foreach ($currIds as $id => $flood) {
            if (! isset($prevIds[$id])) {
                $activities[] = SystemActivity::create([
                    'type' => 'flood_warning',
                    'description' => 'New flood warning: '.($flood['description'] ?? 'Unknown area'),
                    'severity' => $this->severityFromFloodLevel($flood['severityLevel'] ?? 4),
                    'occurred_at' => now(),
                    'metadata' => ['floodAreaID' => $id],
                ]);
            }
        }

        foreach ($prevIds as $id => $flood) {
            if (! isset($currIds[$id])) {
                $activities[] = SystemActivity::create([
                    'type' => 'flood_warning',
                    'description' => 'Flood warning lifted: '.($flood['description'] ?? 'Unknown area'),
                    'severity' => 'low',
                    'occurred_at' => now(),
                    'metadata' => ['floodAreaID' => $id],
                ]);
            }
        }

        return $activities;
    }

    /**
     * @param  array<int, array{road?: string, status?: string}>  $previous
     * @param  array<int, array{road?: string, status?: string}>  $current
     * @return array<int, SystemActivity>
     */
    private function detectIncidentChanges(array $previous, array $current): array
    {
        $activities = [];
        $prevKeys = $this->incidentKeys($previous);
        $currKeys = $this->incidentKeys($current);

        foreach ($currKeys as $key => $incident) {
            if (! isset($prevKeys[$key])) {
                $road = $incident['road'] ?? 'Road';
                $activities[] = SystemActivity::create([
                    'type' => 'road_closure',
                    'description' => "🛣️ {$road} closed",
                    'severity' => 'moderate',
                    'occurred_at' => now(),
                    'metadata' => ['road' => $road],
                ]);
            }
        }

        foreach ($prevKeys as $key => $incident) {
            if (! isset($currKeys[$key])) {
                $road = $incident['road'] ?? 'Road';
                $activities[] = SystemActivity::create([
                    'type' => 'road_reopened',
                    'description' => "🛣️ {$road} reopened",
                    'severity' => 'low',
                    'occurred_at' => now(),
                    'metadata' => ['road' => $road],
                ]);
            }
        }

        return $activities;
    }

    /**
     * @param  array<int, array{station?: string, river?: string, levelStatus?: string}>  $previous
     * @param  array<int, array{station?: string, river?: string, levelStatus?: string}>  $current
     * @return array<int, SystemActivity>
     */
    private function detectRiverLevelChanges(array $previous, array $current): array
    {
        $activities = [];
        $prevByKey = $this->riverLevelKeys($previous);
        $currByKey = $this->riverLevelKeys($current);

        foreach ($currByKey as $key => $level) {
            if (($level['levelStatus'] ?? '') === 'elevated') {
                $prev = $prevByKey[$key] ?? null;
                if ($prev === null || ($prev['levelStatus'] ?? '') !== 'elevated') {
                    $station = $level['station'] ?? $level['river'] ?? 'Station';
                    $activities[] = SystemActivity::create([
                        'type' => 'river_level_elevated',
                        'description' => "💧 {$station} level elevated",
                        'severity' => 'moderate',
                        'occurred_at' => now(),
                        'metadata' => ['station' => $station],
                    ]);
                }
            }
        }

        return $activities;
    }

    /**
     * @param  array<int, array{floodAreaID?: string}>  $floods
     * @return array<string, array>
     */
    private function floodKeys(array $floods): array
    {
        $out = [];
        foreach ($floods as $f) {
            $id = $f['floodAreaID'] ?? $f['description'] ?? null;
            if ($id !== null && $id !== '') {
                $out[(string) $id] = $f;
            }
        }

        return $out;
    }

    /**
     * @param  array<int, array{road?: string, status?: string}>  $incidents
     * @return array<string, array>
     */
    private function incidentKeys(array $incidents): array
    {
        $out = [];
        foreach ($incidents as $i) {
            $road = trim((string) ($i['road'] ?? ''));
            $status = $i['status'] ?? '';
            if ($road !== '') {
                $key = $road.'|'.$status;
                $out[$key] = $i;
            }
        }

        return $out;
    }

    /**
     * @param  array<int, array{station?: string, river?: string}>  $levels
     * @return array<string, array>
     */
    private function riverLevelKeys(array $levels): array
    {
        $out = [];
        foreach ($levels as $r) {
            $key = ($r['station'] ?? '').'|'.($r['river'] ?? '');
            if ($key !== '|') {
                $out[$key] = $r;
            }
        }

        return $out;
    }

    private function severityFromFloodLevel(int $level): string
    {
        return match ($level) {
            1 => 'severe',
            2 => 'high',
            3 => 'moderate',
            default => 'low',
        };
    }
}
