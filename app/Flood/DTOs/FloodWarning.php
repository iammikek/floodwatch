<?php

namespace App\Flood\DTOs;

use App\Flood\Enums\SeverityLevel;
use App\Support\CoordinateMapper;
use Carbon\CarbonImmutable;
use Throwable;

final readonly class FloodWarning
{
    public function __construct(
        public string $description,
        public string $severity,
        public SeverityLevel $severityLevel,
        public string $message,
        public string $floodAreaId,
        public ?CarbonImmutable $timeRaised,
        public ?CarbonImmutable $timeMessageChanged,
        public ?CarbonImmutable $timeSeverityChanged,
        public ?float $lat,
        public ?float $lng,
        public ?array $polygon = null,
        public ?float $distanceKm = null,
    ) {}

    public static function fromArray(array $data): self
    {
        $coords = CoordinateMapper::normalize($data);

        return new self(
            description: $data['description'] ?? '',
            severity: $data['severity'] ?? '',
            severityLevel: SeverityLevel::fromApiValue($data['severityLevel'] ?? null),
            message: $data['message'] ?? '',
            floodAreaId: $data['floodAreaID'] ?? '',
            timeRaised: self::parseDateTime($data['timeRaised'] ?? null),
            timeMessageChanged: self::parseDateTime($data['timeMessageChanged'] ?? null),
            timeSeverityChanged: self::parseDateTime($data['timeSeverityChanged'] ?? null),
            lat: $coords['lat'],
            lng: $coords['lng'],
            polygon: $data['polygon'] ?? null,
            distanceKm: isset($data['distanceKm']) ? (float) $data['distanceKm'] : null,
        );
    }

    public function toArray(): array
    {
        $arr = [
            'description' => $this->description,
            'severity' => $this->severity,
            'severityLevel' => $this->severityLevel->value,
            'message' => $this->message,
            'floodAreaID' => $this->floodAreaId,
            'timeRaised' => $this->timeRaised?->toIso8601String(),
            'timeMessageChanged' => $this->timeMessageChanged?->toIso8601String(),
            'timeSeverityChanged' => $this->timeSeverityChanged?->toIso8601String(),
            'lat' => $this->lat,
            'lng' => $this->lng,
        ];
        if ($this->polygon !== null) {
            $arr['polygon'] = $this->polygon;
        }
        if ($this->distanceKm !== null) {
            $arr['distanceKm'] = $this->distanceKm;
        }

        return $arr;
    }

    public function withoutPolygon(): self
    {
        return new self(
            description: $this->description,
            severity: $this->severity,
            severityLevel: $this->severityLevel,
            message: $this->message,
            floodAreaId: $this->floodAreaId,
            timeRaised: $this->timeRaised,
            timeMessageChanged: $this->timeMessageChanged,
            timeSeverityChanged: $this->timeSeverityChanged,
            lat: $this->lat,
            lng: $this->lng,
            polygon: null,
            distanceKm: $this->distanceKm,
        );
    }

    private static function parseDateTime(?string $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
