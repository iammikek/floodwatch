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
        public ?string $startTime = null,
        public ?string $endTime = null,
        public ?string $locationDescription = null,
        public ?string $managementType = null,
        public bool $isFloodRelated = false,
    ) {}

    public static function fromArray(array $data): self
    {
        $lat = isset($data['lat']) ? (float) $data['lat'] : null;
        $long = isset($data['long']) ? (float) $data['long'] : null;
        $startTime = isset($data['startTime']) ? (string) $data['startTime'] : null;
        $endTime = isset($data['endTime']) ? (string) $data['endTime'] : null;
        $locationDescription = isset($data['locationDescription']) ? (string) $data['locationDescription'] : null;
        $managementType = isset($data['managementType']) ? (string) $data['managementType'] : null;
        $isFloodRelated = (bool) ($data['isFloodRelated'] ?? false);

        return new self(
            road: $data['road'] ?? $data['roadName'] ?? $data['location'] ?? '',
            status: $data['status'] ?? $data['closureStatus'] ?? '',
            incidentType: $data['incidentType'] ?? $data['type'] ?? '',
            delayTime: $data['delayTime'] ?? $data['delay'] ?? '',
            lat: $lat,
            long: $long,
            startTime: $startTime !== '' ? $startTime : null,
            endTime: $endTime !== '' ? $endTime : null,
            locationDescription: $locationDescription !== '' ? $locationDescription : null,
            managementType: $managementType !== '' ? $managementType : null,
            isFloodRelated: $isFloodRelated,
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
        if ($this->startTime !== null) {
            $arr['startTime'] = $this->startTime;
        }
        if ($this->endTime !== null) {
            $arr['endTime'] = $this->endTime;
        }
        if ($this->locationDescription !== null) {
            $arr['locationDescription'] = $this->locationDescription;
        }
        if ($this->managementType !== null) {
            $arr['managementType'] = $this->managementType;
        }
        if ($this->isFloodRelated) {
            $arr['isFloodRelated'] = true;
        }

        return $arr;
    }
}
