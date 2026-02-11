<?php

namespace App\Services;

use App\Enums\Region;
use App\Enums\ToolName;
use App\Flood\DTOs\FloodWarning;
use App\Flood\Services\EnvironmentAgencyFloodService;
use App\Flood\Services\FloodForecastService;
use App\Flood\Services\RiverLevelService;
use App\Roads\Services\NationalHighwaysService;
use App\Roads\Services\SomersetCouncilRoadworksService;
use App\Support\ConfigKey;
use App\Support\LlmTrim;
use App\Support\LogMasker;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\Log;
use OpenAI\Exceptions\ErrorException as OpenAIErrorException;
use OpenAI\Exceptions\RateLimitException as OpenAIRateLimitException;
use OpenAI\Exceptions\TransporterException as OpenAITransporterException;
use OpenAI\Laravel\Facades\OpenAI;
use Throwable;

class FloodWatchService
{
    public function __construct(
        protected EnvironmentAgencyFloodService $floodService,
        protected NationalHighwaysService $highwaysService,
        protected SomersetCouncilRoadworksService $somersetRoadworksService,
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
     * @param  array<int, array{role: string, content: string}>  $conversation  Previous messages for multi-turn chat (optional). Each message requires 'role' and 'content' keys.
     * @param  string|null  $cacheKey  Custom cache key (optional). If not provided, message hash is used.
     * @param  float|null  $userLat  User's latitude (optional). Defaults to config('flood-watch.default_lat').
     * @param  float|null  $userLng  User's longitude (optional). Defaults to config('flood-watch.default_lng').
     * @param  string|null  $region  User's region (e.g., 'somerset', 'bristol') for region-specific prompt injection (optional).
     * @param  int|null  $userId  User ID for LLM request recording in analytics (optional).
     * @param  callable(string): void|null  $onProgress  Optional callback for progress updates (e.g., for streaming status to UI). Receives progress message strings.
     * @return array{response: string, floods: array, incidents: array, forecast: array, weather: array, riverLevels: array, lastChecked: string, error?: bool, error_key?: string} The LLM response and all collected data
     */
    public function chat(string $userMessage, array $conversation = [], ?string $cacheKey = null, ?float $userLat = null, ?float $userLng = null, ?string $region = null, ?int $userId = null, ?callable $onProgress = null): array
    {
        $emptyResult = function (string $response, ?string $lastChecked = null, ?string $errorKey = null): array {
            $result = [
                'response' => $response,
                'floods' => [],
                'incidents' => [],
                'forecast' => [],
                'weather' => [],
                'riverLevels' => [],
                'lastChecked' => $lastChecked ?? now()->toIso8601String(),
            ];
            if ($errorKey !== null) {
                $result['error'] = true;
                $result['error_key'] = $errorKey;
            }

            return $result;
        };

        if (empty(config('openai.api_key'))) {
            return $emptyResult(__('flood-watch.error.no_api_key'));
        }

        $store = $this->resolveCacheStore();
        $key = $this->cacheKey($userMessage, $cacheKey);
        $cacheEnabled = config(ConfigKey::CACHE_TTL_MINUTES, 15) > 0;
        if ($cacheEnabled) {
            $cached = $this->cacheGet($store, $key);
            if ($cached !== null) {
                return $cached;
            }
        }

        $report = static function (string $status) use ($onProgress): void {
            $onProgress !== null && $onProgress($status);
        };

        $lat = $userLat ?? config(ConfigKey::DEFAULT_LAT);
        $lng = $userLng ?? config(ConfigKey::DEFAULT_LNG);

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
            } catch (OpenAIRateLimitException $e) {
                Log::error('FloodWatch OpenAI rate limit', [
                    'error' => $e->getMessage(),
                    'iteration' => $iteration + 1,
                ]);

                return $emptyResult(__('flood-watch.error.rate_limit'), now()->toIso8601String(), 'rate_limit');
            } catch (OpenAIErrorException $e) {
                Log::error('FloodWatch OpenAI API error', [
                    'error' => $e->getMessage(),
                    'status_code' => $e->getStatusCode(),
                    'iteration' => $iteration + 1,
                ]);

                $status = $e->getStatusCode();
                $messageKey = match ($status) {
                    429 => 'flood-watch.error.rate_limit',
                    408, 504 => 'flood-watch.error.timeout',
                    default => 'flood-watch.error.api_error',
                };
                $errorKey = match ($status) {
                    429 => 'rate_limit',
                    408, 504 => 'timeout',
                    default => 'api_error',
                };

                return $emptyResult(__($messageKey), now()->toIso8601String(), $errorKey);
            } catch (OpenAITransporterException $e) {
                Log::error('FloodWatch OpenAI transport error', [
                    'error' => $e->getMessage(),
                    'iteration' => $iteration + 1,
                ]);

                $msg = $this->userMessageForLlmException($e);
                $errorKey = $this->errorKeyFromMessage($msg);

                return $emptyResult($msg, now()->toIso8601String(), $errorKey);
            } catch (Throwable $e) {
                Log::error('FloodWatch unexpected error during LLM call', [
                    'error' => $e->getMessage(),
                    'iteration' => $iteration + 1,
                ]);

                $msg = $this->userMessageForLlmException($e);
                $errorKey = $this->errorKeyFromMessage($msg);

                return $emptyResult($msg, now()->toIso8601String(), $errorKey);
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
                    ToolName::GetFloodData->value => __('flood-watch.progress.fetching_floods'),
                    ToolName::GetHighwaysIncidents->value => __('flood-watch.progress.checking_roads'),
                    ToolName::GetFloodForecast->value => __('flood-watch.progress.getting_forecast'),
                    ToolName::GetRiverLevels->value => __('flood-watch.progress.fetching_river_levels'),
                    ToolName::GetCorrelationSummary->value => __('flood-watch.progress.correlating'),
                    default => __('flood-watch.progress.loading'),
                });
                $context = [
                    'floods' => $floods,
                    'incidents' => $incidents,
                    'riverLevels' => $riverLevels,
                    'region' => $region,
                    'centerLat' => $lat,
                    'centerLng' => $lng,
                ];

