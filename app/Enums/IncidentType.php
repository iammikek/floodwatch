<?php

namespace App\Enums;

/**
 * Road incident types from National Highways DATEX II.
 * Maps to emoji icons for UI display.
 */
enum IncidentType: string
{
    case Flooding = 'flooding';
    case RoadClosed = 'roadClosed';
    case LaneClosures = 'laneClosures';
    case ConstructionWork = 'constructionWork';
    case MaintenanceWork = 'maintenanceWork';
    case SweepingOfRoad = 'sweepingOfRoad';
    case Roadworks = 'roadworks';
    case Accident = 'accident';
    case VehicleObstruction = 'vehicleObstruction';
    case AuthorityOperation = 'authorityOperation';
    case EnvironmentalObstruction = 'environmentalObstruction';
    case Default = 'default';

    public function icon(): string
    {
        return match ($this) {
            self::Flooding => '🌊',
            self::RoadClosed => '🚫',
            self::LaneClosures => '⚠️',
            self::ConstructionWork, self::Roadworks => '🚧',
            self::MaintenanceWork => '🛠️',
            self::SweepingOfRoad => '🧹',
            self::Accident, self::VehicleObstruction => '🚗',
            self::AuthorityOperation, self::EnvironmentalObstruction, self::Default => '🛣️',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::AuthorityOperation => 'Authority operation',
            self::ConstructionWork, self::Roadworks => 'Road works',
            self::MaintenanceWork => 'Maintenance',
            self::RoadClosed => 'Road closed',
            self::LaneClosures => 'Lane closures',
            self::Flooding => 'Flooding',
            self::EnvironmentalObstruction => 'Environmental obstruction',
            self::VehicleObstruction => 'Vehicle obstruction',
            self::SweepingOfRoad => 'Road sweeping',
            self::Accident, self::Default => 'Road incident',
        };
    }

    public static function tryFromString(?string $value): ?self
    {
        if ($value === null || $value === '') {
            return null;
        }

        $lower = strtolower($value);

        $aliases = [
            'lane closure' => self::LaneClosures,
            'road works' => self::Roadworks,
            'roadworks' => self::Roadworks,
        ];

        if (isset($aliases[$lower])) {
            return $aliases[$lower];
        }

        $enum = self::tryFrom($value);
        if ($enum !== null) {
            return $enum;
        }

        foreach (self::cases() as $case) {
            if ($case === self::Default) {
                continue;
            }
            if (str_contains($lower, strtolower($case->value))) {
                return $case;
            }
        }

        return null;
    }
}
