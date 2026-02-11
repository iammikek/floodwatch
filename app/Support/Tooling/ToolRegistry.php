<?php

declare(strict_types=1);

namespace App\Support\Tooling;

use App\Contracts\Tooling\ToolHandler;
use App\Enums\ToolName;

final class ToolRegistry
{
    /** @var array<string, ToolHandler> */
    private array $map = [];

    /** @param iterable<ToolHandler> $handlers */
    public function __construct(iterable $handlers)
    {
        foreach ($handlers as $handler) {
            $this->map[$handler->name()->value] = $handler;
        }
    }

    public function get(ToolName $name): ToolHandler
    {
        if (! isset($this->map[$name->value])) {
            throw new \RuntimeException("Unknown tool: {$name->value}");
        }

        return $this->map[$name->value];
    }

    /**
     * @return list<array>
     */
    public function definitions(): array
    {
        return array_values(array_map(static fn (ToolHandler $h) => $h->definition(), $this->map));
    }
}
