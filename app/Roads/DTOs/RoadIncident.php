<?php

namespace App\Roads\DTOs;

final readonly class RoadIncident
{
    public function __construct(
        public string $road,
        public string $status,
        public string $incidentType,
        public string $delayTime,
        public ?float $lat = null,
        public ?float $long = null,
    ) {}

    public static function fromArray(array $data): self
    {
        $lat = isset($data['lat']) ? (float) $data['lat'] : null;
        $long = isset($data['long']) ? (float) $data['long'] : null;

        return new self(
            road: $data['road'] ?? $data['roadName'] ?? $data['location'] ?? '',
            status: $data['status'] ?? $data['closureStatus'] ?? '',
            incidentType: $data['incidentType'] ?? $data['type'] ?? '',
            delayTime: $data['delayTime'] ?? $data['delay'] ?? '',
            lat: $lat,
            long: $long,
        );
    }

    public function toArray(): array
    {
        $arr = [
            'road' => $this->road,
            'status' => $this->status,
            'incidentType' => $this->incidentType,
            'delayTime' => $this->delayTime,
        ];
        if ($this->lat !== null && $this->long !== null) {
            $arr['lat'] = $this->lat;
            $arr['long'] = $this->long;
        }

        return $arr;
    }
}
