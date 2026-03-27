<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FloodWatchWarningsControllerTest extends TestCase
{
    public function test_forwards_since_parameter_to_data_lake(): void
    {
        Config::set('flood-watch.data_lake.base_url', 'http://lake.test');

        Http::fake(function ($request) {
            if (str_starts_with($request->url(), 'http://lake.test/v1/warnings')) {
                parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $qs);
                $this->assertSame('SOM', $qs['region'] ?? null);
                $this->assertSame('PT1H', $qs['since'] ?? null);

                return Http::response(['items' => []], 200, ['ETag' => 'W/"xyz"']);
            }

            return Http::response(null, 404);
        });

        $resp = $this->getJson(route('flood-watch.warnings', ['region' => 'SOM', 'since' => 'PT1H']));

        $resp->assertOk();
        $resp->assertJson(['items' => []]);

        Http::assertSent(fn ($req) => str_starts_with($req->url(), 'http://lake.test/v1/warnings') && str_contains($req->url(), 'since=PT1H'));
    }
}
