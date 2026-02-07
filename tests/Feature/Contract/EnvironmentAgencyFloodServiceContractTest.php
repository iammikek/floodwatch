<?php

namespace Tests\Feature\Contract;

use App\Flood\Services\EnvironmentAgencyFloodService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Contract tests using recorded API response fixtures.
 * Verifies parsing logic matches the Environment Agency API contract.
 */
class EnvironmentAgencyFloodServiceContractTest extends TestCase
{
    public function test_parses_recorded_floods_fixture_correctly(): void
    {
        $floodsFixture = file_get_contents(__DIR__.'/../../fixtures/environment_agency_floods.json');
        $areasFixture = file_get_contents(__DIR__.'/../../fixtures/environment_agency_areas.json');

        Http::fake(function ($request) use ($floodsFixture, $areasFixture) {
            if (str_contains($request->url(), '/id/floods')) {
                return Http::response($floodsFixture, 200, ['Content-Type' => 'application/json']);
            }
            if (str_contains($request->url(), '/id/floodAreas') && ! str_contains($request->url(), '/polygon')) {
                return Http::response($areasFixture, 200, ['Content-Type' => 'application/json']);
            }
            if (str_contains($request->url(), '/polygon')) {
                return Http::response(['type' => 'FeatureCollection', 'features' => []], 200);
            }

            return Http::response(null, 404);
        });

        $service = new EnvironmentAgencyFloodService;
        $result = $service->getFloods(51.0358, -2.8318, 15);

        $this->assertCount(2, $result);

        $this->assertSame('North Moor and Curry Moor', $result[0]['description']);
        $this->assertSame('Flood Warning', $result[0]['severity']);
        $this->assertSame(2, $result[0]['severityLevel']);
        $this->assertSame('123WAC', $result[0]['floodAreaID']);
        $this->assertSame(51.04, $result[0]['lat']);
        $this->assertSame(-2.82, $result[0]['lng']);

        $this->assertSame('River Parrett at Langport', $result[1]['description']);
        $this->assertSame('Severe Flood Warning', $result[1]['severity']);
        $this->assertSame(1, $result[1]['severityLevel']);
        $this->assertSame(51.035, $result[1]['lat']);
        $this->assertSame(-2.831, $result[1]['lng']);
    }
}
