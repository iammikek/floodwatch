<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use OpenAI\Laravel\Facades\OpenAI;

class SomersetAssistantService
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
You are the Somerset Emergency Assistant for the Somerset Levels (Sedgemoor and South Somerset). Your role is to correlate Environment Agency flood data with National Highways road status to provide a Single Source of Truth for flood and road viability.

**Data Correlation**: If GetFloodData shows a warning for North Moor or King's Sedgemoor, immediately cross-reference GetHighwaysIncidents for the A361 at East Lyng.

**Contextual Awareness**: Muchelney is prone to being cut off. If River Parrett levels are rising, warn users about access to Muchelney even if the Highways API has not updated (predictive warning).

**Prioritization**: Prioritize "Danger to Life" alerts, then road closures, then general flood alerts.

**Output Format**: Always structure responses with:
- A "Current Status" section
- An "Action Steps" bulleted list

Only report flood warnings and road incidents that are present in the tool results. Never invent or hallucinate data.
PROMPT;

    public function __construct(
        protected EnvironmentAgencyFloodService $floodService,
        protected NationalHighwaysService $highwaysService
    ) {}

    /**
     * Send a user message to the Somerset Assistant and return the synthesized response with flood and road data.
     * Results are cached to avoid hammering the APIs. Use $cacheKey to scope the cache (e.g. postcode).
     *
     * @param  array<int, array{role: string, content: string}>  $conversation  Previous messages (optional)
     * @return array{response: string, floods: array, incidents: array, lastChecked: string}
     */
    public function chat(string $userMessage, array $conversation = [], ?string $cacheKey = null): array
    {
        $emptyResult = fn (string $response, ?string $lastChecked = null): array => [
            'response' => $response,
            'floods' => [],
            'incidents' => [],
            'lastChecked' => $lastChecked ?? now()->toIso8601String(),
        ];

        if (empty(config('openai.api_key'))) {
            return $emptyResult('Flood Watch is not configured with an OpenAI API key. Please add OPENAI_API_KEY to your environment.');
        }

        $store = config('flood-watch.cache_store', 'flood-watch');
        $key = $this->cacheKey($userMessage, $cacheKey);
        $cached = Cache::store($store)->get($key);
        if ($cached !== null) {
            return $cached;
        }

        $messages = $this->buildMessages($userMessage, $conversation);
        $tools = $this->getToolDefinitions();
        $maxIterations = 5;
        $iteration = 0;
        $floods = [];
        $incidents = [];

        while ($iteration < $maxIterations) {
            $response = OpenAI::chat()->create([
                'model' => config('openai.model', 'gpt-4o-mini'),
                'messages' => $messages,
                'tools' => $tools,
                'tool_choice' => 'auto',
            ]);

            $choice = $response->choices[0] ?? null;
            if (! $choice) {
                return $emptyResult('Unable to get a response from the assistant.', now()->toIso8601String());
            }

            $message = $choice->message;
            $finishReason = $choice->finishReason ?? '';

            if ($finishReason === 'stop' || $finishReason === 'end_turn') {
                return $this->storeAndReturn($key, [
                    'response' => trim($message->content ?? 'No response generated.'),
                    'floods' => $floods,
                    'incidents' => $incidents,
                ]);
            }

            if (empty($message->toolCalls)) {
                return $this->storeAndReturn($key, [
                    'response' => trim($message->content ?? 'No response generated.'),
                    'floods' => $floods,
                    'incidents' => $incidents,
                ]);
            }

            $messages[] = [
                'role' => 'assistant',
                'content' => $message->content ?? null,
                'tool_calls' => array_map(fn ($tc) => $tc->toArray(), $message->toolCalls),
            ];

            foreach ($message->toolCalls as $toolCall) {
                $result = $this->executeTool($toolCall->function->name, $toolCall->function->arguments);
                if ($toolCall->function->name === 'GetFloodData' && is_array($result)) {
                    $floods = $result;
                }
                if ($toolCall->function->name === 'GetHighwaysIncidents' && is_array($result)) {
                    $incidents = $result;
                }
                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $toolCall->id,
                    'content' => is_string($result) ? $result : json_encode($result),
                ];
            }

            $iteration++;
        }

        return $emptyResult('The assistant reached the maximum number of tool calls. Please try again.', now()->toIso8601String());
    }

    private function cacheKey(string $userMessage, ?string $cacheKey): string
    {
        if ($cacheKey !== null && $cacheKey !== '') {
            return 'flood-watch:'.md5($cacheKey);
        }

        return 'flood-watch:'.md5($userMessage);
    }

    /**
     * @param  array{response: string, floods: array, incidents: array}  $result
     * @return array{response: string, floods: array, incidents: array, lastChecked: string}
     */
    private function storeAndReturn(string $cacheKey, array $result): array
    {
        $store = config('flood-watch.cache_store', 'flood-watch');
        $ttl = config('flood-watch.cache_ttl_minutes', 15) * 60;
        $result['lastChecked'] = now()->toIso8601String();
        Cache::store($store)->put($cacheKey, $result, $ttl);

        return $result;
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $conversation
     * @return array<int, array{role: string, content: string|null, tool_calls?: array}>
     */
    private function buildMessages(string $userMessage, array $conversation): array
    {
        $messages = [
            ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
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
                    'description' => 'Fetch current flood warnings from the Environment Agency for the Somerset Levels (River Parrett, River Tone, Langport, Muchelney, Burrowbridge). Use default coordinates (Langport 51.0358, -2.8318) unless the user specifies a postcode or location.',
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
                    'description' => 'Fetch road and lane closure incidents from National Highways for Somerset Levels routes: A361, A372, M5 J23–J25. Returns status, delay time, and incident type.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => (object) [],
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
            default => ['error' => "Unknown tool: {$name}"],
        };
    }
}
