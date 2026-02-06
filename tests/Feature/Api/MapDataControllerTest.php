<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MapDataControllerTest extends TestCase
{
    public function test_map_data_returns_json_api_document_with_floods_incidents_river_levels(): void
    {
        Config::set('flood-watch.national_highways.api_key', 'test-key');
        Config::set('flood-watch.national_highways.base_url', 'https://api.example.com');
        Config::set('flood-watch.national_highways.fetch_unplanned', false);

        Http::fake(function ($request) {
            if (str_contains($request->url(), 'environment.data.gov.uk')) {
                if (str_contains($request->url(), '/id/stations')) {
                    return Http::response(['items' => []], 200);
                }
                if (str_contains($request->url(), '/id/floodAreas')) {
                    return Http::response(['items' => []], 200);
                }
                if (str_contains($request->url(), '/id/floods')) {
                    return Http::response(['items' => []], 200);
                }
            }
            if (str_contains($request->url(), 'api.example.com')) {
                return Http::response(['D2Payload' => ['situation' => []]], 200);
            }
            if (str_contains($request->url(), 'fgs.metoffice.gov.uk')) {
                return Http::response(['statement' => []], 200);
            }
            if (str_contains($request->url(), 'open-meteo.com')) {
                return Http::response(['daily' => ['time' => [], 'weathercode' => [], 'temperature_2m_max' => [], 'temperature_2m_min' => [], 'precipitation_sum' => []]], 200);
            }

            return Http::response(null, 404);
        });

        $response = $this->getJson('/api/v1/map-data?lat=51.0358&long=-2.8318');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/vnd.api+json');
        $response->assertJsonStructure([
            'data' => [
                'floods',
                'incidents',
                'riverLevels',
                'forecast',
                'weather',
                'lastChecked',
            ],
        ]);
    }

    public function test_map_data_accepts_location_parameter(): void
    {
        Config::set('flood-watch.national_highways.api_key', 'test-key');
        Config::set('flood-watch.national_highways.base_url', 'https://api.example.com');
        Config::set('flood-watch.national_highways.fetch_unplanned', false);

        Http::fake(function ($request) {
            if (str_contains($request->url(), 'environment.data.gov.uk')) {
                if (str_contains($request->url(), '/id/stations')) {
                    return Http::response(['items' => []], 200);
                }
                if (str_contains($request->url(), '/id/floodAreas')) {
                    return Http::response(['items' => []], 200);
                }
                if (str_contains($request->url(), '/id/floods')) {
                    return Http::response(['items' => []], 200);
                }
            }
            if (str_contains($request->url(), 'api.example.com')) {
                return Http::response(['D2Payload' => ['situation' => []]], 200);
            }
            if (str_contains($request->url(), 'postcodes.io')) {
                return Http::response([
                    'result' => [
                        'latitude' => 51.0358,
                        'longitude' => -2.8318,
                        'postcode' => 'TA10 9PU',
                    ],
                ], 200);
            }
            if (str_contains($request->url(), 'fgs.metoffice.gov.uk')) {
                return Http::response(['statement' => []], 200);
            }
            if (str_contains($request->url(), 'open-meteo.com')) {
                return Http::response(['daily' => ['time' => [], 'weathercode' => [], 'temperature_2m_max' => [], 'temperature_2m_min' => [], 'precipitation_sum' => []]], 200);
            }

            return Http::response(null, 404);
        });

        $response = $this->getJson('/api/v1/map-data?location=TA109PU');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }
}