                try {
                    $result = $this->executeTool($toolName, $toolCall->function->arguments, $context);
                } catch (Throwable $e) {
                    Log::warning('FloodWatch tool execution failed', [
                        'tool' => $toolName,
                        'error' => $e->getMessage(),
                    ]);
                    $result = ['error' => __('flood-watch.error.tool_failed'), 'code' => 'tool_error'];
                }

                if ($toolName === ToolName::GetFloodData->value && is_array($result) && ! isset($result['error'])) {
                    $floods = $result;
                }
                if ($toolName === ToolName::GetHighwaysIncidents->value && is_array($result) && ! isset($result['error'])) {
                    $incidents = $result;
                }
                if ($toolName === ToolName::GetFloodForecast->value && is_array($result) && ! isset($result['error'])) {
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
        $prefix = config(ConfigKey::CACHE_KEY_PREFIX, 'flood-watch');

        if ($cacheKey !== null && $cacheKey !== '') {
            return "{$prefix}:chat:".md5($cacheKey);
        }

        return "{$prefix}:chat:".md5($userMessage);
    }

    /**
     * Map transport/generic LLM exceptions to user-facing messages.
     * Prefer rate_limit, timeout, or connection when the exception message indicates it.
     */
    private function userMessageForLlmException(Throwable $e): string
    {
        $msg = strtolower($e->getMessage());

        if (str_contains($msg, 'rate limit') || str_contains($msg, '429')) {
            return __('flood-watch.error.rate_limit');
        }
        if (str_contains($msg, 'timed out') || str_contains($msg, 'timeout')) {
            return __('flood-watch.error.timeout');
        }
        if (str_contains($msg, 'connection') || str_contains($msg, 'resolve host') || str_contains($msg, 'connection refused')) {
            return __('flood-watch.error.connection');
        }

        return __('flood-watch.error.unexpected');
    }

