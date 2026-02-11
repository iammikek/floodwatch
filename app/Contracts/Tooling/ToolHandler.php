<?php

declare(strict_types=1);

namespace App\Contracts\Tooling;

use App\Enums\ToolName;
use App\Support\Tooling\TokenBudget;
use App\Support\Tooling\ToolArguments;
use App\Support\Tooling\ToolContext;
use App\Support\Tooling\ToolResult;

interface ToolHandler
{
    public function name(): ToolName;

    public function definition(): array;

    public function execute(ToolArguments $args, ToolContext $ctx): ToolResult;

    public function presentForLlm(ToolResult $result, TokenBudget $budget): array|string;
}
