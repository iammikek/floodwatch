<?php

namespace Tests\Feature\Services;

use App\Services\EnvironmentAgencyFloodService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EnvironmentAgencyFloodServiceTest extends TestCase
{
    public function test_fetches_floods_for_default_coordinates(): void
    {
        Http::fake([
            '*/flood-monitoring/id/floods*' => Http::response([
                'items' => [
                    [
                        'description' => 'River Parrett',
                        'severity' => 'Flood alert',
                        'severityLevel' => 3,
                        'message' => 'Flooding is possible.',
                    ],
                ],
            ], 200),
        ]);

        $service = new EnvironmentAgencyFloodService;
        $result = $service->getFloods();

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame('River Parrett', $result[0]['description']);
        $this->assertSame('Flood alert', $result[0]['severity']);
        $this->assertSame(3, $result[0]['severityLevel']);
    }

    public function test_fetches_floods_for_custom_coordinates(): void
    {
        Http::fake([
            '*/flood-monitoring/id/floods*' => Http::response([
                'items' => [],
            ], 200),
        ]);

        $service = new EnvironmentAgencyFloodService;
        $result = $service->getFloods(51.5, -2.5, 10);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'lat=51.5')
                && str_contains($request->url(), 'long=-2.5')
                && str_contains($request->url(), 'dist=10');
        });

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_returns_empty_array_on_api_failure(): void
    {
        Http::fake([
            '*/flood-monitoring/id/floods*' => Http::response(null, 500),
        ]);

        $service = new EnvironmentAgencyFloodService;
        $result = $service->getFloods();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
