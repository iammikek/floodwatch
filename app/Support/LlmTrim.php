<?php

declare(strict_types=1);

namespace App\Support;

class LlmTrim
{
    /**
     * Limit the number of items in an array.
     */
    public static function limitItems(array $items, int $max): array
    {
        return array_slice($items, 0, max(0, $max));
    }

    /**
     * Truncate a string if it exceeds the maximum length and add an ellipsis.
     */
    public static function truncate(string $text, int $maxChars, string $ellipsis = '…'): string
    {
        if (mb_strlen($text) <= $maxChars) {
            return $text;
        }

        return mb_substr($text, 0, $maxChars).$ellipsis;
    }

    /**
     * Map items and apply item-level truncation if applicable.
     *
     * @param  callable(mixed): mixed  $mapper
     */
    public static function trimList(array $items, int $maxItems, callable $mapper): array
    {
        $limited = self::limitItems($items, $maxItems);

        return array_map($mapper, $limited);
    }
}
