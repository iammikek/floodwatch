<?php

namespace App\DTOs;

/**
 * Deterministic correlation of flood warnings, road incidents, and river levels.
 * Produced by RiskCorrelationService for LLM context and non-LLM consumers.
 */
final readonly class RiskAssessment
{
    /**
     * @param  array<int, array{floodArea: string, road: string, hasIncident: bool, note?: string}>  $crossReferences
     * @param  array<int, array{type: string, message: string, reason: string}>  $predictiveWarnings
     * @param  array<int, string>  $keyRoutes
     */
    public function __construct(
        public array $severeFloods,
        public array $floodWarnings,
        public array $roadIncidents,
        public array $crossReferences,
        public array $predictiveWarnings,
        public array $keyRoutes,
    ) {}

    public function toArray(): array
    {
        return [
            'severe_floods' => $this->severeFloods,
            'flood_warnings' => $this->floodWarnings,
            'road_incidents' => $this->roadIncidents,
            'cross_references' => $this->crossReferences,
            'predictive_warnings' => $this->predictiveWarnings,
            'key_routes' => $this->keyRoutes,
        ];
    }

    public function toPromptContext(): string
    {
        $lines = [];

        if (! empty($this->severeFloods)) {
            $lines[] = '**Severe flood warnings (Danger to Life):** '.implode(', ', array_column($this->severeFloods, 'description'));
        }
        if (! empty($this->crossReferences)) {
            $refs = array_map(fn ($r) => "{$r['floodArea']} ↔ {$r['road']}".($r['hasIncident'] ? ' (incident reported)' : ''), $this->crossReferences);
            $lines[] = '**Flood–road cross-references:** '.implode('; ', $refs);
        }
        if (! empty($this->predictiveWarnings)) {
            $lines[] = '**Predictive warnings:** '.implode(' ', array_column($this->predictiveWarnings, 'message'));
        }
        if (! empty($this->keyRoutes)) {
            $lines[] = '**Key routes to monitor:** '.implode(', ', $this->keyRoutes);
        }

        if (empty($lines)) {
            return '';
        }

        return "\n\n**Correlation summary:**\n".implode("\n", $lines);
    }
}
