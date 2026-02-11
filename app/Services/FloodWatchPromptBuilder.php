<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

/**
 * Builds system prompts and tool definitions for the Flood Watch LLM.
 * Prompts are versioned in resources/prompts/{version}/.
 */
class FloodWatchPromptBuilder
{
    /**
     * Cached base prompt to avoid repeated file reads
     */
    protected ?string $cachedBasePrompt = null;

    /**
     * Cached tool definitions to avoid repeated array construction
     */
    protected ?array $cachedToolDefinitions = null;

    public function __construct(
        protected string $version = 'v1',
        protected ?\App\Support\Tooling\ToolRegistry $registry = null,
    ) {}

    public function buildSystemPrompt(?string $region = null): string
    {
        $prompt = $this->loadBasePrompt();

        if ($region !== null && $region !== '') {
            $regionConfig = config("flood-watch.regions.{$region}");
            if (is_array($regionConfig) && ! empty($regionConfig['prompt'])) {
                $prompt .= "\n\n**Region-specific guidance (user's location):**\n".$regionConfig['prompt'];
            }
        }

        return $prompt;
    }

    public function loadBasePrompt(): string
    {
        if ($this->cachedBasePrompt !== null) {
            return $this->cachedBasePrompt;
        }

        $path = resource_path("prompts/{$this->version}/system.txt");

        if (! File::exists($path)) {
            throw new \RuntimeException("Prompt file not found: {$path}");
        }

        $this->cachedBasePrompt = trim(File::get($path));

        return $this->cachedBasePrompt;
    }

    /**
     * @return array<int, array{type: string, function: array{name: string, description: string, parameters: array}}>
     */
    public function getToolDefinitions(): array
    {
        if ($this->cachedToolDefinitions !== null) {
            return $this->cachedToolDefinitions;
        }

        if ($this->registry !== null) {
            $defs = $this->registry->definitions();
            // Optional: keep stable order
            usort($defs, fn ($a, $b) => strcmp($a['function']['name'] ?? '', $b['function']['name'] ?? ''));

            return $this->cachedToolDefinitions = $defs;
        }

        // Fallback to legacy inline definitions when registry not provided
        $this->cachedToolDefinitions = [
            [
                'type' => 'function',
                'function' => [
                    'name' => \App\Enums\ToolName::GetFloodData->value,
                    'description' => 'Fetch current flood warnings from the Environment Agency for the South West (Bristol, Somerset, Devon, Cornwall). Use the coordinates provided in the user message when a postcode is given; otherwise use default (Langport 51.0358, -2.8318).',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'lat' => [
                                'type' => 'number',
                                'description' => 'Latitude (default: 51.0358 for Langport)',
                            ],
                            'lng' => [
                                'type' => 'number',
                                'description' => 'Longitude (default: -2.8318 for Langport)',
                            ],
                            'radius_km' => [
                                'type' => 'integer',
                                'description' => 'Search radius in km (default: 15)',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => \App\Enums\ToolName::GetHighwaysIncidents->value,
                    'description' => 'Fetch road and lane closure incidents from National Highways for South West routes (M5, A38, A30, A303, A361, A372, etc.). Returns road, status, incidentType, delayTime, startTime, endTime, locationDescription (e.g. "M5 southbound between J14 and J13"), managementType (roadClosed or laneClosures), and isFloodRelated. Flooding-related incidents are prioritised.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => (object) [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => \App\Enums\ToolName::GetFloodForecast->value,
                    'description' => 'Fetch the latest 5-day flood risk forecast from the Flood Forecasting Centre. Returns England-wide narrative (risk trend day1–day5, sources). When summarising for the user, focus on South West–relevant parts only; do not highlight areas outside the South West (e.g. River Trent, Midlands).',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => (object) [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => \App\Enums\ToolName::GetRiverLevels->value,
                    'description' => 'Fetch real-time river and sea levels from Environment Agency monitoring stations. Same data source as check-for-flooding.service.gov.uk/river-and-sea-levels. Use coordinates from the user message when a postcode is given; otherwise use default (Langport 51.0358, -2.8318). Returns station name, river, town, current level and unit, and reading time.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'lat' => [
                                'type' => 'number',
                                'description' => 'Latitude (default: 51.0358 for Langport)',
                            ],
                            'lng' => [
                                'type' => 'number',
                                'description' => 'Longitude (default: -2.8318 for Langport)',
                            ],
                            'radius_km' => [
                                'type' => 'integer',
                                'description' => 'Search radius in km (default: 15)',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => \App\Enums\ToolName::GetCorrelationSummary->value,
                    'description' => 'Get a deterministic correlation of flood warnings with road incidents and river levels. Call this after fetching flood and road data to receive cross-references (e.g. North Moor flood ↔ A361), predictive warnings (e.g. Muchelney cut-off risk when Parrett elevated), and key routes to monitor. Use this to inform your summary.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => (object) [],
                    ],
                ],
            ],
        ];

        return $this->cachedToolDefinitions;
    }
}
