<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

/**
 * Builds system prompts and tool definitions for the Flood Watch LLM.
 * Prompts are versioned in resources/prompts/{version}/.
 */
class FloodWatchPromptBuilder
{
    /**
     * Cached base prompt to avoid repeated file reads
     */
    protected ?string $cachedBasePrompt = null;

    /**
     * Cached tool definitions to avoid repeated array construction
     */
    protected ?array $cachedToolDefinitions = null;

    public function __construct(
        protected string $version = 'v1',
        protected ?\App\Support\Tooling\ToolRegistry $registry = null,
    ) {}

    public function buildSystemPrompt(?string $region = null): string
    {
        $prompt = $this->loadBasePrompt();

        if ($region !== null && $region !== '') {
            $regionConfig = config("flood-watch.regions.{$region}");
            if (is_array($regionConfig) && ! empty($regionConfig['prompt'])) {
                $prompt .= "\n\n**Region-specific guidance (user's location):**\n".$regionConfig['prompt'];
            }
        }

        return $prompt;
    }

    public function loadBasePrompt(): string
    {
        if ($this->cachedBasePrompt !== null) {
            return $this->cachedBasePrompt;
        }

        $path = resource_path("prompts/{$this->version}/system.txt");

        if (! File::exists($path)) {
            throw new \RuntimeException("Prompt file not found: {$path}");
        }

        $this->cachedBasePrompt = trim(File::get($path));

        return $this->cachedBasePrompt;
    }

    /**
     * @return array<int, array{type: string, function: array{name: string, description: string, parameters: array}}>
     */
    public function getToolDefinitions(): array
    {
        if ($this->cachedToolDefinitions !== null) {
            return $this->cachedToolDefinitions;
        }

        if ($this->registry === null) {
            throw new \RuntimeException('ToolRegistry is not available in FloodWatchPromptBuilder');
        }

        $defs = $this->registry->definitions();
        usort($defs, fn ($a, $b) => strcmp($a['function']['name'] ?? '', $b['function']['name'] ?? ''));

        return $this->cachedToolDefinitions = $defs;
    }
}
