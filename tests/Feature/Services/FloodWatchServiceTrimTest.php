<?php

namespace Tests\Feature\Services;

use App\Enums\ToolName;
use App\Services\FloodWatchService;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class FloodWatchServiceTrimTest extends TestCase
{
    private function callPrivate(object $object, string $method, array $args = [])
    {
        $ref = new \ReflectionClass($object);
        $m = $ref->getMethod($method);
        $m->setAccessible(true);

        return $m->invokeArgs($object, $args);
    }

    public function test_prepare_tool_result_trims_floods_and_message_chars(): void
    {
        Config::set('flood-watch.llm_max_floods', 3);
        Config::set('flood-watch.llm_max_flood_message_chars', 10);

        $longMessage = str_repeat('A', 50);
        $result = [];
        for ($i = 0; $i < 10; $i++) {
            $result[] = [
                'description' => "Flood $i",
                'severity' => 'Flood alert',
                'severityLevel' => 3,
                'message' => $longMessage,
                'floodAreaID' => 'X'.$i,
                'lat' => 51.0,
                'lng' => -2.8,
            ];
        }

        $service = app(FloodWatchService::class);
        $trimmed = $this->callPrivate($service, 'prepareToolResultForLlm', [ToolName::GetFloodData->value, $result]);

        $this->assertIsArray($trimmed);
        $this->assertCount(3, $trimmed, 'Flood list should be capped to llm_max_floods');
        $this->assertArrayHasKey('message', $trimmed[0]);
        $this->assertLessThanOrEqual(10 + 1, strlen($trimmed[0]['message']), 'Message should be truncated with ellipsis (<= max + 1)');
        $this->assertArrayNotHasKey('polygon', $trimmed[0], 'Polygon should never be present in LLM payload');
    }

    public function test_prepare_tool_result_limits_incidents_and_river_levels(): void
    {
        Config::set('flood-watch.llm_max_incidents', 2);
        Config::set('flood-watch.llm_max_river_levels', 2);

        $incidents = [];
        for ($i = 0; $i < 5; $i++) {
            $incidents[] = ['road' => 'A361', 'status' => 'active', 'incidentType' => 'laneClosures'];
        }
        $levels = [];
        for ($i = 0; $i < 5; $i++) {
            $levels[] = ['station' => 'S'.$i, 'river' => 'R'.$i, 'town' => 'T'.$i, 'value' => 1.0, 'unit' => 'm', 'unitName' => 'metres', 'dateTime' => '2026-02-04T12:00:00Z'];
        }

        $service = app(FloodWatchService::class);
        $trimInc = $this->callPrivate($service, 'prepareToolResultForLlm', [ToolName::GetHighwaysIncidents->value, $incidents]);
        $trimLev = $this->callPrivate($service, 'prepareToolResultForLlm', [ToolName::GetRiverLevels->value, $levels]);

        $this->assertCount(2, $trimInc, 'Incidents should be capped to llm_max_incidents');
        $this->assertCount(2, $trimLev, 'River levels should be capped to llm_max_river_levels');
    }
}
