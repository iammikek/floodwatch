<?php

use App\Flood\Services\EnvironmentAgencyFloodService;
use App\Support\CircuitBreaker;
use App\Support\CircuitOpenException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Config::set('flood-watch.cache_key_prefix', 'flood-watch-test');
    Config::set('flood-watch.circuit_breaker.enabled', true);
    Config::set('flood-watch.circuit_breaker.failure_threshold', 2);
    Config::set('flood-watch.circuit_breaker.cooldown_seconds', 60);
});

afterEach(function () {
    $prefix = Config::get('flood-watch.cache_key_prefix', 'flood-watch');
    Cache::forget("{$prefix}:circuit:environment_agency:failures");
    Cache::forget("{$prefix}:circuit:environment_agency:open");
});

test('circuit breaker returns empty when open', function () {
    Http::fake(fn () => Http::response(null, 500));

    $circuit = new CircuitBreaker('environment_agency', 2, 60);

    $failures = 0;
    for ($i = 0; $i < 3; $i++) {
        try {
            $circuit->execute(fn () => throw new \RuntimeException('API error'));
        } catch (\Throwable $e) {
            $failures++;
        }
    }

    expect($circuit->isOpen())->toBeTrue();

    expect(fn () => $circuit->execute(fn () => 'ok'))
        ->toThrow(CircuitOpenException::class);
});

test('environment agency service returns empty when circuit is open', function () {
    $circuit = new CircuitBreaker('environment_agency', 1, 60);
    $circuit->recordFailure();
    Cache::put('flood-watch-test:circuit:environment_agency:open', true, 60);

    $service = new EnvironmentAgencyFloodService($circuit);

    Http::fake(fn () => Http::response(null, 500));

    $result = $service->getFloods();

    expect($result)->toBe([]);
});

test('circuit breaker disabled bypasses open state', function () {
    Config::set('flood-watch.circuit_breaker.enabled', false);

    $circuit = new CircuitBreaker('environment_agency', 1, 60);
    Cache::put('flood-watch-test:circuit:environment_agency:open', true, 60);

    $result = $circuit->execute(fn () => 'ok');

    expect($result)->toBe('ok');
});

test('environment agency service works when circuit disabled', function () {
    Config::set('flood-watch.circuit_breaker.enabled', false);

    Http::fake(function ($request) {
        if (str_contains($request->url(), 'environment.data.gov.uk')) {
            if (str_contains($request->url(), '/id/floodAreas')) {
                return Http::response(['items' => []], 200);
            }

            return Http::response([
                'items' => [
                    [
                        'description' => 'Test flood',
                        'severity' => 'Warning',
                        'severityLevel' => 1,
                        'message' => 'Test',
                    ],
                ],
            ], 200);
        }

        return Http::response(null, 404);
    });

    $service = app(EnvironmentAgencyFloodService::class);
    $result = $service->getFloods();

    expect($result)->toHaveCount(1);
    expect($result[0]['description'])->toBe('Test flood');
});
