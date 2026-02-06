<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SystemActivity>
 */
class SystemActivityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => fake()->randomElement(['flood_warning', 'road_closure', 'road_reopened', 'river_level_elevated']),
            'description' => fake()->sentence(),
            'severity' => fake()->randomElement(['low', 'moderate', 'high', 'severe']),
            'occurred_at' => now(),
            'metadata' => null,
        ];
    }
}