    /**
     * Return error_key for the given user-facing message (for dashboard error/retry state).
     */
    private function errorKeyFromMessage(string $message): string
    {
        if ($message === __('flood-watch.error.rate_limit')) {
            return 'rate_limit';
        }
        if ($message === __('flood-watch.error.timeout')) {
            return 'timeout';
        }
        if ($message === __('flood-watch.error.connection')) {
            return 'connection';
        }

        return 'unexpected';
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

        if ($name === ToolName::GetCorrelationSummary->value) {
            $assessment = $this->correlationService->correlate(
                $context['floods'] ?? [],
                $context['incidents'] ?? [],
                $context['riverLevels'] ?? [],
                $context['region'] ?? null
            );

            return $assessment->toArray();
        }

        return match ($name) {
            ToolName::GetFloodData->value => $this->floodService->getFloods(
                $args['lat'] ?? null,
                $args['lng'] ?? null,
                $args['radius_km'] ?? null
            ),
            ToolName::GetHighwaysIncidents->value => $this->sortIncidentsByPriority(
                $this->filterMotorwaysFromDisplay(
                    $this->filterIncidentsByProximity(
                        $this->filterIncidentsByRegion(
                            $this->mergedHighwaysIncidents($context['region'] ?? null),
                            $context['region'] ?? null
                        ),
                        $context['centerLat'] ?? null,
                        $context['centerLng'] ?? null
                    )
                )
            ),
            ToolName::GetFloodForecast->value => $this->forecastService->getForecast(),
            ToolName::GetRiverLevels->value => $this->riverLevelService->getLevels(
                $args['lat'] ?? null,
                $args['lng'] ?? null,
                $args['radius_km'] ?? null
            ),
            default => ['error' => "Unknown tool: {$name}"],
        };
    }

