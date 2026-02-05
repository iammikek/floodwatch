<?php

use App\Services\FloodWatchPromptBuilder;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    Config::set('flood-watch.regions', [
        'somerset' => [
            'areas' => ['BA', 'TA'],
            'prompt' => '**Somerset Levels focus**: Muchelney is prone to being cut off.',
        ],
    ]);
});

test('build system prompt returns base prompt when no region', function () {
    $builder = new FloodWatchPromptBuilder('v1');

    $prompt = $builder->buildSystemPrompt(null);

    expect($prompt)->toContain('South West Emergency Assistant');
    expect($prompt)->toContain('Data Correlation');
    expect($prompt)->not->toContain('Somerset Levels focus');
});

test('build system prompt appends region specific guidance when region given', function () {
    $builder = new FloodWatchPromptBuilder('v1');

    $prompt = $builder->buildSystemPrompt('somerset');

    expect($prompt)->toContain('South West Emergency Assistant');
    expect($prompt)->toContain("Region-specific guidance (user's location)");
    expect($prompt)->toContain('Somerset Levels focus');
    expect($prompt)->toContain('Muchelney');
});

test('build system prompt returns base prompt when region has no prompt config', function () {
    Config::set('flood-watch.regions.unknown_region', ['areas' => ['XX']]);
    $builder = new FloodWatchPromptBuilder('v1');

    $prompt = $builder->buildSystemPrompt('unknown_region');

    expect($prompt)->toContain('South West Emergency Assistant');
    expect($prompt)->not->toContain('Region-specific guidance');
});

test('load base prompt loads content from prompts directory', function () {
    $builder = new FloodWatchPromptBuilder('v1');

    $content = $builder->loadBasePrompt();

    expect($content)->toContain('South West Emergency Assistant');
    expect($content)->toContain('Never invent or hallucinate data');
});

test('load base prompt throws when version does not exist', function () {
    $builder = new FloodWatchPromptBuilder('nonexistent');

    $builder->loadBasePrompt();
})->throws(\RuntimeException::class, 'Prompt file not found');

test('system prompt matches snapshot', function () {
    $builder = new FloodWatchPromptBuilder('v1');

    $prompt = $builder->buildSystemPrompt(null);

    expect($prompt)->toMatchSnapshot();
});

test('system prompt with somerset region matches snapshot', function () {
    $builder = new FloodWatchPromptBuilder('v1');

    $prompt = $builder->buildSystemPrompt('somerset');

    expect($prompt)->toMatchSnapshot();
});

test('tool definitions match snapshot', function () {
    $builder = new FloodWatchPromptBuilder('v1');

    $tools = $builder->getToolDefinitions();

    expect($tools)->toMatchSnapshot();
});
