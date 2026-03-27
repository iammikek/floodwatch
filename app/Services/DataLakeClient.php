<?php

namespace App\Services;

use App\Support\ConfigKey;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class DataLakeClient
{
    public function __construct(
        private ?string $baseUrl = null,
        private ?int $timeout = null
    ) {
        $this->baseUrl = $this->baseUrl ?? (string) config(ConfigKey::DATA_LAKE.'.base_url');
        $this->timeout = $this->timeout ?? (int) config(ConfigKey::DATA_LAKE.'.timeout');
    }

    public function fetch(string $path, mixed $query = null, ?string $ifNoneMatch = null): DataLakeResponse
    {
        $url = rtrim($this->baseUrl ?? '', '/').$path;
        $req = Http::timeout($this->timeout ?? 10);
        if ($ifNoneMatch !== null && $ifNoneMatch !== '') {
            $req = $req->withHeaders(['If-None-Match' => $ifNoneMatch]);
        }
        $retryTimes = (int) config(ConfigKey::DATA_LAKE.'.retry_times', 2);
        $retrySleep = (int) config(ConfigKey::DATA_LAKE.'.retry_sleep_ms', 50);

        /** @var Response $resp */
        $resp = $req->get($url, is_array($query) ? $query : []);
        $status = $resp->status();
        $attempt = 0;
        while (($status === 429 || $status >= 500) && $attempt < $retryTimes) {
            $retryAfterHeader = $resp->header('Retry-After');
            $rateResetHeader = $resp->header('X-RateLimit-Reset');
            $retryAfter = is_numeric($retryAfterHeader) ? (int) $retryAfterHeader : 0;
            $rateReset = is_numeric($rateResetHeader) ? (int) $rateResetHeader : 0;
            $sleepMs = $retrySleep;
            if ($retryAfter > 0) {
                $sleepMs = max($sleepMs, $retryAfter * 1000);
            } elseif ($rateReset > 0) {
                $now = time();
                $delta = $rateReset > $now ? ($rateReset - $now) : $rateReset;
                if ($delta > 0) {
                    $sleepMs = max($sleepMs, $delta * 1000);
                }
            }
            usleep($sleepMs * 1000);
            $attempt++;
            $resp = $req->get($url, is_array($query) ? $query : []);
            $status = $resp->status();
        }
        $etag = $resp->header('ETag');

        return new DataLakeResponse($status, $etag, $status === 304 ? null : $resp->json());
    }

    public function fetchBinary(string $path, mixed $query = null, ?string $ifNoneMatch = null): DataLakeResponse
    {
        $url = rtrim($this->baseUrl ?? '', '/').$path;
        $req = Http::timeout($this->timeout ?? 10)->withHeaders(['Accept' => 'application/x-protobuf']);
        if ($ifNoneMatch !== null && $ifNoneMatch !== '') {
            $req = $req->withHeaders(['If-None-Match' => $ifNoneMatch]);
        }
        $retryTimes = (int) config(ConfigKey::DATA_LAKE.'.retry_times', 2);
        $retrySleep = (int) config(ConfigKey::DATA_LAKE.'.retry_sleep_ms', 50);

        /** @var Response $resp */
        $resp = $req->get($url, is_array($query) ? $query : []);
        $status = $resp->status();
        $attempt = 0;
        while (($status === 429 || $status >= 500) && $attempt < $retryTimes) {
            $retryAfterHeader = $resp->header('Retry-After');
            $rateResetHeader = $resp->header('X-RateLimit-Reset');
            $retryAfter = is_numeric($retryAfterHeader) ? (int) $retryAfterHeader : 0;
            $rateReset = is_numeric($rateResetHeader) ? (int) $rateResetHeader : 0;
            $sleepMs = $retrySleep;
            if ($retryAfter > 0) {
                $sleepMs = max($sleepMs, $retryAfter * 1000);
            } elseif ($rateReset > 0) {
                $now = time();
                $delta = $rateReset > $now ? ($rateReset - $now) : $rateReset;
                if ($delta > 0) {
                    $sleepMs = max($sleepMs, $delta * 1000);
                }
            }
            usleep($sleepMs * 1000);
            $attempt++;
            $resp = $req->get($url, is_array($query) ? $query : []);
            $status = $resp->status();
        }
        $etag = $resp->header('ETag');

        return new DataLakeResponse($status, $etag, $status === 304 ? null : $resp->body());
    }

    public function getWarnings(
        ?string $bbox = null,
        ?string $region = null,
        ?string $since = null,
        ?string $county = null,
        ?int $minSeverity = null,
        ?string $ifNoneMatch = null
    ): DataLakeResponse {
        $query = [];
        if ($bbox !== null) {
            $query['bbox'] = $bbox;
        }
        if ($region !== null) {
            $query['region'] = $region;
        }
        if ($since !== null) {
            $query['since'] = $since;
        }
        if ($county !== null) {
            $query['county'] = $county;
        }
        if ($minSeverity !== null) {
            $query['min_severity'] = $minSeverity;
        }

        return $this->fetch('/v1/warnings', $query, $ifNoneMatch);
    }

    public function getMeasurements(
        ?string $stationId = null,
        ?string $measureId = null,
        ?string $region = null,
        ?string $bbox = null,
        ?string $from = null,
        ?string $to = null,
        string $aggregate = 'raw',
        int $page = 1,
        int $limit = 500,
        ?string $ifNoneMatch = null
    ): DataLakeResponse {
        $query = [
            'aggregate' => $aggregate,
            'page' => $page,
            'limit' => $limit,
        ];
        if ($stationId !== null) {
            $query['station_id'] = $stationId;
        }
        if ($measureId !== null) {
            $query['measure_id'] = $measureId;
        }
        if ($region !== null) {
            $query['region'] = $region;
        }
        if ($bbox !== null) {
            $query['bbox'] = $bbox;
        }
        if ($from !== null) {
            $query['from'] = $from;
        }
        if ($to !== null) {
            $query['to'] = $to;
        }

        return $this->fetch('/v1/measurements', $query, $ifNoneMatch);
    }

    public function getPolygons(
        string $dataset,
        string $region,
        ?string $scenario = null,
        string $format = 'simplified',
        bool $inline = false,
        ?string $bbox = null,
        ?string $ifNoneMatch = null
    ): DataLakeResponse {
        $query = [
            'dataset' => $dataset,
            'region' => $region,
            'format' => $format,
            'inline' => $inline ? 'true' : 'false',
        ];
        if ($scenario !== null) {
            $query['scenario'] = $scenario;
        }
        if ($inline && $bbox !== null) {
            $query['bbox'] = $bbox;
        }

        return $this->fetch('/v1/polygons', $query, $ifNoneMatch);
    }

    public function getPolygonTile(
        string $dataset,
        int $z,
        int $x,
        int $y,
        string $region,
        ?string $scenario = null,
        string $format = 'simplified',
        ?string $ifNoneMatch = null
    ): DataLakeResponse {
        $query = [
            'region' => $region,
            'format' => $format,
        ];
        if ($scenario !== null) {
            $query['scenario'] = $scenario;
        }

        return $this->fetchBinary("/v1/polygons/tiles/{$dataset}/{$z}/{$x}/{$y}", $query, $ifNoneMatch);
    }

    public function getWarningTile(
        int $z,
        int $x,
        int $y,
        ?string $region = null,
        ?int $minSeverity = null,
        ?string $ifNoneMatch = null
    ): DataLakeResponse {
        $query = [];
        if ($region !== null && $region !== '') {
            $query['region'] = $region;
        }
        if ($minSeverity !== null) {
            $query['min_severity'] = $minSeverity;
        }

        return $this->fetchBinary("/v1/warnings/tiles/{$z}/{$x}/{$y}.pbf", $query, $ifNoneMatch);
    }
}