    private function prepareToolResultForLlm(string $toolName, array|string $result): array|string
    {
        if (is_string($result)) {
            return $result;
        }

        if (isset($result['error'])) {
            return $result;
        }

        if ($toolName === ToolName::GetFloodData->value) {
            $max = (int) config(ConfigKey::LLM_MAX_FLOODS, 25);
            $maxMsg = (int) config(ConfigKey::LLM_MAX_FLOOD_MESSAGE_CHARS, 300);

            return LlmTrim::trimList($result, $max, function ($flood) use ($maxMsg) {
                $arr = FloodWarning::fromArray($flood)->withoutPolygon()->toArray();
                if (isset($arr['message'])) {
                    $arr['message'] = LlmTrim::truncate($arr['message'], $maxMsg);
                }

                return $arr;
            });
        }

        if ($toolName === ToolName::GetHighwaysIncidents->value) {
            return LlmTrim::limitItems($result, (int) config(ConfigKey::LLM_MAX_INCIDENTS, 25));
        }

        if ($toolName === ToolName::GetRiverLevels->value) {
            return LlmTrim::limitItems($result, (int) config(ConfigKey::LLM_MAX_RIVER_LEVELS, 15));
        }

        if ($toolName === ToolName::GetFloodForecast->value) {
            if (isset($result['england_forecast'])) {
                $result['england_forecast'] = LlmTrim::truncate(
                    $result['england_forecast'],
                    (int) config(ConfigKey::LLM_MAX_FORECAST_CHARS, 1200)
                );
            }

            $maxExtraChars = 800;
            foreach (['flood_risk_trend', 'sources'] as $key) {
                if (isset($result[$key]) && is_array($result[$key])) {
                    if (strlen(json_encode($result[$key])) > $maxExtraChars) {
                        $result[$key] = LlmTrim::limitItems($result[$key], 3);
                    }
                }
            }

            return $result;
        }

        if ($toolName === ToolName::GetCorrelationSummary->value) {
            $maxFloods = (int) config(ConfigKey::LLM_MAX_FLOODS, 12);
            $maxIncidents = (int) config(ConfigKey::LLM_MAX_INCIDENTS, 12);
            $maxMsgChars = (int) config(ConfigKey::LLM_MAX_FLOOD_MESSAGE_CHARS, 150);
            $maxTotalChars = (int) config(ConfigKey::LLM_MAX_CORRELATION_CHARS, 8000);

            $stripFlood = function (array $f) use ($maxMsgChars): array {
                try {
                    $arr = FloodWarning::fromArray($f)->withoutPolygon()->toArray();
                } catch (Throwable) {
                    return [
                        'description' => $f['description'] ?? '',
                        'severity' => $f['severity'] ?? '',
                        'message' => LlmTrim::truncate((string) ($f['message'] ?? ''), $maxMsgChars),
                    ];
                }

                if (isset($arr['message'])) {
                    $arr['message'] = LlmTrim::truncate($arr['message'], $maxMsgChars);
                }

                return $arr;
            };

            $result['severe_floods'] = LlmTrim::trimList($result['severe_floods'] ?? [], $maxFloods, $stripFlood);
            $result['flood_warnings'] = LlmTrim::trimList($result['flood_warnings'] ?? [], $maxFloods, $stripFlood);
            $result['road_incidents'] = LlmTrim::limitItems($result['road_incidents'] ?? [], $maxIncidents);
            $result['cross_references'] = LlmTrim::limitItems($result['cross_references'] ?? [], 15);
            $result['predictive_warnings'] = LlmTrim::limitItems($result['predictive_warnings'] ?? [], 10);

            while (strlen(json_encode($result)) > $maxTotalChars && ($maxFloods > 2 || $maxIncidents > 2)) {
                if ($maxFloods > 2) {
                    $maxFloods--;
                    $result['severe_floods'] = LlmTrim::limitItems($result['severe_floods'], $maxFloods);
                    $result['flood_warnings'] = LlmTrim::limitItems($result['flood_warnings'], $maxFloods);
                }
                if (strlen(json_encode($result)) > $maxTotalChars && $maxIncidents > 2) {
                    $maxIncidents--;
                    $result['road_incidents'] = LlmTrim::limitItems($result['road_incidents'], $maxIncidents);
                }
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
        // Crude token estimation: bytes / 4 (OpenAI's rule: ~1 token per 4 chars for English)
        // Note: strlen() counts bytes, which approximates character count for ASCII/English.
        // For multi-byte UTF-8, this estimate is less accurate but conservative (overestimates).
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

        // Keep only: system prompt + most recent user message + last assistant+tool exchange
        // buildMessages() can prepend conversation history, so index 1 is not always the current user
        // message—find the last role === 'user' before the last assistant/tool block.
        if ($lastAssistantIndex !== null && $lastAssistantIndex >= 2) {
            $lastUserIndex = null;
            for ($i = $lastAssistantIndex - 1; $i >= 0; $i--) {
                if (($messages[$i]['role'] ?? '') === 'user') {
                    $lastUserIndex = $i;
                    break;
                }
            }
            $userMessage = $lastUserIndex !== null ? $messages[$lastUserIndex] : $messages[1];

            $messages = [
                $messages[0],
                $userMessage,
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
     * Merged incidents from cache: National Highways + Somerset Council (when region is Somerset).
     *
     * @return array<int, array{road?: string, status?: string, incidentType?: string, delayTime?: string}>
     */
    private function mergedHighwaysIncidents(?string $region): array
    {
        $incidents = $this->highwaysService->getIncidents();

        if ($region === Region::Somerset->value) {
            $somerset = $this->somersetRoadworksService->getIncidents();
            $incidents = array_merge($incidents, $somerset);
        }

        return $incidents;
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
            $aClosed = ($a['managementType'] ?? $a['incidentType'] ?? '') === 'roadClosed';
            $bClosed = ($b['managementType'] ?? $b['incidentType'] ?? '') === 'roadClosed';
            if ($aClosed !== $bClosed) {
                return $aClosed ? -1 : 1;
            }

            return 0;
        });

        return array_values($incidents);
    }

    /**
     * Filter out motorway incidents when config excludes them (hyperlocal focus).
     *
     * @param  array<int, array{road?: string}>  $incidents
     * @return array<int, array{road?: string}>
     */
    private function filterMotorwaysFromDisplay(array $incidents): array
    {
        if (! config('flood-watch.exclude_motorways_from_display', true)) {
            return $incidents;
        }

        return array_values(array_filter($incidents, function (array $incident): bool {
            $road = trim((string) ($incident['road'] ?? ''));
            if ($road === '') {
                return true;
            }
            $baseRoad = $this->extractBaseRoad($road);

            return ! preg_match('/^M\d+/', $baseRoad);
        }));
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
     * Filter incidents to those within radius of the search location so the AI summary only mentions nearby incidents.
     *
     * @param  array<int, array<string, mixed>>  $incidents
     * @return array<int, array<string, mixed>>
     */
    private function filterIncidentsByProximity(array $incidents, ?float $centerLat, ?float $centerLng): array
    {
        $radiusKm = config('flood-watch.incident_summary_proximity_km', 80);
        if ($radiusKm <= 0 || $centerLat === null || $centerLng === null) {
            return $incidents;
        }

        return array_values(array_filter($incidents, function (array $incident) use ($centerLat, $centerLng, $radiusKm): bool {
            $coords = CoordinateMapper::normalize($incident);
            $lat = $coords['lat'] ?? null;
            $lng = $coords['lng'] ?? null;
            if ($lat === null || $lng === null) {
                return false;
            }

            return $this->haversineKm($centerLat, $centerLng, (float) $lat, (float) $lng) <= $radiusKm;
        }));
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusKm = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusKm * $c;
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
