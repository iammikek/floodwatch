<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\LlmTrim;

it('limits items in an array', function () {
    $items = [1, 2, 3, 4, 5];

    expect(LlmTrim::limitItems($items, 3))->toBe([1, 2, 3])
        ->and(LlmTrim::limitItems($items, 0))->toBe([])
        ->and(LlmTrim::limitItems($items, 10))->toBe([1, 2, 3, 4, 5])
        ->and(LlmTrim::limitItems($items, -1))->toBe([]);
});

it('truncates strings with ellipsis', function () {
    $text = 'Hello World';

    expect(LlmTrim::truncate($text, 5))->toBe('Hello…')
        ->and(LlmTrim::truncate($text, 11))->toBe('Hello World')
        ->and(LlmTrim::truncate($text, 20))->toBe('Hello World')
        ->and(LlmTrim::truncate($text, 0))->toBe('…');
});

it('truncates multibyte strings correctly', function () {
    $text = '🌊 Flood Watch 🌊';

    // mb_strlen is 15
    expect(LlmTrim::truncate($text, 7))->toBe('🌊 Flood…')
        ->and(mb_strlen(LlmTrim::truncate($text, 7)))->toBe(8); // 7 chars + ellipsis
});

it('trims lists with a mapper', function () {
    $items = [
        ['id' => 1, 'name' => 'First'],
        ['id' => 2, 'name' => 'Second'],
        ['id' => 3, 'name' => 'Third'],
    ];

    $result = LlmTrim::trimList($items, 2, function ($item) {
        $item['name'] = strtoupper($item['name']);

        return $item;
    });

    expect($result)->toHaveCount(2)
        ->and($result[0]['name'])->toBe('FIRST')
        ->and($result[1]['name'])->toBe('SECOND');
});
