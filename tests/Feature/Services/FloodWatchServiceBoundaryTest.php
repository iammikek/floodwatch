<?php

namespace Tests\Feature\Services;

use App\Enums\ToolName;
use App\Flood\Services\EnvironmentAgencyFloodService;
use App\Flood\Services\FloodForecastService;
use App\Flood\Services\RiverLevelService;
use App\Roads\Services\RoadIncidentOrchestrator;
use App\Services\FloodWatchPromptBuilder;
use App\Services\FloodWatchService;
use App\Services\RiskCorrelationService;
use App\Services\WeatherService;
use App\Support\ConfigKey;
use Illuminate\Support\Facades\Config;
use ReflectionClass;
use Tests\TestCase;

class FloodWatchServiceBoundaryTest extends TestCase
{
    protected FloodWatchService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new FloodWatchService(
            $this->createMock(EnvironmentAgencyFloodService::class),
            $this->createMock(RoadIncidentOrchestrator::class),
            $this->createMock(FloodForecastService::class),
            $this->createMock(WeatherService::class),
            $this->createMock(RiverLevelService::class),
            new RiskCorrelationService,
            $this->createMock(FloodWatchPromptBuilder::class)
        );
    }

    /**
     * Test truncation of flood messages and item count limits.
     */
    public function test_prepare_tool_result_for_llm_flood_data_boundaries(): void
    {
        Config::set(ConfigKey::LLM_MAX_FLOODS, 2);
        Config::set(ConfigKey::LLM_MAX_FLOOD_MESSAGE_CHARS, 10);

        $input = [
            ['description' => 'Flood 1', 'severity' => 'Alert', 'message' => 'This is a long message'],
            ['description' => 'Flood 2', 'severity' => 'Warning', 'message' => 'Short'],
            ['description' => 'Flood 3', 'severity' => 'Severe', 'message' => 'Ignored'],
        ];

        $result = $this->callPrivateMethod('prepareToolResultForLlm', [ToolName::GetFloodData->value, $input]);

        $this->assertCount(2, $result);
        $this->assertSame('This is a …', $result[0]['message']);
        $this->assertSame('Short', $result[1]['message']);
    }

    /**
     * Test truncation of flood forecast narrative.
     */
    public function test_prepare_tool_result_for_llm_forecast_boundaries(): void
    {
        Config::set(ConfigKey::LLM_MAX_FORECAST_CHARS, 10);

        $input = [
            'england_forecast' => 'Very long narrative that should be cut off',
            'flood_risk_trend' => [],
        ];

        $result = $this->callPrivateMethod('prepareToolResultForLlm', [ToolName::GetFloodForecast->value, $input]);

        $this->assertSame('Very long …', $result['england_forecast']);
    }

    /**
     * Test correlation block total size budget (iterative reduction).
     */
    public function test_prepare_tool_result_for_llm_correlation_budget_boundaries(): void
    {
        Config::set(ConfigKey::LLM_MAX_CORRELATION_CHARS, 200); // Very small budget

        $input = [
            'severe_floods' => array_fill(0, 10, ['description' => 'Severe flood']),
            'flood_warnings' => array_fill(0, 10, ['description' => 'Flood warning']),
            'road_incidents' => array_fill(0, 10, ['road' => 'A361']),
        ];

        $result = $this->callPrivateMethod('prepareToolResultForLlm', [ToolName::GetCorrelationSummary->value, $input]);

        $json = json_encode($result);
        $this->assertLessThanOrEqual(200, strlen($json));
        // Verify it didn't just empty everything, but reduced counts
        $this->assertCount(2, $result['severe_floods']);
        $this->assertCount(2, $result['road_incidents']);
    }

    /**
     * Test token budget trimming keeps system + user + last assistant exchange.
     */
    public function test_trim_messages_to_token_budget_keeps_recent_context(): void
    {
        Config::set('flood-watch.llm_max_context_tokens', 50); // Very small budget

        $messages = [
            ['role' => 'system', 'content' => 'System prompt'],
            ['role' => 'user', 'content' => 'Old message'],
            ['role' => 'assistant', 'content' => 'Old response'],
            ['role' => 'user', 'content' => 'Latest query'],
            ['role' => 'assistant', 'content' => null, 'tool_calls' => [['id' => 'c1', 'function' => ['name' => 'f1']]]],
            ['role' => 'tool', 'tool_call_id' => 'c1', 'content' => 'Massive tool result that exceeds the 50 token budget easily...'],
        ];

        $result = $this->callPrivateMethod('trimMessagesToTokenBudget', [$messages]);

        // Should keep system [0], latest user [3], and last assistant+tool block [4, 5]
        $this->assertCount(4, $result);
        $this->assertSame('system', $result[0]['role']);
        $this->assertSame('Latest query', $result[1]['content']);
        $this->assertSame('assistant', $result[2]['role']);
        $this->assertSame('tool', $result[3]['role']);
    }

    protected function callPrivateMethod(string $name, array $args)
    {
        $reflection = new ReflectionClass(FloodWatchService::class);
        $method = $reflection->getMethod($name);
        $method->setAccessible(true);

        return $method->invokeArgs($this->service, $args);
    }
}
