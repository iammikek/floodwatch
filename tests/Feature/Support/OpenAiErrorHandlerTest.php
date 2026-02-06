<?php

use App\Support\OpenAiErrorHandler;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    $this->handler = new OpenAiErrorHandler;
});

test('is rate limit error returns true for rate limit message', function () {
    $e = new \Exception('Rate limit exceeded');

    expect($this->handler->isRateLimitError($e))->toBeTrue();
});

test('is rate limit error returns true for 429 message', function () {
    $e = new \Exception('HTTP 429 Too Many Requests');

    expect($this->handler->isRateLimitError($e))->toBeTrue();
});

test('is rate limit error returns false for other errors', function () {
    $e = new \Exception('Connection refused');

    expect($this->handler->isRateLimitError($e))->toBeFalse();
});

test('format error message returns rate limit translation for rate limit error', function () {
    $e = new \Exception('Rate limit exceeded');

    $message = $this->handler->formatErrorMessage($e);

    expect($message)->toBe(__('flood-watch.error.rate_limit'));
});

test('format error message returns timeout translation for timeout error', function () {
    $e = new \Exception('cURL error 28: Operation timed out');

    $message = $this->handler->formatErrorMessage($e);

    expect($message)->toBe(__('flood-watch.error.timeout'));
});

test('format error message returns connection translation for connection error', function () {
    $e = new \Exception('Connection refused');

    $message = $this->handler->formatErrorMessage($e);

    expect($message)->toBe(__('flood-watch.error.connection'));
});

test('format error message returns generic when debug disabled', function () {
    Config::set('app.debug', false);
    $e = new \Exception('Some unknown error');

    $message = $this->handler->formatErrorMessage($e);

    expect($message)->toBe(__('flood-watch.error.generic'));
});

test('format error message returns raw message when debug enabled', function () {
    Config::set('app.debug', true);
    $e = new \Exception('Some unknown error');

    $message = $this->handler->formatErrorMessage($e);

    expect($message)->toBe('Some unknown error');
});

test('log rate limit completes without throwing', function () {
    $e = new \Exception('Rate limit exceeded');

    expect(fn () => $this->handler->logRateLimit($e))->not->toThrow(\Throwable::class);
});

test('extract rate limit headers returns non-empty headers from response', function () {
    $response = $this->createMock(\Psr\Http\Message\ResponseInterface::class);
    $response->method('getHeaderLine')->willReturnCallback(function (string $name) {
        return match ($name) {
            'x-ratelimit-limit-requests' => '100',
            'x-ratelimit-remaining-requests' => '0',
            default => '',
        };
    });

    $headers = $this->handler->extractRateLimitHeaders($response);

    expect($headers)->toHaveKey('x-ratelimit-limit-requests', '100');
    expect($headers)->toHaveKey('x-ratelimit-remaining-requests', '0');
});
