<?php

namespace App\Roads\Services;

use App\Roads\DTOs\RoadIncident;
use App\Support\CircuitBreaker;
use App\Support\CircuitOpenException;
use App\Support\CoordinateMapper;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class NationalHighwaysService
{
    public function __construct(
        protected ?CircuitBreaker $circuitBreaker = null
    ) {
        $this->circuitBreaker ??= new CircuitBreaker('national_highways');
    }

    /**
     * Get road and lane closure incidents for South West routes (M5, A38, A30, A303, A361, A372, etc.).
     *
     * @return array<int, array{road?: string, status?: string, incidentType?: string, delayTime?: string}>
     */
    public function getIncidents(): array
    {
        $apiKey = config('flood-watch.national_highways.api_key');

        if (empty($apiKey)) {
            return [];
        }

        try {
            return $this->circuitBreaker->execute(fn () => $this->fetchIncidents($apiKey));
        } catch (CircuitOpenException) {
            return [];
        } catch (ConnectionException|RequestException $e) {
            report($e);

            return [];
        }
    }

    /**
     * @return array<int, array{road?: string, status?: string, incidentType?: string, delayTime?: string}>
     */
    private function fetchIncidents(string $apiKey): array
    {
        $baseUrl = rtrim(config('flood-watch.national_highways.base_url'), '/');
        $closuresPath = ltrim(config('flood-watch.national_highways.closures_path', 'closures'), '/');
        $timeout = config('flood-watch.national_highways.timeout');
        $retryTimes = config('flood-watch.national_highways.retry_times', 3);
        $retrySleep = config('flood-watch.national_highways.retry_sleep_ms', 100);
        $headers = [
            'Ocp-Apim-Subscription-Key' => $apiKey,
            'X-Response-MediaType' => 'application/json',
        ];

        $incidents = [];

        $planned = $this->fetchClosures($baseUrl, $closuresPath, 'planned', $timeout, $retryTimes, $retrySleep, $headers);
        $incidents = array_merge($incidents, $this->parseDatexPayload($planned));

        if (config('flood-watch.national_highways.fetch_unplanned', true)) {
            $unplanned = $this->fetchClosures($baseUrl, $closuresPath, 'unplanned', $timeout, $retryTimes, $retrySleep, $headers);
            $incidents = array_merge($incidents, $this->parseDatexPayload($unplanned));
        }

        return $incidents;
    }

    /**
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    private function fetchClosures(string $baseUrl, string $path, string $closureType, int $timeout, int $retryTimes, int $retrySleep, array $headers): array
    {
        $url = "{$baseUrl}/{$path}?closureType={$closureType}";

        $response = Http::timeout($timeout)
            ->retry($retryTimes, $retrySleep, null, false)
            ->withHeaders($headers)
            ->get($url);

        if ($response->status() === 404) {
            return [];
        }

        if (! $response->successful()) {
            $response->throw();
        }

        return $response->json() ?? [];
    }

    /**
     * Parse DATEX II v3.4 D2Payload structure.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, array{road?: string, status?: string, incidentType?: string, delayTime?: string}>
     */
    private function parseDatexPayload(array $data): array
    {
        $incidents = [];

        $payload = $data['D2Payload'] ?? $data;
        $situations = $payload['situation'] ?? [];

        foreach ((array) $situations as $situation) {
            if (! is_array($situation)) {
                continue;
            }

            $records = $situation['situationRecord'] ?? [];
            foreach ((array) $records as $record) {
                if (! is_array($record)) {
                    continue;
                }

                $sit = $record['sitRoadOrCarriagewayOrLaneManagement'] ?? null;
                if (! is_array($sit)) {
                    continue;
                }

                $flat = $this->extractIncidentFromDatexRecord($sit);
                if ($flat['road'] !== '' || $flat['status'] !== '' || $flat['incidentType'] !== '') {
                    $incidents[] = RoadIncident::fromArray($flat)->toArray();
                }
            }
        }

        return $incidents;
    }

    /**
     * Extract flat incident data from a sitRoadOrCarriagewayOrLaneManagement record.
     *
     * @param  array<string, mixed>  $sit
     * @return array{road: string, status: string, incidentType: string, delayTime: string, lat?: float, lng?: float, startTime?: string, endTime?: string, locationDescription?: string, managementType?: string, isFloodRelated?: bool}
     */
    private function extractIncidentFromDatexRecord(array $sit): array
    {
        $locationRef = $sit['locationReference'] ?? [];
        $road = $this->extractRoadName($locationRef);
        $status = $sit['validity']['validityStatus'] ?? '';
        $incidentType = $this->extractIncidentType($sit);
        $delayTime = $this->extractComment($sit['generalPublicComment'] ?? []);
        $coords = $this->extractCoordinates($locationRef) ?? $this->fallbackCoordinatesForRoad($road);

        $validitySpec = $sit['validity']['validityTimeSpecification'] ?? [];
        $startTime = $validitySpec['overallStartTime'] ?? null;
        $endTime = $validitySpec['overallEndTime'] ?? null;

        $locationDescription = $this->extractLocationDescription($locationRef);
        $managementType = $sit['roadOrCarriagewayOrLaneManagementType']['value'] ?? null;
        $isFloodRelated = $this->isFloodRelatedIncident($sit, $incidentType);

        $flat = [
            'road' => is_string($road) ? $road : '',
            'status' => is_string($status) ? $status : '',
            'incidentType' => is_string($incidentType) ? $incidentType : '',
            'delayTime' => is_string($delayTime) ? $delayTime : '',
        ];
        if ($coords !== null) {
            $mapped = CoordinateMapper::fromPointArray($coords);
            $flat['lat'] = $mapped['lat'];
            $flat['lng'] = $mapped['lng'];
        }
        if (is_string($startTime) && $startTime !== '') {
            $flat['startTime'] = $startTime;
        }
        if (is_string($endTime) && $endTime !== '') {
            $flat['endTime'] = $endTime;
        }
        if (is_string($locationDescription) && $locationDescription !== '') {
            $flat['locationDescription'] = $locationDescription;
        }
        if (is_string($managementType) && $managementType !== '') {
            $flat['managementType'] = $managementType;
        }
        if ($isFloodRelated) {
            $flat['isFloodRelated'] = true;
        }

        return $flat;
    }

    /**
     * @param  array<string, mixed>  $locationRef
     */
    private function extractLocationDescription(array $locationRef): string
    {
        $linearLoc = $locationRef['locLinearLocation'] ?? [];
        $desc = $linearLoc['supplementaryPositionalDescription']['locationDescription'] ?? null;
        if (is_string($desc) && $desc !== '') {
            return $desc;
        }

        $groups = $locationRef['locLocationGroupByList']['locationContainedInGroup'] ?? [];
        foreach ((array) $groups as $group) {
            if (! is_array($group)) {
                continue;
            }
            $linearLoc = $group['locLinearLocation'] ?? [];
            $desc = $linearLoc['supplementaryPositionalDescription']['locationDescription'] ?? null;
            if (is_string($desc) && $desc !== '') {
                return $desc;
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $sit
     */
    private function isFloodRelatedIncident(array $sit, string $incidentType): bool
    {
        if (str_contains(strtolower($incidentType), 'flooding')) {
            return true;
        }

        $detailed = $sit['cause']['detailedCauseType'] ?? [];
        if (is_array($detailed)) {
            $envType = $detailed['environmentalObstructionType'] ?? null;
            if (is_string($envType) && strtolower($envType) === 'flooding') {
                return true;
            }
            if (is_array($envType) && isset($envType[0]) && strtolower((string) $envType[0]) === 'flooding') {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract a representative point (lat, long) from locationReference for map display.
     * Uses pointCoordinates when available, otherwise midpoint of posList.
     *
     * @param  array<string, mixed>  $locationRef
     * @return array{0: float, 1: float}|null
     */
    private function extractCoordinates(array $locationRef): ?array
    {
        $point = $locationRef['locPointLocation']['pointByCoordinates']['pointCoordinates'] ?? null;
        if (is_array($point) && isset($point['latitude'], $point['longitude'])) {
            return [(float) $point['latitude'], (float) $point['longitude']];
        }

        $posList = $locationRef['locLinearLocation']['gmlLineString']['locGmlLineString']['posList'] ?? null;
        if (is_string($posList) && $posList !== '') {
            return $this->posListToPoint($posList);
        }

        $groups = $locationRef['locLocationGroupByList']['locationContainedInGroup'] ?? [];
        foreach ((array) $groups as $group) {
            if (! is_array($group)) {
                continue;
            }
            $posList = $group['locLinearLocation']['gmlLineString']['locGmlLineString']['posList'] ?? null;
            if (is_string($posList) && $posList !== '') {
                return $this->posListToPoint($posList);
            }
        }

        return null;
    }

    /**
     * Parse GML posList "lat long lat long ..." to first point [lat, long].
     *
     * @return array{0: float, 1: float}|null
     */
    private function posListToPoint(string $posList): ?array
    {
        $parts = preg_split('/\s+/', trim($posList), -1, PREG_SPLIT_NO_EMPTY);
        if ($parts === false || count($parts) < 2) {
            return null;
        }
        $coords = array_map('floatval', $parts);

        return [$coords[0], $coords[1]];
    }

    /**
     * Fallback coordinates when API does not return geometry. Uses config for known South West roads.
     *
     * @return array{0: float, 1: float}|null
     */
    private function fallbackCoordinatesForRoad(string $road): ?array
    {
        $baseRoad = $this->extractBaseRoad($road);
        if ($baseRoad === '') {
            return null;
        }
        $lookup = config('flood-watch.incident_road_coordinates', []);
        $coords = $lookup[$baseRoad] ?? null;
        if (is_array($coords) && count($coords) >= 2) {
            return [(float) $coords[0], (float) $coords[1]];
        }

        return null;
    }

    private function extractBaseRoad(string $roadOrKeyRoute): string
    {
        if (preg_match('/^([AM]\d+[A-Z]?)/', trim($roadOrKeyRoute), $m)) {
            return $m[1];
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $locationRef
     */
    private function extractRoadName(array $locationRef): string
    {
        $road = $this->extractRoadFromLinearElements($locationRef['locLocationGroupByList']['locationContainedInGroup'] ?? []);
        if ($road !== '') {
            return $road;
        }

        $road = $this->extractRoadFromSingleLocation($locationRef['locLinearLocation'] ?? [], $locationRef['locSingleRoadLinearLocation'] ?? []);
        if ($road !== '') {
            return $road;
        }

        return $this->extractRoadFromPointLocation($locationRef['locPointLocation'] ?? []);
    }

    /**
     * @param  array<int, array<string, mixed>>  $groups
     */
    private function extractRoadFromLinearElements(array $groups): string
    {
        foreach ($groups as $group) {
            if (! is_array($group)) {
                continue;
            }
            $road = $this->extractRoadFromLinearElement($group['locSingleRoadLinearLocation'] ?? []);
            if ($road !== '') {
                return $road;
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $linearLoc
     * @param  array<string, mixed>  $singleRoad
     */
    private function extractRoadFromSingleLocation(array $linearLoc, array $singleRoad): string
    {
        $road = $this->extractRoadFromLinearElement($singleRoad);
        if ($road !== '') {
            return $road;
        }

        $desc = $linearLoc['supplementaryPositionalDescription']['locationDescription'] ?? '';
        if (is_string($desc) && $desc !== '') {
            if (preg_match('/\b([AM]\d+[A-Z]?)\b/', $desc, $m)) {
                return $m[1];
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $pointLoc
     */
    private function extractRoadFromPointLocation(array $pointLoc): string
    {
        $elements = $pointLoc['pointAlongLinearElement'] ?? [];
        foreach ((array) $elements as $el) {
            if (! is_array($el)) {
                continue;
            }
            $byCode = $el['linearElement']['locLinearElementByCode'] ?? [];
            $road = $byCode['roadName'] ?? '';
            if (is_string($road) && $road !== '') {
                return $road;
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $singleRoad
     */
    private function extractRoadFromLinearElement(array $singleRoad): string
    {
        $within = $singleRoad['linearWithinLinearElement'] ?? [];
        foreach ((array) $within as $w) {
            if (! is_array($w)) {
                continue;
            }
            $el = $w['linearElement']['locLinearElementByCode'] ?? [];
            $road = $el['roadName'] ?? '';
            if (is_string($road) && $road !== '') {
                return $road;
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $sit
     */
    private function extractIncidentType(array $sit): string
    {
        $detailed = $sit['cause']['detailedCauseType'] ?? [];
        if (is_array($detailed)) {
            foreach (['environmentalObstructionType', 'vehicleObstructionType', 'roadMaintenanceType'] as $key) {
                $val = $detailed[$key] ?? null;
                if (is_string($val) && $val !== '') {
                    return $val;
                }
                if (is_array($val) && isset($val[0])) {
                    return is_string($val[0]) ? $val[0] : '';
                }
            }
            $mgmt = $detailed['roadOrCarriagewayOrLaneManagementType']['value'] ?? null;
            if (is_string($mgmt) && $mgmt !== '') {
                return $mgmt;
            }
        }

        $cause = $sit['cause']['causeType'] ?? '';
        if (is_string($cause) && $cause !== '') {
            return $cause;
        }

        $type = $sit['roadOrCarriagewayOrLaneManagementType']['value'] ?? '';
        if (is_string($type) && $type !== '') {
            return $type;
        }

        return '';
    }

    /**
     * @param  array<int, array<string, mixed>>  $comments
     */
    private function extractComment(array $comments): string
    {
        $first = $comments[0]['comment'] ?? null;

        return is_string($first) ? $first : '';
    }
}
