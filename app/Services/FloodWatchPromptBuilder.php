<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

/**
 * Builds system prompts and tool definitions for the Flood Watch LLM.
 * Prompts are versioned in resources/prompts/{version}/.
 */
class FloodWatchPromptBuilder
{
    public function __construct(
        protected string $version = 'v1'
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
        $path = resource_path("prompts/{$this->version}/system.txt");

        if (! File::exists($path)) {
            throw new \RuntimeException("Prompt file not found: {$path}");
        }

        return trim(File::get($path));
    }

    /**
     * @return array<int, array{type: string, function: array{name: string, description: string, parameters: array}}>
     */
    public function getToolDefinitions(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'GetFloodData',
                    'description' => 'Fetch current flood warnings from the Environment Agency for the South West (Bristol, Somerset, Devon, Cornwall). Use the coordinates provided in the user message when a postcode is given; otherwise use default (Langport 51.0358, -2.8318).',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'lat' => [
                                'type' => 'number',
                                'description' => 'Latitude (default: 51.0358 for Langport)',
                            ],
                            'long' => [
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
                    'name' => 'GetHighwaysIncidents',
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
                    'name' => 'GetFloodForecast',
                    'description' => 'Fetch the latest 5-day flood risk forecast from the Flood Forecasting Centre. Returns England-wide outlook, risk trend (day1–day5), and source summaries (river, coastal, ground) relevant to the South West.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => (object) [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'GetRiverLevels',
                    'description' => 'Fetch real-time river and sea levels from Environment Agency monitoring stations. Same data source as check-for-flooding.service.gov.uk/river-and-sea-levels. Use coordinates from the user message when a postcode is given; otherwise use default (Langport 51.0358, -2.8318). Returns station name, river, town, current level and unit, and reading time.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'lat' => [
                                'type' => 'number',
                                'description' => 'Latitude (default: 51.0358 for Langport)',
                            ],
                            'long' => [
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
                    'name' => 'GetCorrelationSummary',
                    'description' => 'Get a deterministic correlation of flood warnings with road incidents and river levels. Call this after fetching flood and road data to receive cross-references (e.g. North Moor flood ↔ A361), predictive warnings (e.g. Muchelney cut-off risk when Parrett elevated), and key routes to monitor. Use this to inform your summary.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => (object) [],
                    ],
                ],
            ],
        ];
    }
}
