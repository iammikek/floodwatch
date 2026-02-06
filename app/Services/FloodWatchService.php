<?php

namespace App\Services;

use App\Flood\DTOs\FloodWarning;
use App\Flood\Services\EnvironmentAgencyFloodService;
use App\Flood\Services\FloodForecastService;
use App\Flood\Services\RiverLevelService;
use App\Roads\Services\NationalHighwaysService;
use App\Support\LogMasker;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class FloodWatchService
{
    public function __construct(
        protected EnvironmentAgencyFloodService $floodService,
        protected NationalHighwaysService $highwaysService,
        protected FloodForecastService $forecastService,
        protected WeatherService $weatherService,
        protected RiverLevelService $riverLevelService,
        protected RiskCorrelationService $correlationService,
        protected FloodWatchPromptBuilder $promptBuilder
    ) {}

    /**
     * Fetch map data (floods, incidents, river levels, forecast, weather) without AI.
     * Used for fast map rendering and by the Situational Awareness dashboard.
     *
     * @return array{floods: array, incidents: array, riverLevels: array, forecast: array, weather: array, lastChecked: string}
     */
    public function getMapData(float $lat, float $long, ?string $region = null, ?array $bounds = null): array
    {
        $radiusKm = config('flood-watch.default_radius_km', 15);
        $store = $this->resolveCacheStore();
        $cacheKey = $this->mapDataCacheKey($lat, $long, $bounds);
        $cacheEnabled = config('flood-watch.cache_ttl_minutes', 15) > 0;

        if ($cacheEnabled) {
            $cached = $this->cacheGet($store, $cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        [$floods, $incidents, $riverLevels, $forecast, $weather] = Concurrency::run([
            fn () => $this->floodService->getFloods($lat, $long, $radiusKm),
            fn () => $this->filterIncidentsByRegion($this->highwaysService->getIncidents(), $region),
            fn () => $this->riverLevelService->getLevels($lat, $long, $radiusKm),
            fn () => $this->forecastService->getForecast(),
            fn () => $this->weatherService->getForecast($lat, $long),
        ]);

        $incidents = $this->sortIncidentsByPriority($incidents);

        $mapBounds = $bounds ?? $this->boundsFromCenter($lat, $long, $radiusKm);
        $floods = $bounds !== null ? $this->filterByBounds($floods, $bounds) : $floods;
        $riverLevels = $bounds !== null ? $this->filterRiverLevelsByBounds($riverLevels, $bounds) : $riverLevels;
        $incidents = $this->filterIncidentsByBounds($incidents, $mapBounds);

        $result = [
            'floods' => $floods,
            'incidents' => $incidents,
            'riverLevels' => $riverLevels,
            'forecast' => $forecast,
            'weather' => $weather,
            'lastChecked' => now()->toIso8601String(),
        ];

        $ttl = config('flood-watch.cache_ttl_minutes', 15) * 60;
        if ($cacheEnabled && $ttl > 0) {
            $this->cachePut($store, $cacheKey, $result, $ttl);
        }

        return $result;
    }

    /**
     * Fetch map data without using cache. Used by FetchLatestInfrastructureData job for delta comparison.
     *
     * @return array{floods: array, incidents: array, riverLevels: array}
     */
    public function getMapDataUncached(float $lat, float $long, ?string $region = null): array
    {
        $radiusKm = config('flood-watch.default_radius_km', 15);

        [$floods, $incidents, $riverLevels] = Concurrency::run([
            fn () => $this->floodService->getFloods($lat, $long, $radiusKm),
            fn () => $this->filterIncidentsByRegion($this->highwaysService->getIncidents(), $region),
            fn () => $this->riverLevelService->getLevels($lat, $long, $radiusKm),
        ]);

        $incidents = $this->sortIncidentsByPriority($incidents);
        $bounds = $this->boundsFromCenter($lat, $long, $radiusKm);
        $incidents = $this->filterIncidentsByBounds($incidents, $bounds);

        return [
            'floods' => $floods,
            'incidents' => $incidents,
            'riverLevels' => $riverLevels,
        ];
    }

    private function mapDataCacheKey(float $lat, float $long, ?array $bounds): string
    {
        $prefix = config('flood-watch.cache_key_prefix', 'flood-watch');
        $rounded = round($lat, 2).':'.round($long, 2);
        if ($bounds !== null) {
            $rounded .= ':'.implode(',', array_map(fn ($v) => round((float) $v, 2), $bounds));
        }

        return "{$prefix}:map:".md5($rounded);
    }

    /**
     * @param  array<int, array{lat?: float, long?: float}>  $items
     * @param  array{0: float, 1: float, 2: float, 3: float}  $bounds  [minLat, maxLat, minLng, maxLng]
     * @return array<int, array>
     */
    private function filterByBounds(array $items, array $bounds): array
    {
        [$minLat, $maxLat, $minLng, $maxLng] = $bounds;

        return array_values(array_filter($items, function (array $item) use ($minLat, $maxLat, $minLng, $maxLng): bool {
            $lat = $item['lat'] ?? null;
            $long = $item['long'] ?? null;
            if ($lat === null || $long === null) {
                return true;
            }

            return $lat >= $minLat && $lat <= $maxLat && $long >= $minLng && $long <= $maxLng;
        }));
    }

    /**
     * @param  array<int, array{lat?: float, long?: float}>  $stations
     * @param  array{0: float, 1: float, 2: float, 3: float}  $bounds
     * @return array<int, array>
     */
    private function filterRiverLevelsByBounds(array $stations, array $bounds): array
    {
        return $this->filterByBounds($stations, $bounds);
    }

    /**
     * Filter incidents to those within map bounds. Excludes incidents without lat/long (cannot verify).
     *
     * @param  array<int, array<string, mixed>>  $incidents
     * @return array<int, array<string, mixed>>
     */
    private function filterIncidentsByBounds(array $incidents, array $bounds): array
    {
        [$minLat, $maxLat, $minLng, $maxLng] = $bounds;

        return array_values(array_filter($incidents, function (array $item) use ($minLat, $maxLat, $minLng, $maxLng): bool {
            $lat = $item['lat'] ?? null;
            $long = $item['long'] ?? null;
            if ($lat === null || $long === null) {
                return false;
            }

            return $lat >= $minLat && $lat <= $maxLat && $long >= $minLng && $long <= $maxLng;
        }));
    }

    /**
     * Compute map bounds [minLat, maxLat, minLng, maxLng] from center and radius.
     */
    private function boundsFromCenter(float $lat, float $long, float $radiusKm): array
    {
        $degPerKmLat = 1 / 111.0;
        $degPerKmLng = 1 / (111.0 * cos(deg2rad($lat)));
        $deltaLat = $radiusKm * $degPerKmLat;
        $deltaLng = $radiusKm * $degPerKmLng;

        return [
            $lat - $deltaLat,
            $lat + $deltaLat,
            $long - $deltaLng,
            $long + $deltaLng,
        ];
    }

    /**
     * Send a user message to the Flood Watch Assistant and return the synthesized response with flood and road data.
     * Results are cached to avoid hammering the APIs. Use $cacheKey to scope the cache (e.g. postcode).
     *
     * @param  array<int, array{role: string, content: string}>  $conversation  Previous messages (optional)
     * @param  callable(string): void|null  $onProgress  Optional callback for progress updates (e.g. for streaming to UI)
     * @param  array{floods?: array, incidents?: array, riverLevels?: array, forecast?: array, weather?: array}|null  $preFetchedData
     * @return array{response: string, floods: array, incidents: array, forecast: array, weather: array, lastChecked: string}
     */
    public function chat(string $userMessage, array $conversation = [], ?string $cacheKey = null, ?float $userLat = null, ?float $userLong = null, ?string $region = null, ?callable $onProgress = null, ?array $preFetchedData = null): array
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
            return $emptyResult(__('flood-watch.error.no_api_key'));
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

        $lat = $userLat ?? config('flood-watch.default_lat');
        $long = $userLong ?? config('flood-watch.default_long');

        if ($preFetchedData !== null) {
            $forecast = $preFetchedData['forecast'] ?? [];
            $weather = $preFetchedData['weather'] ?? [];
            $riverLevels = $preFetchedData['riverLevels'] ?? [];
            $floods = $preFetchedData['floods'] ?? [];
            $incidents = $preFetchedData['incidents'] ?? [];
        } else {
            $report(__('flood-watch.progress.fetching_prefetch'));
            [$forecast, $weather, $riverLevels] = Concurrency::run([
                fn () => app(FloodForecastService::class)->getForecast(),
                fn () => app(WeatherService::class)->getForecast($lat, $long),
                fn () => app(RiverLevelService::class)->getLevels($lat, $long),
            ]);
            $floods = [];
            $incidents = [];
        }

        $report(__('flood-watch.progress.calling_assistant'));
        $messages = $this->buildMessages($userMessage, $conversation, $region);
        $tools = $this->promptBuilder->getToolDefinitions();
        $maxIterations = 8;
        $iteration = 0;

        while ($iteration < $maxIterations) {
            if ($iteration > 0) {
                $report(__('flood-watch.progress.analysing'));
            }

            $messages = $this->trimMessagesToTokenBudget($messages);
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
            Log::debug('FloodWatch OpenAI payload content', ['payload' => LogMasker::maskOpenAiPayload($payload)]);

            $response = OpenAI::chat()->create($payload);

            $choice = $response->choices[0] ?? null;
            if (! $choice) {
                return $emptyResult(__('flood-watch.error.no_response'), now()->toIso8601String());
            }

            $message = $choice->message;
            $finishReason = $choice->finishReason ?? '';

            if ($finishReason === 'stop' || $finishReason === 'end_turn') {
                $report(__('flood-watch.progress.preparing_summary'));

                return $this->storeAndReturn($key, [
                    'response' => trim($message->content ?? __('flood-watch.error.no_content')),
                    'floods' => $floods,
                    'incidents' => $incidents,
                    'forecast' => $forecast,
                    'weather' => $weather,
                    'riverLevels' => $riverLevels,
                ]);
            }

            if (empty($message->toolCalls)) {
                $report(__('flood-watch.progress.preparing_summary'));

                return $this->storeAndReturn($key, [
                    'response' => trim($message->content ?? __('flood-watch.error.no_content')),
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
                    'GetFloodData' => __('flood-watch.progress.fetching_floods'),
                    'GetHighwaysIncidents' => __('flood-watch.progress.checking_roads'),
                    'GetFloodForecast' => __('flood-watch.progress.getting_forecast'),
                    'GetRiverLevels' => __('flood-watch.progress.fetching_river_levels'),
                    'GetCorrelationSummary' => __('flood-watch.progress.correlating'),
                    default => __('flood-watch.progress.loading'),
                });
                $context = [
                    'floods' => $floods,
                    'incidents' => $incidents,
                    'riverLevels' => $riverLevels,
                    'region' => $region,
                    'preFetchedData' => $preFetchedData,
                ];
                $result = $this->executeTool($toolName, $toolCall->function->arguments, $context);
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
                Log::debug('FloodWatch tool result content', ['tool' => $toolName, 'content' => LogMasker::maskToolContent($toolName, $content)]);
                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $toolCall->id,
                    'content' => $content,
                ];
            }

            $iteration++;
        }

        return $emptyResult(__('flood-watch.error.max_tool_calls'), now()->toIso8601String());
    }

    private function buildSystemPrompt(?string $region): string
    {
        return $this->promptBuilder->buildSystemPrompt($region);
    }

    private function cacheKey(string $userMessage, ?string $cacheKey): string
    {
        $prefix = config('flood-watch.cache_key_prefix', 'flood-watch');

        if ($cacheKey !== null && $cacheKey !== '') {
            return "{$prefix}:chat:".md5($cacheKey);
        }

        return "{$prefix}:chat:".md5($userMessage);
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
     * @param  array{floods?: array, incidents?: array, riverLevels?: array, region?: string|null, preFetchedData?: array|null}  $context
     */
    private function executeTool(string $name, string $argumentsJson, array $context = []): array|string
    {
        $args = json_decode($argumentsJson, true) ?? [];
        $preFetched = $context['preFetchedData'] ?? null;

        if ($preFetched !== null) {
            $fromPrefetch = match ($name) {
                'GetFloodData' => $context['floods'] ?? [],
                'GetHighwaysIncidents' => $context['incidents'] ?? [],
                'GetFloodForecast' => $preFetched['forecast'] ?? [],
                'GetRiverLevels' => $context['riverLevels'] ?? [],
                default => null,
            };
            if ($fromPrefetch !== null) {
                return $fromPrefetch;
            }
        }

        if ($name === 'GetCorrelationSummary') {
            $assessment = $this->correlationService->correlate(
                $context['floods'] ?? [],
                $context['incidents'] ?? [],
                $context['riverLevels'] ?? [],
                $context['region'] ?? null
            );

            return $assessment->toArray();
        }

        return match ($name) {
            'GetFloodData' => $this->floodService->getFloods(
                $args['lat'] ?? null,
                $args['long'] ?? null,
                $args['radius_km'] ?? null
            ),
            'GetHighwaysIncidents' => $this->sortIncidentsByPriority(
                $this->filterIncidentsByRegion(
                    $this->highwaysService->getIncidents(),
                    $context['region'] ?? null
                )
            ),
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
     * Prepare tool result for LLM consumption by stripping large/unnecessary data and applying limits.
     * Reduces token usage and avoids context length exceeded errors (128k tokens).
     */
    private function prepareToolResultForLlm(string $toolName, array|string $result): array|string
    {
        if (is_string($result)) {
            return $result;
        }

        if ($toolName === 'GetFloodData') {
            $max = config('flood-watch.llm_max_floods', 25);
            $maxMsg = config('flood-watch.llm_max_flood_message_chars', 300);
            $floods = array_slice($result, 0, $max);
            $out = [];
            foreach ($floods as $flood) {
                $arr = FloodWarning::fromArray($flood)->withoutPolygon()->toArray();
                if (isset($arr['message']) && strlen($arr['message']) > $maxMsg) {
                    $arr['message'] = substr($arr['message'], 0, $maxMsg).'…';
                }
                $out[] = $arr;
            }

            return $out;
        }

        if ($toolName === 'GetHighwaysIncidents') {
            $max = config('flood-watch.llm_max_incidents', 25);

            return array_slice($result, 0, $max);
        }

        if ($toolName === 'GetRiverLevels') {
            $max = config('flood-watch.llm_max_river_levels', 15);

            return array_slice($result, 0, $max);
        }

        if ($toolName === 'GetFloodForecast') {
            $maxChars = config('flood-watch.llm_max_forecast_chars', 1200);
            if (isset($result['england_forecast']) && strlen($result['england_forecast']) > $maxChars) {
                $result['england_forecast'] = substr($result['england_forecast'], 0, $maxChars).'…';
            }
            $maxExtraChars = 800;
            foreach (['flood_risk_trend', 'sources'] as $key) {
                if (isset($result[$key]) && is_array($result[$key])) {
                    $encoded = json_encode($result[$key]);
                    if (strlen($encoded) > $maxExtraChars) {
                        $result[$key] = array_slice($result[$key], 0, 3, true);
                    }
                }
            }

            return $result;
        }

        if ($toolName === 'GetCorrelationSummary') {
            $maxFloods = config('flood-watch.llm_max_floods', 12);
            $maxIncidents = config('flood-watch.llm_max_incidents', 12);
            $maxMsgChars = config('flood-watch.llm_max_flood_message_chars', 150);
            $maxTotalChars = config('flood-watch.llm_max_correlation_chars', 8000);

            $stripFlood = function (array $f) use ($maxMsgChars): array {
                try {
                    $arr = FloodWarning::fromArray($f)->withoutPolygon()->toArray();
                } catch (\Throwable) {
                    return ['description' => $f['description'] ?? '', 'severity' => $f['severity'] ?? '', 'message' => substr((string) ($f['message'] ?? ''), 0, $maxMsgChars)];
                }
                if (isset($arr['message']) && strlen($arr['message']) > $maxMsgChars) {
                    $arr['message'] = substr($arr['message'], 0, $maxMsgChars).'…';
                }

                return $arr;
            };

            $result['severe_floods'] = array_map($stripFlood, array_slice($result['severe_floods'] ?? [], 0, $maxFloods));
            $result['flood_warnings'] = array_map($stripFlood, array_slice($result['flood_warnings'] ?? [], 0, $maxFloods));
            $result['road_incidents'] = array_slice($result['road_incidents'] ?? [], 0, $maxIncidents);
            $result['cross_references'] = array_slice($result['cross_references'] ?? [], 0, 15);
            $result['predictive_warnings'] = array_slice($result['predictive_warnings'] ?? [], 0, 10);

            $encoded = json_encode($result);
            while (strlen($encoded) > $maxTotalChars && ($maxFloods > 2 || $maxIncidents > 2)) {
                if ($maxFloods > 2) {
                    $maxFloods--;
                    $result['severe_floods'] = array_slice($result['severe_floods'], 0, $maxFloods);
                    $result['flood_warnings'] = array_slice($result['flood_warnings'], 0, $maxFloods);
                }
                if (strlen(json_encode($result)) > $maxTotalChars && $maxIncidents > 2) {
                    $maxIncidents--;
                    $result['road_incidents'] = array_slice($result['road_incidents'], 0, $maxIncidents);
                }
                $encoded = json_encode($result);
            }

            return $result;
        }

        return $result;
    }

    /**
     * Trim messages to stay under the model's context limit. Keeps system, user,
     * and only the most recent assistant+tool block when over budget.
     *
     * @param  array<int, array{role: string, content?: string|null, tool_calls?: array, tool_call_id?: string}>  $messages
     * @return array<int, array{role: string, content?: string|null, tool_calls?: array, tool_call_id?: string}>
     */
    private function trimMessagesToTokenBudget(array $messages): array
    {
        $maxTokens = config('flood-watch.llm_max_context_tokens', 110000);
        $estimate = fn (array $m): int => (int) ceil(strlen(json_encode(['messages' => $m])) / 4);
        $estimatedTokens = $estimate($messages);

        if ($estimatedTokens <= $maxTokens) {
            return $messages;
        }

        $lastAssistantIndex = null;
        for ($i = count($messages) - 1; $i >= 0; $i--) {
            if (($messages[$i]['role'] ?? '') === 'assistant' && isset($messages[$i]['tool_calls'])) {
                $lastAssistantIndex = $i;
                break;
            }
        }

        if ($lastAssistantIndex !== null) {
            $messages = [
                $messages[0],
                $messages[1],
                ...array_slice($messages, $lastAssistantIndex),
            ];
            Log::warning('FloodWatch trimmed to last assistant+tool block', [
                'estimated_tokens' => $estimate($messages),
            ]);
        }

        return $this->truncateToolContentsToBudget($messages, $maxTokens);
    }

    /**
     * When messages are still over budget, truncate tool message contents until under limit.
     *
     * @param  array<int, array{role: string, content?: string|null, tool_calls?: array, tool_call_id?: string}>  $messages
     * @return array<int, array{role: string, content?: string|null, tool_calls?: array, tool_call_id?: string}>
     */
    private function truncateToolContentsToBudget(array $messages, int $maxTokens): array
    {
        $estimate = fn (array $m): int => (int) ceil(strlen(json_encode(['messages' => $m])) / 4);

        if ($estimate($messages) <= $maxTokens) {
            return $messages;
        }

        $maxContentChars = 8000;
        $step = 2000;

        while ($estimate($messages) > $maxTokens && $maxContentChars > 500) {
            foreach ($messages as $i => $msg) {
                if (($msg['role'] ?? '') === 'tool' && isset($msg['content']) && strlen($msg['content']) > $maxContentChars) {
                    $messages[$i]['content'] = substr($msg['content'], 0, $maxContentChars).'… [truncated]';
                }
            }
            $maxContentChars -= $step;
        }

        if ($estimate($messages) > $maxTokens) {
            Log::warning('FloodWatch payload still over token budget after truncation', [
                'estimated_tokens' => $estimate($messages),
                'max_tokens' => $maxTokens,
            ]);
        }

        return $messages;
    }

    /**
     * Sort incidents by priority: flood-related first, then roadClosed before laneClosures.
     *
     * @param  array<int, array<string, mixed>>  $incidents
     * @return array<int, array<string, mixed>>
     */
    private function sortIncidentsByPriority(array $incidents): array
    {
        usort($incidents, function (array $a, array $b): int {
            $aFlood = (bool) ($a['isFloodRelated'] ?? false);
            $bFlood = (bool) ($b['isFloodRelated'] ?? false);
            if ($aFlood !== $bFlood) {
                return $aFlood ? -1 : 1;
            }
            $aClosed = ($a['managementType'] ?? '') === 'roadClosed';
            $bClosed = ($b['managementType'] ?? '') === 'roadClosed';
            if ($aClosed !== $bClosed) {
                return $aClosed ? -1 : 1;
            }

            return 0;
        });

        return array_values($incidents);
    }

    /**
     * Filter incidents to only those on roads within the region's county limits (M4, M5, A roads in the South West).
     *
     * @param  array<int, array{road?: string, status?: string, incidentType?: string, delayTime?: string}>  $incidents
     * @return array<int, array{road?: string, status?: string, incidentType?: string, delayTime?: string}>
     */
    private function filterIncidentsByRegion(array $incidents, ?string $region): array
    {
        $allowed = $this->getAllowedRoadsForRegion($region);
        if (empty($allowed)) {
            return $incidents;
        }

        return array_values(array_filter($incidents, function (array $incident) use ($allowed): bool {
            $road = trim((string) ($incident['road'] ?? ''));
            if ($road === '') {
                return false;
            }
            $baseRoad = $this->extractBaseRoad($road);

            return in_array($baseRoad, $allowed, true);
        }));
    }

    /**
     * @return array<int, string>
     */
    private function getAllowedRoadsForRegion(?string $region): array
    {
        if ($region !== null && $region !== '') {
            $keyRoutes = config("flood-watch.correlation.{$region}.key_routes", []);
            if (! empty($keyRoutes)) {
                return array_values(array_unique($this->extractBaseRoads($keyRoutes)));
            }
        }

        return config('flood-watch.incident_allowed_roads', []);
    }

    /**
     * @param  array<int, string>  $keyRoutes
     * @return array<int, string>
     */
    private function extractBaseRoads(array $keyRoutes): array
    {
        $roads = [];
        foreach ($keyRoutes as $route) {
            $base = $this->extractBaseRoad($route);
            if ($base !== '') {
                $roads[] = $base;
            }
        }

        return array_values(array_unique($roads));
    }

    private function extractBaseRoad(string $roadOrKeyRoute): string
    {
        if (preg_match('/^([AM]\d+[A-Z]?)/', trim($roadOrKeyRoute), $m)) {
            return $m[1];
        }

        return '';
    }
}
