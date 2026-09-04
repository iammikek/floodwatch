<?php

namespace Tests\Unit\Services;

use App\Services\CorridorPredictionService;
use App\Services\DataLakeClient;
use App\Services\DataLakeResponse;
use App\Support\ConfigKey;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

class CorridorPredictionServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_returns_null_when_disabled(): void
    {
        Config::set('flood-watch.predictions.enabled', false);
        $client = Mockery::mock(DataLakeClient::class);
        $client->shouldNotReceive('getPredictions');

        $doc = (new CorridorPredictionService($client))->getPrediction();

        $this->assertNull($doc);
    }

    public function test_returns_document_on_success(): void
    {
        Config::set('flood-watch.predictions.enabled', true);
        Config::set('flood-watch.predictions.default_corridor', 'a361-muchelney');
        Config::set(ConfigKey::DATA_LAKE.'.base_url', 'http://lake.test');

        $body = [
            'schema' => 'floodwatch.prediction.v1',
            'prediction' => ['verdict' => 'clear'],
            'method' => ['name' => 'historic_analogue_v1'],
        ];
        $client = Mockery::mock(DataLakeClient::class);
        $client->shouldReceive('getPredictions')
            ->once()
            ->with('a361-muchelney', 120, null, null)
            ->andReturn(new DataLakeResponse(200, 'W/"x"', $body));

        $doc = (new CorridorPredictionService($client))->getPrediction();

        $this->assertSame($body, $doc);
    }

    public function test_forwards_as_of_to_client(): void
    {
        Config::set('flood-watch.predictions.enabled', true);
        Config::set('flood-watch.predictions.default_corridor', 'a361-muchelney');

        $body = [
            'schema' => 'floodwatch.prediction.v1',
            'prediction' => ['verdict' => 'at_risk'],
            'method' => ['name' => 'historic_analogue_v1'],
        ];
        $client = Mockery::mock(DataLakeClient::class);
        $client->shouldReceive('getPredictions')
            ->once()
            ->with('a361-muchelney', 120, null, '2020-02-16T12:00:00Z')
            ->andReturn(new DataLakeResponse(200, 'W/"x"', $body));

        $doc = (new CorridorPredictionService($client))->getPrediction(
            null,
            null,
            '2020-02-16T12:00:00Z'
        );

        $this->assertSame($body, $doc);
    }

    public function test_returns_null_on_non_200(): void
    {
        Config::set('flood-watch.predictions.enabled', true);
        $client = Mockery::mock(DataLakeClient::class);
        $client->shouldReceive('getPredictions')
            ->once()
            ->andReturn(new DataLakeResponse(503, null, null));

        $this->assertNull((new CorridorPredictionService($client))->getPrediction());
    }

    public function test_returns_null_on_schema_mismatch(): void
    {
        Config::set('flood-watch.predictions.enabled', true);
        $client = Mockery::mock(DataLakeClient::class);
        $client->shouldReceive('getPredictions')
            ->once()
            ->andReturn(new DataLakeResponse(200, null, ['schema' => 'other']));

        $this->assertNull((new CorridorPredictionService($client))->getPrediction());
    }
}
