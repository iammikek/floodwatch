<?php

declare(strict_types=1);

namespace App\Support\Tooling;

final class TokenBudget
{
    public function __construct(
        public readonly int $maxTokens,
        public readonly int $usedTokens = 0,
    ) {}

    public function remaining(): int
    {
        return max(0, $this->maxTokens - $this->usedTokens);
    }
}
