<?php

namespace Tests\Feature\Services;

use App\Services\FloodWatchService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FloodWatchServiceTest extends TestCase
{
    public function test_fetches_flood_and_road_data_in_parallel(): void
    {
        Config::set('flood-watch.national_highways.api_key', 'test-key');
        Config::set('flood-watch.national_highways.base_url', 'https://api.example.com');

        Http::fake(function ($request) {
            if (str_contains($request->url(), 'environment.data.gov.uk')) {
                return Http::response([
                    'items' => [
                        [
                            'description' => 'River Parrett',
                            'severity' => 'Flood alert',
                            'severityLevel' => 3,
                            'message' => 'Flooding possible.',
                        ],
                    ],
                ], 200);
            }
            if (str_contains($request->url(), 'api.example.com')) {
                return Http::response([
                    'closure' => [
                        'closure' => [
                            [
                                'road' => 'A361',
                                'status' => 'closed',
                                'incidentType' => 'flooding',
                            ],
                        ],
                    ],
                ], 200);
            }

            return Http::response(null, 404);
        });

        $service = app(FloodWatchService::class);
        $result = $service->getFloodAndRoadData();

        $this->assertArrayHasKey('floods', $result);
        $this->assertArrayHasKey('incidents', $result);
        $this->assertCount(1, $result['floods']);
        $this->assertSame('River Parrett', $result['floods'][0]['description']);
        $this->assertCount(1, $result['incidents']);
        $this->assertSame('A361', $result['incidents'][0]['road']);
    }
}
