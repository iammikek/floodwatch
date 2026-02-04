<?php

namespace App\Services;

use App\DTOs\FloodWarning;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class FloodWatchService
{
    private const string BASE_PROMPT = <<<'PROMPT'
You are the South West Emergency Assistant for Bristol, Somerset, Devon and Cornwall. Your role is to correlate Environment Agency flood data with National Highways road status to provide a Single Source of Truth for flood and road viability across the region.

**Data Correlation**: Cross-reference flood warnings with road incidents. Apply region-specific logic (see below) for the user's area.

**River and sea levels**: Use GetRiverLevels to fetch real-time water levels from monitoring stations (same data as check-for-flooding.service.gov.uk). Correlate rising levels with flood warnings and road status.

**Predictive**: Use GetFloodForecast to include the 5-day flood risk outlook. Combine current flood warnings with the forecast trend (day1–day5) to give users forward-looking advice.

**Prioritization**: Prioritize "Danger to Life" alerts, then road closures, then general flood alerts.

**Output Format**: Always structure responses with:
- A "Current Status" section
- An "Action Steps" bulleted list

Only report flood warnings and road incidents that are present in the tool results. Never invent or hallucinate data.
PROMPT;

    public function __construct(
        protected EnvironmentAgencyFloodService $floodService,
        protected NationalHighwaysService $highwaysService,
        protected FloodForecastService $forecastService,
        protected WeatherService $weatherService,
        protected RiverLevelService $riverLevelService
    ) {}

    /**
     * Send a user message to the Flood Watch Assistant and return the synthesized response with flood and road data.
     * Results are cached to avoid hammering the APIs. Use $cacheKey to scope the cache (e.g. postcode).
     *
     * @param  array<int, array{role: string, content: string}>  $conversation  Previous messages (optional)
     * @param  callable(string): void|null  $onProgress  Optional callback for progress updates (e.g. for streaming to UI)
     * @return array{response: string, floods: array, incidents: array, forecast: array, weather: array, lastChecked: string}
     */
    public function chat(string $userMessage, array $conversation = [], ?string $cacheKey = null, ?float $userLat = null, ?float $userLong = null, ?string $region = null, ?callable $onProgress = null): array
    {
        $emptyResult = fn (string $response, ?string $lastChecked = null): array => [
            'response' => $response,
            'floods' => [],
            'incidents' => [],
            'forecast' => [],
            'weather' => [],
            'riverLevels' => [],
            'lastChecked' => $lastChecked ?? now()->toIso8601String(),
        ];

        if (empty(config('openai.api_key'))) {
            return $emptyResult('Flood Watch is not configured with an OpenAI API key. Please add OPENAI_API_KEY to your environment.');
        }

        $store = $this->resolveCacheStore();
        $key = $this->cacheKey($userMessage, $cacheKey);
        $cacheEnabled = config('flood-watch.cache_ttl_minutes', 15) > 0;
        if ($cacheEnabled) {
            $cached = $this->cacheGet($store, $key);
            if ($cached !== null) {
                return $cached;
            }
        }

        $report = static function (string $status) use ($onProgress): void {
            $onProgress !== null && $onProgress($status);
        };

        $report('Fetching 5-day flood forecast...');
        $forecast = $this->forecastService->getForecast();
        $lat = $userLat ?? config('flood-watch.default_lat');
        $long = $userLong ?? config('flood-watch.default_long');

        $report('Getting weather forecast...');
        $weather = $this->weatherService->getForecast($lat, $long);

        $report('Fetching river levels...');
        $riverLevels = $this->riverLevelService->getLevels($lat, $long);

        $report('Calling AI assistant...');
        $messages = $this->buildMessages($userMessage, $conversation, $region);
        $tools = $this->getToolDefinitions();
        $maxIterations = 8;
        $iteration = 0;
        $floods = [];
        $incidents = [];

        while ($iteration < $maxIterations) {
            if ($iteration > 0) {
                $report('Analyzing with AI...');
            }

            $payload = [
                'model' => config('openai.model', 'gpt-4o-mini'),
                'messages' => $messages,
                'tools' => $tools,
                'tool_choice' => 'auto',
            ];
            $payloadJson = json_encode($payload);
            $payloadBytes = strlen($payloadJson);
            $estimatedTokens = (int) ceil($payloadBytes / 4);
            Log::info('FloodWatch OpenAI payload', [
                'size_bytes' => $payloadBytes,
                'estimated_tokens' => $estimatedTokens,
                'message_count' => count($messages),
                'iteration' => $iteration + 1,
            ]);
            Log::debug('FloodWatch OpenAI payload content', ['payload' => $payload]);

            $response = OpenAI::chat()->create($payload);

            $choice = $response->choices[0] ?? null;
            if (! $choice) {
                return $emptyResult('Unable to get a response from the assistant.', now()->toIso8601String());
            }

            $message = $choice->message;
            $finishReason = $choice->finishReason ?? '';

            if ($finishReason === 'stop' || $finishReason === 'end_turn') {
                $report('Preparing your summary...');

                return $this->storeAndReturn($key, [
                    'response' => trim($message->content ?? 'No response generated.'),
                    'floods' => $floods,
                    'incidents' => $incidents,
                    'forecast' => $forecast,
                    'weather' => $weather,
                    'riverLevels' => $riverLevels,
                ]);
            }

            if (empty($message->toolCalls)) {
                $report('Preparing your summary...');

                return $this->storeAndReturn($key, [
                    'response' => trim($message->content ?? 'No response generated.'),
                    'floods' => $floods,
                    'incidents' => $incidents,
                    'forecast' => $forecast,
                    'weather' => $weather,
                    'riverLevels' => $riverLevels,
                ]);
            }

            $messages[] = [
                'role' => 'assistant',
                'content' => $message->content ?? null,
                'tool_calls' => array_map(fn ($tc) => $tc->toArray(), $message->toolCalls),
            ];

            foreach ($message->toolCalls as $toolCall) {
                $toolName = $toolCall->function->name;
                $report(match ($toolName) {
                    'GetFloodData' => 'Fetching flood warnings...',
                    'GetHighwaysIncidents' => 'Checking road status...',
                    'GetFloodForecast' => 'Getting flood forecast...',
                    'GetRiverLevels' => 'Fetching river levels...',
                    default => 'Loading data...',
                });
                $result = $this->executeTool($toolName, $toolCall->function->arguments);
                if ($toolName === 'GetFloodData' && is_array($result)) {
                    $floods = $result;
                }
                if ($toolName === 'GetHighwaysIncidents' && is_array($result)) {
                    $incidents = $result;
                }
                if ($toolName === 'GetFloodForecast' && is_array($result) && ! isset($result['error'])) {
                    $forecast = $result;
                }

                $contentForLlm = $this->prepareToolResultForLlm($toolName, $result);
                $content = is_string($contentForLlm) ? $contentForLlm : json_encode($contentForLlm);
                $contentBytes = strlen($content);
                Log::info('FloodWatch tool result for LLM', [
                    'tool' => $toolName,
                    'size_bytes' => $contentBytes,
                    'estimated_tokens' => (int) ceil($contentBytes / 4),
                ]);
                Log::debug('FloodWatch tool result content', ['tool' => $toolName, 'content' => $content]);
                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $toolCall->id,
                    'content' => $content,
                ];
            }

            $iteration++;
        }

        return $emptyResult('The assistant reached the maximum number of tool calls. Please try again.', now()->toIso8601String());
    }

    private function buildSystemPrompt(?string $region): string
    {
        $prompt = self::BASE_PROMPT;

        if ($region !== null && $region !== '') {
            $regionConfig = config("flood-watch.regions.{$region}");
            if (is_array($regionConfig) && ! empty($regionConfig['prompt'])) {
                $prompt .= "\n\n**Region-specific guidance (user's location):**\n".$regionConfig['prompt'];
            }
        }

        return $prompt;
    }

    private function cacheKey(string $userMessage, ?string $cacheKey): string
    {
        if ($cacheKey !== null && $cacheKey !== '') {
            return 'flood-watch:'.md5($cacheKey);
        }

        return 'flood-watch:'.md5($userMessage);
    }

    /**
     * @param  array{response: string, floods: array, incidents: array, forecast: array, weather: array}  $result
     * @return array{response: string, floods: array, incidents: array, forecast: array, weather: array, lastChecked: string}
     */
    private function storeAndReturn(string $cacheKey, array $result): array
    {
        $result['lastChecked'] = now()->toIso8601String();
        $ttl = config('flood-watch.cache_ttl_minutes', 15) * 60;
        if ($ttl > 0) {
            $store = $this->resolveCacheStore();
            $this->cachePut($store, $cacheKey, $result, $ttl);
        }

        return $result;
    }

    private function resolveCacheStore(): string
    {
        $configured = config('flood-watch.cache_store', 'flood-watch');
        if ($configured === 'flood-watch-array') {
            return 'flood-watch-array';
        }

        return $configured;
    }

    private function cacheGet(string $store, string $key): ?array
    {
        try {
            return Cache::store($store)->get($key);
        } catch (\Throwable) {
            return null;
        }
    }

    private function cachePut(string $store, string $key, array $value, int $ttl): void
    {
        try {
            Cache::store($store)->put($key, $value, $ttl);
        } catch (\Throwable) {
            // Silently skip cache write on Redis failure
        }
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $conversation
     * @return array<int, array{role: string, content: string|null, tool_calls?: array}>
     */
    private function buildMessages(string $userMessage, array $conversation, ?string $region = null): array
    {
        $systemPrompt = $this->buildSystemPrompt($region);

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        foreach ($conversation as $msg) {
            $messages[] = [
                'role' => $msg['role'],
                'content' => $msg['content'],
            ];
        }

        $messages[] = ['role' => 'user', 'content' => $userMessage];

        return $messages;
    }

    /**
     * @return array<int, array{type: string, function: array{name: string, description: string, parameters: array}}>
     */
    private function getToolDefinitions(): array
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
                    'description' => 'Fetch road and lane closure incidents from National Highways for South West routes (M5, A38, A30, A303, A361, A372, etc.). Returns status, delay time, and incident type.',
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
        ];
    }

    private function executeTool(string $name, string $argumentsJson): array|string
    {
        $args = json_decode($argumentsJson, true) ?? [];

        return match ($name) {
            'GetFloodData' => $this->floodService->getFloods(
                $args['lat'] ?? null,
                $args['long'] ?? null,
                $args['radius_km'] ?? null
            ),
            'GetHighwaysIncidents' => $this->highwaysService->getIncidents(),
            'GetFloodForecast' => $this->forecastService->getForecast(),
            'GetRiverLevels' => $this->riverLevelService->getLevels(
                $args['lat'] ?? null,
                $args['long'] ?? null,
                $args['radius_km'] ?? null
            ),
            default => ['error' => "Unknown tool: {$name}"],
        };
    }

    /**
     * Prepare tool result for LLM consumption by stripping large/unnecessary data (e.g. GeoJSON polygons).
     * Reduces token usage and avoids "Request too large" rate limit errors.
     */
    private function prepareToolResultForLlm(string $toolName, array|string $result): array|string
    {
        if (is_string($result)) {
            return $result;
        }

        if ($toolName === 'GetFloodData') {
            return array_map(
                fn (array $flood) => FloodWarning::fromArray($flood)->withoutPolygon()->toArray(),
                $result
            );
        }

        return $result;
    }
}
