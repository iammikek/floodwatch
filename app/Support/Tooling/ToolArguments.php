<?php

declare(strict_types=1);

namespace App\Support\Tooling;

final class ToolArguments
{
    public function __construct(
        public readonly ?float $lat = null,
        public readonly ?float $lng = null,
        public readonly ?int $radiusKm = null
    ) {}

    public static function fromJson(string $json): self
    {
        $args = json_decode($json, true) ?? [];

        return new self(
            isset($args['lat']) ? (float) $args['lat'] : null,
            isset($args['lng']) ? (float) $args['lng'] : null,
            isset($args['radius_km']) ? (int) $args['radius_km'] : null,
        );
    }
}
