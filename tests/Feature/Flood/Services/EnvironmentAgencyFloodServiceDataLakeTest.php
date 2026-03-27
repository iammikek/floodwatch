<?php

namespace Tests\Feature\Flood\Services;

use App\Flood\Services\EnvironmentAgencyFloodService;
use App\Support\ConfigKey;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EnvironmentAgencyFloodServiceDataLakeTest extends TestCase
{
    public function test_warnings_uses_etag_and_returns_cached_body_on_304(): void
    {
        Config::set(ConfigKey::DATA_LAKE.'.base_url', 'http://lake.test');
        Config::set('flood-watch.cache_ttl_minutes', 5);

        Http::fake([
            'http://lake.test/v1/warnings*' => Http::sequence()
                ->push(['items' => [['severity' => 'Flood alert', 'message' => 'Test warning', 'floodAreaID' => 'A1']]], 200, ['ETag' => 'W/"abc"'])
                ->push(null, 304, ['ETag' => 'W/"abc"']),
        ]);

        $svc = new EnvironmentAgencyFloodService;
        $first = $svc->getFloods(51.0, -2.8, 10);
        $this->assertIsArray($first);
        $this->assertCount(1, $first);

        $second = $svc->getFloods(51.0, -2.8, 10);
        $this->assertIsArray($second);
        $this->assertCount(1, $second);
        $this->assertSame($first[0]['floodAreaID'], $second[0]['floodAreaID']);
    }
}
