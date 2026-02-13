<?php

declare(strict_types=1);

namespace App\Support\Tooling;

final class ToolContext
{
    /**
     * @param  array<int, array<string, mixed>>|null  $floods
     * @param  array<int, array<string, mixed>>|null  $incidents
     * @param  array<int, array<string, mixed>>|null  $riverLevels
     */
    public function __construct(
        public readonly ?string $region = null,
        public readonly ?float $centerLat = null,
        public readonly ?float $centerLng = null,
        public readonly ?array $floods = null,
        public readonly ?array $incidents = null,
        public readonly ?array $riverLevels = null,
    ) {}

    /**
     * @param  array{region?: string|null, centerLat?: float|null, centerLng?: float|null, floods?: array|null, incidents?: array|null, riverLevels?: array|null}  $context
     */
    public static function fromArray(array $context): self
    {
        return new self(
            $context['region'] ?? null,
            isset($context['centerLat']) ? (float) $context['centerLat'] : null,
            isset($context['centerLng']) ? (float) $context['centerLng'] : null,
            isset($context['floods']) && is_array($context['floods']) ? $context['floods'] : null,
            isset($context['incidents']) && is_array($context['incidents']) ? $context['incidents'] : null,
            isset($context['riverLevels']) && is_array($context['riverLevels']) ? $context['riverLevels'] : null,
        );
    }
}
