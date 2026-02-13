<?php

namespace App\Services;

use App\Enums\ToolName;
use App\Flood\Services\EnvironmentAgencyFloodService;
use App\Flood\Services\FloodForecastService;
use App\Flood\Services\RiverLevelService;
use App\Roads\Services\RoadIncidentOrchestrator;
use App\Support\ConfigKey;
use App\Support\LogMasker;
use App\Support\Tooling\TokenBudget;
use App\Support\Tooling\ToolRegistry;
use App\Support\Tooling\ToolResult;
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
        protected RoadIncidentOrchestrator $roadIncidentOrchestrator,
        protected FloodForecastService $forecastService,
        protected WeatherService $weatherService,
        protected RiverLevelService $riverLevelService,
        protected RiskCorrelationService $correlationService,
        protected FloodWatchPromptBuilder $promptBuilder,
        protected ?ToolRegistry $toolRegistry = null,
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
     * @return array{response: string, floods: array, incidents: array, forecast: array, weather: array, riverLevels: array, lastChecked: string, getError?: bool, error_key?: string} The LLM response and all collected data
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
                $result['getError'] = true;
                $result['error_key'] = $errorKey;
            }

            return $result;
        };

        if (empty(config('openai.api_key'))) {
            return $emptyResult(__('flood-watch.getError.no_api_key'));
        }

        $store = $this->resolveCacheStore();
        $key = $this->cacheKey($userMessage, $cacheKey);
        $cacheEnabled = config(ConfigKey::CACHE_TTL_MINUTES, 15) > 0;
        if ($cacheEnabled) {
            $cached = $this->cacheGet($store, $key);
            if ($cached !== null) {
                Log::debug('FloodWatch cache hit', [
                    'provider' => 'flood-watch',
                    'store' => $store,
                    'key' => $key,
                    'region' => $region,
                ]);

                return $cached;
            }
            Log::debug('FloodWatch cache miss', [
                'provider' => 'flood-watch',
                'store' => $store,
                'key' => $key,
                'region' => $region,
            ]);
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
        ], 120);

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
                'provider' => 'openai',
                'size_bytes' => $payloadBytes,
                'estimated_tokens' => $estimatedTokens,
                'message_count' => count($messages),
                'iteration' => $iteration + 1,
                'region' => $region,
                'lat' => $lat,
                'lng' => $lng,
            ]);
            Log::debug('FloodWatch OpenAI payload content', [
                'provider' => 'openai',
                'payload' => LogMasker::maskOpenAiPayload($payload),
            ]);

            try {
                $response = OpenAI::chat()->create($payload);
            } catch (OpenAIRateLimitException $e) {
                Log::error('FloodWatch OpenAI rate limit', [
                    'provider' => 'openai',
                    'getError' => $e->getMessage(),
                    'iteration' => $iteration + 1,
                    'region' => $region,
                ]);

                return $emptyResult(__('flood-watch.getError.rate_limit'), now()->toIso8601String(), 'rate_limit');
            } catch (OpenAIErrorException $e) {
                Log::error('FloodWatch OpenAI API getError', [
                    'provider' => 'openai',
                    'getError' => $e->getMessage(),
                    'status_code' => $e->getStatusCode(),
                    'iteration' => $iteration + 1,
                    'region' => $region,
                ]);

                $status = $e->getStatusCode();
                $messageKey = match ($status) {
                    429 => 'flood-watch.getError.rate_limit',
                    408, 504 => 'flood-watch.getError.timeout',
                    default => 'flood-watch.getError.api_error',
                };
                $errorKey = match ($status) {
                    429 => 'rate_limit',
                    408, 504 => 'timeout',
                    default => 'api_error',
                };

                return $emptyResult(__($messageKey), now()->toIso8601String(), $errorKey);
            } catch (OpenAITransporterException $e) {
                Log::error('FloodWatch OpenAI transport getError', [
                    'provider' => 'openai',
                    'getError' => $e->getMessage(),
                    'iteration' => $iteration + 1,
                    'region' => $region,
                ]);

                $msg = $this->userMessageForLlmException($e);
                $errorKey = $this->errorKeyFromMessage($msg);

                return $emptyResult($msg, now()->toIso8601String(), $errorKey);
            } catch (Throwable $e) {
                Log::error('FloodWatch unexpected getError during LLM call', [
                    'provider' => 'openai',
                    'getError' => $e->getMessage(),
                    'iteration' => $iteration + 1,
                    'region' => $region,
                ]);

                $msg = $this->userMessageForLlmException($e);
                $errorKey = $this->errorKeyFromMessage($msg);

                return $emptyResult($msg, now()->toIso8601String(), $errorKey);
            }

            $this->dispatchRecordLlmRequest($response, $userId, $region);

            $choice = $response->choices[0] ?? null;
            if (! $choice) {
                return $emptyResult(__('flood-watch.getError.no_response'), now()->toIso8601String());
            }

            $message = $choice->message;
            $finishReason = $choice->finishReason ?? '';

            if ($finishReason === 'stop' || $finishReason === 'end_turn') {
                $report(__('flood-watch.progress.preparing_summary'));

                return $this->storeAndReturn($key, [
                    'response' => trim($message->content ?? __('flood-watch.getError.no_content')),
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
                    'response' => trim($message->content ?? __('flood-watch.getError.no_content')),
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
                        'provider' => 'tooling',
                        'region' => $region,
                        'lat' => $lat,
                        'lng' => $lng,
                        'getError' => $e->getMessage(),
                    ]);
                    $result = ['getError' => __('flood-watch.getError.tool_failed'), 'code' => 'tool_error'];
                }

                if ($toolName === ToolName::GetFloodData->value && is_array($result) && ! isset($result['getError'])) {
                    $floods = $result;
                }
                if ($toolName === ToolName::GetHighwaysIncidents->value && is_array($result) && ! isset($result['getError'])) {
                    $incidents = $result;
                }
                if ($toolName === ToolName::GetFloodForecast->value && is_array($result) && ! isset($result['getError'])) {
                    $forecast = $result;
                }

                $contentForLlm = $this->prepareToolResultForLlm($toolName, $result);
                $content = is_string($contentForLlm) ? $contentForLlm : json_encode($contentForLlm);
                $contentBytes = strlen($content);
                Log::info('FloodWatch tool result for LLM', [
                    'tool' => $toolName,
                    'size_bytes' => $contentBytes,
                    'estimated_tokens' => (int) ceil($contentBytes / 4),
                    'region' => $region,
                    'lat' => $lat,
                    'lng' => $lng,
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

        return $emptyResult(__('flood-watch.getError.max_tool_calls'), now()->toIso8601String());
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
            return __('flood-watch.getError.rate_limit');
        }
        if (str_contains($msg, 'timed out') || str_contains($msg, 'timeout')) {
            return __('flood-watch.getError.timeout');
        }
        if (str_contains($msg, 'connection') || str_contains($msg, 'resolve host') || str_contains($msg, 'connection refused')) {
            return __('flood-watch.getError.connection');
        }

        return __('flood-watch.getError.unexpected');
    }

    /**
     * Return error_key for the given user-facing message (for dashboard getError/retry state).
     */
    private function errorKeyFromMessage(string $message): string
    {
        if ($message === __('flood-watch.getError.rate_limit')) {
            return 'rate_limit';
        }
        if ($message === __('flood-watch.getError.timeout')) {
            return 'timeout';
        }
        if ($message === __('flood-watch.getError.connection')) {
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
        if ($this->toolRegistry === null) {
            return ['getError' => 'Tool registry not available'];
        }

        try {
            $handler = $this->toolRegistry->get(ToolName::from($name));
            $args = \App\Support\Tooling\ToolArguments::fromJson($argumentsJson);
            $ctx = \App\Support\Tooling\ToolContext::fromArray($context);
            $result = $handler->execute($args, $ctx);

            return $result->isOk() ? ($result->data() ?? []) : ['getError' => $result->getError() ?? __('flood-watch.getError.tool_execution', ['name' => $name])];
        } catch (\Throwable $e) {
            return ['getError' => __('flood-watch.getError.tool_execution', ['name' => $name])];
        }
    }

    private function prepareToolResultForLlm(string $toolName, array|string $result): array|string
    {
        if ($this->toolRegistry !== null) {
            try {
                $handler = $this->toolRegistry->get(ToolName::from($toolName));
                $toolResult = is_array($result)
                    ? (isset($result['getError']) ? ToolResult::error((string) ($result['getError'])) : ToolResult::ok($result))
                    : ToolResult::ok($result);

                return $handler->presentForLlm($toolResult, new TokenBudget(0));
            } catch (\Throwable) {
                // Fall back to passthrough if presenter fails
            }
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
            Log::warning('FloodWatch failed to record LLM request', ['getError' => $e->getMessage()]);
        }
    }
}
