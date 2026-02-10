<?php

namespace App\Roads\Services;

use DOMXPath;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * Somerset Council roadworks and travel incidents. Scraping is run by
 * ScrapeSomersetCouncilRoadworksJob every 15 minutes; this service only reads from cache.
 *
 * @see https://www.somerset.gov.uk/roads-travel-and-parking/roadworks-and-travel/
 */
class SomersetCouncilRoadworksService
{
    private const MAX_HTML_SIZE_BYTES = 2 * 1024 * 1024;

    public static function cacheKey(): string
    {
        $prefix = config('flood-watch.cache_key_prefix', 'flood-watch');

        return "{$prefix}:somerset-roadworks:incidents";
    }

    /**
     * Get incidents from cache (populated by ScrapeSomersetCouncilRoadworksJob).
     * Returns the same shape as National Highways for merging.
     *
     * @return array<int, array{road: string, status: string, incidentType: string, delayTime: string, managementType?: string}>
     *
     * @throws InvalidArgumentException
     */
    public function getIncidents(): array
    {
        if (! config('flood-watch.somerset_council.enabled', true)) {
            return [];
        }

        $cache = Cache::store(config('flood-watch.cache_store'));
        $cached = $cache->get(self::cacheKey());

        return is_array($cached) ? $cached : [];
    }

    /**
     * Scrape the council page and store parsed incidents in cache. Called by the deferred job.
     */
    public function scrapeAndStoreInCache(): void
    {
        if (! config('flood-watch.somerset_council.enabled', true)) {
            return;
        }

        $url = config('flood-watch.somerset_council.roadworks_url');
        $timeout = config('flood-watch.somerset_council.timeout', 15);
        $cacheMinutes = config('flood-watch.somerset_council.cache_minutes', 30);

        $html = $this->fetchHtml($url, $timeout);
        $incidents = $html !== null && $html !== '' ? $this->parseIncidentsFromHtml($html) : [];

        Cache::store(config('flood-watch.cache_store'))->put(self::cacheKey(), $incidents, now()->addMinutes($cacheMinutes));
    }

    private function fetchHtml(string $url, int $timeout): ?string
    {
        try {
            $response = Http::timeout($timeout)
                ->withHeaders(['User-Agent' => 'FloodWatch/1.0 (Somerset flood and road status)'])
                ->get($url);

            if (! $response->successful()) {
                return null;
            }

            return $response->body();
        } catch (ConnectionException $e) {
            report($e);

            return null;
        }
    }

    /**
     * Parse INRIX feed incidents from the page HTML.
     *
     * @return array<int, array{road: string, status: string, incidentType: string, delayTime: string, managementType?: string}>
     */
    private function parseIncidentsFromHtml(string $html): array
    {
        if (strlen($html) > self::MAX_HTML_SIZE_BYTES) {
            return [];
        }

        $incidents = [];
        $dom = new \DOMDocument;

        $previous = libxml_use_internal_errors(true);
        try {
            if (! @$dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD)) {
                return [];
            }

            $xpath = new DOMXPath($dom);

            foreach ($xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' inrix-feed__alert ')]") as $alert) {
                $title = $this->textContent($xpath, ".//*[contains(concat(' ', normalize-space(@class), ' '), ' inrix-feed__alert-title ')]", $alert);
                $details = $this->textContent($xpath, ".//*[contains(concat(' ', normalize-space(@class), ' '), ' inrix-feed__alert-details ')]", $alert);

                $road = $this->normalizeRoadFromTitle($title);
                $delayTime = trim(preg_replace('/\s+/', ' ', $details) ?? '');

                if ($road !== '' || $delayTime !== '') {
                    $incident = [
                        'road' => $road !== '' ? $road : $title,
                        'status' => 'active',
                        'incidentType' => $this->inferIncidentType($details),
                        'delayTime' => $delayTime,
                    ];
                    $managementType = $this->inferManagementType($details);
                    if ($managementType !== null) {
                        $incident['managementType'] = $managementType;
                    }
                    $incidents[] = $incident;
                }
            }

            return $incidents;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function textContent(DOMXPath $xpath, string $expr, \DOMNode $context): string
    {
        $nodes = $xpath->query($expr, $context);
        $node = $nodes->item(0);

        return $node !== null ? trim($node->textContent ?? '') : '';
    }

    private function normalizeRoadFromTitle(string $title): string
    {
        $title = trim($title);
        if (preg_match('/^(.+?)\s*[-–—]\s*(.+)$/u', $title, $m)) {
            $suffix = trim($m[2]);
            if (preg_match('/^(A\d{2,4}|M\d{1,2})\b/i', $suffix)) {
                return $suffix;
            }

            return trim($m[1]).' ('.$suffix.')';
        }

        return $title;
    }

    private function inferIncidentType(string $details): string
    {
        $lower = strtolower($details);
        if (str_contains($lower, 'flood')) {
            return 'flooding';
        }
        if ((str_contains($lower, 'lane') && str_contains($lower, 'close')) || str_contains($lower, 'lane closure')) {
            return 'laneClosures';
        }
        if (str_contains($lower, 'closed') || str_contains($lower, 'closure')) {
            return 'roadClosed';
        }
        if (str_contains($lower, 'roadworks') || str_contains($lower, 'road works')) {
            return 'constructionWork';
        }

        return 'authorityOperation';
    }

    /**
     * When the incident is a full road closure (not lane-only), return managementType for downstream (sorting, blocking).
     */
    private function inferManagementType(string $details): ?string
    {
        $lower = strtolower($details);
        if (str_contains($lower, 'lane') && str_contains($lower, 'close')) {
            return null;
        }
        if (str_contains($lower, 'closed') || str_contains($lower, 'closure')) {
            return 'roadClosed';
        }

        return null;
    }
}
