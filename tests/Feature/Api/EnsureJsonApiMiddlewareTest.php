<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class EnsureJsonApiMiddlewareTest extends TestCase
{
    public function test_api_responses_have_json_api_content_type(): void
    {
        $response = $this->getJson('/api/v1/activities');

        $response->assertHeader('Content-Type', 'application/vnd.api+json');
    }
}
