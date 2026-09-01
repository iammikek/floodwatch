<?php

namespace Tests\Feature;

use App\Services\LocationResolver;
use Mockery;
use Tests\TestCase;

class FloodWatchReverseGeocodeControllerTest extends TestCase
{
    public function test_reverse_geocode_requires_flood_watch_session(): void
    {
        $response = $this->getJson('/flood-watch/reverse-geocode?lat=51.04&lng=-2.83');

        $response->assertForbidden();
    }

    public function test_reverse_geocode_requires_coords(): void
    {
        $response = $this->withSession(['flood_watch_loaded' => true])
            ->getJson('/flood-watch/reverse-geocode');

        $response->assertStatus(422)
            ->assertJsonPath('valid', false);
    }

    public function test_reverse_geocode_returns_location_in_area(): void
    {
        $resolver = Mockery::mock(LocationResolver::class);
        $resolver->shouldReceive('reverseFromCoords')
            ->once()
            ->with(51.04, -2.83)
            ->andReturn([
                'valid' => true,
                'in_area' => true,
                'location' => 'Langport',
                'region' => 'SOM',
                'error' => null,
            ]);
        $this->app->instance(LocationResolver::class, $resolver);

        $response = $this->withSession(['flood_watch_loaded' => true])
            ->getJson('/flood-watch/reverse-geocode?lat=51.04&lng=-2.83');

        $response->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('in_area', true)
            ->assertJsonPath('location', 'Langport');
    }

    public function test_reverse_geocode_rejects_outside_area(): void
    {
        $resolver = Mockery::mock(LocationResolver::class);
        $resolver->shouldReceive('reverseFromCoords')
            ->once()
            ->andReturn([
                'valid' => true,
                'in_area' => false,
                'location' => 'London',
                'region' => null,
                'error' => null,
            ]);
        $this->app->instance(LocationResolver::class, $resolver);

        $response = $this->withSession(['flood_watch_loaded' => true])
            ->getJson('/flood-watch/reverse-geocode?lat=51.5&lng=-0.12');

        $response->assertStatus(422)
            ->assertJsonPath('valid', true)
            ->assertJsonPath('in_area', false);
    }
}
