<?php

namespace Database\Factories;

use App\Models\LlmRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LlmRequest>
 */
class LlmRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'model' => 'gpt-4o-mini',
            'input_tokens' => fake()->numberBetween(100, 2000),
            'output_tokens' => fake()->numberBetween(50, 500),
            'openai_id' => 'chatcmpl-'.fake()->regexify('[a-zA-Z0-9]{24}'),
            'region' => fake()->randomElement(['somerset', 'devon', null]),
        ];
    }
}
