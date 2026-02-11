<?php

declare(strict_types=1);

namespace App\Support\Tooling;

final class ToolContext
{
    public function __construct(
        public readonly ?string $region = null,
        public readonly ?float $centerLat = null,
        public readonly ?float $centerLng = null,
    ) {}

    /**
     * @param  array{region?: string|null, centerLat?: float|null, centerLng?: float|null}  $context
     */
    public static function fromArray(array $context): self
    {
        return new self(
            $context['region'] ?? null,
            isset($context['centerLat']) ? (float) $context['centerLat'] : null,
            isset($context['centerLng']) ? (float) $context['centerLng'] : null,
        );
    }
}
