<?php

namespace App\Support;

/**
 * Redacts sensitive data before logging. Prevents PII and API responses from appearing in logs.
 */
class LogMasker
{
    private const string REDACTED = '[REDACTED]';

    /**
     * Redact OpenAI payload for safe logging. Masks user/assistant content, preserves structure.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function maskOpenAiPayload(array $payload): array
    {
        $masked = $payload;

        if (isset($masked['messages']) && is_array($masked['messages'])) {
            $masked['messages'] = array_map(
                fn (mixed $msg) => is_array($msg) ? self::maskMessage($msg) : $msg,
                $masked['messages']
            );
        }

        return $masked;
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array<string, mixed>
     */
    private static function maskMessage(array $message): array
    {
        $masked = $message;

        if (isset($masked['content']) && is_string($masked['content']) && $masked['content'] !== '') {
            $masked['content'] = self::REDACTED.' ('.strlen($masked['content']).' chars)';
        }

        if (isset($masked['tool_calls']) && is_array($masked['tool_calls'])) {
            foreach ($masked['tool_calls'] as $i => $tc) {
                if (is_array($tc) && isset($tc['function']['arguments'])) {
                    $args = $tc['function']['arguments'];
                    $masked['tool_calls'][$i]['function']['arguments'] = self::REDACTED.' ('.strlen((string) $args).' chars)';
                }
            }
        }

        return $masked;
    }

    /**
     * Redact tool result content for safe logging. Returns size summary instead of full content.
     */
    public static function maskToolContent(string $toolName, string $content): string
    {
        return self::REDACTED." ({$toolName}, ".strlen($content).' chars)';
    }

    /**
     * Redact API response body. Truncates and masks potential PII.
     */
    public static function maskResponseBody(string $body, int $maxChars = 200): string
    {
        if ($body === '') {
            return '';
        }

        $len = strlen($body);

        if ($len <= $maxChars) {
            return self::REDACTED." ({$len} chars)";
        }

        return self::REDACTED." ({$len} chars, truncated)";
    }
}
