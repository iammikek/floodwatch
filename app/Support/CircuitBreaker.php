<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Simple cache-based circuit breaker. Opens after N failures, stays open for a cooldown period.
 * Uses config('flood-watch.cache_key_prefix') for cache keys.
 */
class CircuitBreaker
{
    protected int $failureThreshold;

    protected int $cooldownSeconds;

    public function __construct(
        protected string $service,
        ?int $failureThreshold = null,
        ?int $cooldownSeconds = null
    ) {
        $this->failureThreshold = $failureThreshold ?? config('flood-watch.circuit_breaker.failure_threshold', 5);
        $this->cooldownSeconds = $cooldownSeconds ?? config('flood-watch.circuit_breaker.cooldown_seconds', 60);
    }

    protected function isEnabled(): bool
    {
        return config('flood-watch.circuit_breaker.enabled', true);
    }

    public function isOpen(): bool
    {
        $key = $this->openKey();

        return Cache::get($key, false);
    }

    public function recordSuccess(): void
    {
        Cache::forget($this->failureKey());
    }

    public function recordFailure(): void
    {
        $failureKey = $this->failureKey();
        $openKey = $this->openKey();

        $failures = (int) Cache::get($failureKey, 0) + 1;
        Cache::put($failureKey, $failures, now()->addSeconds($this->cooldownSeconds * 2));

        if ($failures >= $this->failureThreshold) {
            Cache::put($openKey, true, now()->addSeconds($this->cooldownSeconds));
            Cache::forget($failureKey);
        }
    }

    public function execute(callable $callback): mixed
    {
        if (! $this->isEnabled()) {
            return $callback();
        }

        if ($this->isOpen()) {
            throw new CircuitOpenException("Circuit breaker open for service: {$this->service}");
        }

        try {
            $result = $callback();
            $this->recordSuccess();

            return $result;
        } catch (Throwable $e) {
            $this->recordFailure();
            throw $e;
        }
    }

    private function failureKey(): string
    {
        $prefix = config('flood-watch.cache_key_prefix', 'flood-watch');

        return "{$prefix}:circuit:{$this->service}:failures";
    }

    private function openKey(): string
    {
        $prefix = config('flood-watch.cache_key_prefix', 'flood-watch');

        return "{$prefix}:circuit:{$this->service}:open";
    }
}
