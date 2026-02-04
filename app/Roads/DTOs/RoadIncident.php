<?php

namespace App\Roads\DTOs;

final readonly class RoadIncident
{
    public function __construct(
        public string $road,
        public string $status,
        public string $incidentType,
        public string $delayTime,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            road: $data['road'] ?? $data['roadName'] ?? $data['location'] ?? '',
            status: $data['status'] ?? $data['closureStatus'] ?? '',
            incidentType: $data['incidentType'] ?? $data['type'] ?? '',
            delayTime: $data['delayTime'] ?? $data['delay'] ?? '',
        );
    }

    public function toArray(): array
    {
        return [
            'road' => $this->road,
            'status' => $this->status,
            'incidentType' => $this->incidentType,
            'delayTime' => $this->delayTime,
        ];
    }
}
