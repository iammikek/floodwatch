<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class ActivitiesControllerTest extends TestCase
{
    public function test_activities_returns_json_api_document(): void
    {
        $response = $this->getJson('/api/v1/activities');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/vnd.api+json');
        $response->assertJsonStructure(['data']);
        $this->assertIsArray($response->json('data'));
    }
}
