<?php

declare(strict_types=1);

namespace App\Support\Tooling;

final class TokenBudget
{
    public function __construct(
        public readonly int $maxTokens
    ) {}
}
