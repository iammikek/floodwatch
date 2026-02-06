<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use OpenAI\Exceptions\ErrorException;
use OpenAI\Exceptions\RateLimitException;
use Psr\Http\Message\ResponseInterface;

class OpenAiErrorHandler
{
    public function logRateLimit(\Throwable $e): void
    {
        $response = null;
        if ($e instanceof RateLimitException) {
            $response = $e->response;
        }
        if ($e instanceof ErrorException) {
            $response = $e->response;
        }

        $context = [
            'exception' => $e::class,
            'message' => $e->getMessage(),
        ];

        if ($response instanceof ResponseInterface) {
            $context['rate_limit'] = $this->extractRateLimitHeaders($response);
            $context['retry_after'] = $response->getHeaderLine('Retry-After');
            $context['status_code'] = $response->getStatusCode();
            try {
                $body = (string) $response->getBody();
                if ($body !== '') {
                    $context['response_body'] = LogMasker::maskResponseBody($body);
                }
            } catch (\Throwable) {
                // Stream may already be consumed
            }
        }

        Log::warning('OpenAI rate limit exceeded', $context);
    }

    /**
     * @return array<string, string>
     */
    public function extractRateLimitHeaders(ResponseInterface $response): array
    {
        $headers = [
            'x-ratelimit-limit-requests' => $response->getHeaderLine('x-ratelimit-limit-requests'),
            'x-ratelimit-remaining-requests' => $response->getHeaderLine('x-ratelimit-remaining-requests'),
            'x-ratelimit-reset-requests' => $response->getHeaderLine('x-ratelimit-reset-requests'),
            'x-ratelimit-limit-tokens' => $response->getHeaderLine('x-ratelimit-limit-tokens'),
            'x-ratelimit-remaining-tokens' => $response->getHeaderLine('x-ratelimit-remaining-tokens'),
            'x-ratelimit-reset-tokens' => $response->getHeaderLine('x-ratelimit-reset-tokens'),
        ];

        return array_filter($headers, fn (string $v) => $v !== '');
    }

    public function isRateLimitError(\Throwable $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'rate limit') || str_contains($message, '429');
    }

    public function formatErrorMessage(\Throwable $e): string
    {
        $message = $e->getMessage();
        if (str_contains(strtolower($message), 'rate limit') || str_contains(strtolower($message), '429')) {
            return __('flood-watch.error.rate_limit');
        }
        if (str_contains($message, 'timed out') || str_contains($message, 'cURL error 28') || str_contains($message, 'Operation timed out')) {
            return __('flood-watch.error.timeout');
        }
        if (str_contains($message, 'Connection') && (str_contains($message, 'refused') || str_contains($message, 'reset'))) {
            return __('flood-watch.error.connection');
        }
        if (config('app.debug')) {
            return $message;
        }

        return __('flood-watch.error.generic');
    }
}
