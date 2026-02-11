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
use Throwable;

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
     * Send a user message to the Flood Watch Assistant and return the synthesized response with flood and road data.
     * Results are cached to avoid hammering the APIs. Use $cacheKey to scope the cache (e.g. postcode).
     *
     * This method implements an iterative LLM workflow with function calling:
     * 1. Pre-fetches forecast, weather, and river levels in parallel
     * 2. Calls OpenAI with tool definitions for GetFloodData, GetHighwaysIncidents, etc.
     * 3. Executes tool calls made by the LLM and returns results
     * 4. Repeats until LLM returns final response (max 8 iterations)
     *
     * @param  string  $userMessage  The user's query (e.g., "Check status for BA1 1AA")
     * @param  array<int, array{role: string, content: string, tool_calls?: array}>  $conversation  Previous messages for multi-turn chat (optional). Each message must have 'role' and 'content'. Assistant messages may include 'tool_calls' array.
     * @param  string|null  $cacheKey  Custom cache key (optional). If not provided, message hash is used.
     * @param  float|null  $userLat  User's latitude (optional). Defaults to config('flood-watch.default_lat').
     * @param  float|null  $userLng  User's longitude (optional). Defaults to config('flood-watch.default_lng').
     * @param  string|null  $region  User's region (e.g., 'somerset', 'bristol') for region-specific prompt injection (optional).
     * @param  int|null  $userId  User ID for LLM request recording in analytics (optional).
     * @param  callable(string): void|null  $onProgress  Optional callback for progress updates (e.g., for streaming status to UI). Receives progress message strings.
     * @return array{response: string, floods: array, incidents: array, forecast: array, weather: array, riverLevels: array, lastChecked: string}  The LLM response and all collected data
     */
    public function chat(string $userMessage, array $conversation = [], ?string $cacheKey = null, ?float $userLat = null, ?float $userLng = null, ?string $region = null, ?int $userId = null, ?callable $onProgress = null): array
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
        $lng = $userLng ?? config('flood-watch.default_lng');

        $report(__('flood-watch.progress.fetching_prefetch'));
        [$forecast, $weather, $riverLevels] = Concurrency::run([
            fn () => app(FloodForecastService::class)->getForecast(),
            fn () => app(WeatherService::class)->getForecast($lat, $lng),
            fn () => app(RiverLevelService::class)->getLevels($lat, $lng),
        ]);

        $report(__('flood-watch.progress.calling_assistant'));
        $messages = $this->buildMessages($userMessage, $conversation, $region);
        $tools = $this->promptBuilder->getToolDefinitions();
        $maxIterations = 8;
        $iteration = 0;
        $floods = [];
        $incidents = [];

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

            try {
                $response = OpenAI::chat()->create($payload);
            } catch (\OpenAI\Exceptions\ErrorException $e) {
                Log::error('FloodWatch OpenAI API error', [
                    'error' => $e->getMessage(),
                    'iteration' => $iteration + 1,
                ]);
                return $emptyResult(__('flood-watch.error.api_error'), now()->toIso8601String());
            } catch (Throwable $e) {
                Log::error('FloodWatch unexpected error during LLM call', [
                    'error' => $e->getMessage(),
                    'iteration' => $iteration + 1,
                ]);
                return $emptyResult(__('flood-watch.error.unexpected'), now()->toIso8601String());
            }

            $this->dispatchRecordLlmRequest($response, $userId, $region);

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
                ];
                
                try {
                    $result = $this->executeTool($toolName, $toolCall->function->arguments, $context);
                } catch (Throwable $e) {
                    Log::warning('FloodWatch tool execution failed', [
                        'tool' => $toolName,
                        'error' => $e->getMessage(),
                    ]);
                    $result = ['error' => 'Tool execution failed: ' . $e->getMessage()];
                }
                
                if ($toolName === 'GetFloodData' && is_array($result) && ! isset($result['error'])) {
                    $floods = $result;
                }
                if ($toolName === 'GetHighwaysIncidents' && is_array($result) && ! isset($result['error'])) {
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
        $cacheMinutes = config('flood-watch.cache_ttl_minutes', 15);
        if ($cacheMinutes > 0) {
            $store = $this->resolveCacheStore();
            $this->cachePut($store, $cacheKey, $result, now()->addMinutes($cacheMinutes));
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
        } catch (Throwable) {
            return null;
        }
    }

    private function cachePut(string $store, string $key, array $value, \DateTimeInterface|int $ttl): void
    {
        try {
            Cache::store($store)->put($key, $value, $ttl);
        } catch (Throwable) {
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
     * @param  array{floods?: array, incidents?: array, riverLevels?: array, region?: string|null}  $context
     */
    private function executeTool(string $name, string $argumentsJson, array $context = []): array|string
    {
        $args = json_decode($argumentsJson, true) ?? [];

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
                $args['lng'] ?? null,
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
                $args['lng'] ?? null,
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
                } catch (Throwable) {
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
     * This implements a token budget management strategy:
     * 1. First, estimate current token count (crude but fast: bytes/4)
     * 2. If under budget, return messages as-is
     * 3. If over budget, keep system prompt + user message + last assistant+tool exchange
     * 4. If still over budget after trimming, truncate individual tool contents
     * 
     * This ensures we maintain conversation context while avoiding "context length exceeded" errors.
     *
     * @param  array<int, array{role: string, content?: string|null, tool_calls?: array, tool_call_id?: string}>  $messages
     * @return array<int, array{role: string, content?: string|null, tool_calls?: array, tool_call_id?: string}>
     */
    private function trimMessagesToTokenBudget(array $messages): array
    {
        $maxTokens = config('flood-watch.llm_max_context_tokens', 110000);
        // Estimate tokens using character count / 4 (OpenAI's rough estimate: 1 token ≈ 4 chars)
        $estimate = fn (array $m): int => (int) ceil(strlen(json_encode(['messages' => $m])) / 4);
        $estimatedTokens = $estimate($messages);

        if ($estimatedTokens <= $maxTokens) {
            return $messages;
        }

        // Find the last assistant message that made tool calls
        $lastAssistantIndex = null;
        for ($i = count($messages) - 1; $i >= 0; $i--) {
            if (($messages[$i]['role'] ?? '') === 'assistant' && isset($messages[$i]['tool_calls'])) {
                $lastAssistantIndex = $i;
                break;
            }
        }

        // Keep only: system prompt + user message + last assistant+tool exchange
        // This preserves the most recent context while discarding older iterations
        if ($lastAssistantIndex !== null) {
            $messages = [
                $messages[0],  // System prompt
                $messages[1],  // User message
                ...array_slice($messages, $lastAssistantIndex),  // Last assistant + subsequent tool responses
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
     * This is a fallback strategy when keeping only the last assistant+tool block isn't enough.
     * It progressively shortens tool response contents until we're under the token budget.
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

        // Start with 8000 chars max per tool content, reduce by 2000 each iteration
        $maxContentChars = 8000;
        $step = 2000;

        // Iteratively reduce tool content length until we're under budget
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

    private function dispatchRecordLlmRequest(mixed $response, ?int $userId, ?string $region): void
    {
        try {
            $usage = $response->usage;
            $payload = [
                'user_id' => $userId,
                'model' => $response->model ?? null,
                'input_tokens' => $usage?->promptTokens ?? 0,
                'output_tokens' => $usage?->completionTokens ?? 0,
                'openai_id' => $response->id ?? null,
                'region' => $region,
            ];

            \App\Jobs\RecordLlmRequestJob::dispatch($payload);
        } catch (Throwable $e) {
            Log::warning('FloodWatch failed to record LLM request', ['error' => $e->getMessage()]);
        }
    }
}
