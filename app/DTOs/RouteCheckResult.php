<?php

namespace App\DTOs;

/**
 * Result of a route check: verdict, summary, and affected floods/incidents.
 *
 * @param  string  $verdict  One of: blocked, at_risk, delays, clear, getError
 * @param  string  $summary  Human-readable summary
 * @param  array<int, array<string, mixed>>  $floodsOnRoute  Floods affecting the route
 * @param  array<int, array<string, mixed>>  $incidentsOnRoute  Incidents affecting the route
 * @param  array<int, array{names: array<string>, distance: float, duration: float}>  $alternatives  Alternative routes (text only)
 * @param  array<int, array{0: float, 1: float}>|null  $routeGeometry  GeoJSON LineString coordinates [[lng,lat],...] for map
 * @param  string|null  $routeKey  Precomputed stable key for map wire:key (avoids hashing geometry in view)
 */
final readonly class RouteCheckResult
{
    public function __construct(
        public string $verdict,
        public string $summary,
        public array $floodsOnRoute,
        public array $incidentsOnRoute,
        public array $alternatives,
        public ?array $routeGeometry,
        public ?string $routeKey = null,
    ) {}

    public static function error(string $summary): self
    {
        return new self(
            verdict: 'getError',
            summary: $summary,
            floodsOnRoute: [],
            incidentsOnRoute: [],
            alternatives: [],
            routeGeometry: null,
        );
    }

    public function toArray(): array
    {
        return [
            'verdict' => $this->verdict,
            'summary' => $this->summary,
            'floods_on_route' => $this->floodsOnRoute,
            'incidents_on_route' => $this->incidentsOnRoute,
            'alternatives' => $this->alternatives,
            'route_geometry' => $this->routeGeometry,
            'route_key' => $this->routeKey,
        ];
    }

    /**
     * Reconstruct from array (e.g. cache payload). Returns null if array is invalid.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): ?self
    {
        $verdict = $data['verdict'] ?? null;
        if (! is_string($verdict) || $verdict === '') {
            return null;
        }

        return new self(
            verdict: $verdict,
            summary: (string) ($data['summary'] ?? ''),
            floodsOnRoute: is_array($data['floods_on_route'] ?? null) ? $data['floods_on_route'] : [],
            incidentsOnRoute: is_array($data['incidents_on_route'] ?? null) ? $data['incidents_on_route'] : [],
            alternatives: is_array($data['alternatives'] ?? null) ? $data['alternatives'] : [],
            routeGeometry: isset($data['route_geometry']) && is_array($data['route_geometry']) ? $data['route_geometry'] : null,
            routeKey: isset($data['route_key']) && is_string($data['route_key']) ? $data['route_key'] : null,
        );
    }
}
