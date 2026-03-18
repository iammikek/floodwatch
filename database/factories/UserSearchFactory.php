<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserSearch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserSearch>
 */
class UserSearchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'location' => fake()->city(),
            'lat' => 51.0358,
            'lng' => -2.8318,
            'region' => 'somerset',
            'searched_at' => now(),
        ];
    }

    public function guest(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
            'session_id' => 'test-session-'.fake()->uuid(),
        ]);
    }
}
