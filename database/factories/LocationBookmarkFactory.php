<?php

namespace Database\Factories;

use App\Models\LocationBookmark;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LocationBookmark>
 */
class LocationBookmarkFactory extends Factory
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
            'label' => fake()->word(),
            'location' => fake()->city(),
            'lat' => 51.0358,
            'lng' => -2.8318,
            'region' => 'somerset',
            'is_default' => false,
        ];
    }
}
