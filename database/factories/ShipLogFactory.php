<?php

namespace Database\Factories;

use App\Models\ShipLog;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShipLogFactory extends Factory
{
    protected $model = ShipLog::class;

    public function definition(): array
    {
        return [
            'recorded_at' => fake()->dateTimeBetween('-7 days', 'now')->format('Y-m-d H:00:00'),
            'latitude' => fake()->latitude(49.5, 51.0),
            'longitude' => fake()->longitude(-2.0, 0.5),
            'course' => fake()->randomFloat(1, 0, 359.9),
            'trip_log' => fake()->randomFloat(1, 0, 500),
            'wind_speed' => fake()->randomFloat(1, 0, 40),
            'wind_direction' => fake()->randomFloat(1, 0, 359.9),
            'pressure' => fake()->randomFloat(1, 990, 1030),
            'wp_distance' => fake()->randomFloat(1, 0, 100),
            'wp_ttg' => fake()->numberBetween(0, 86400),
            'battery_soc' => fake()->randomFloat(1, 20, 100),
            'water_level' => fake()->randomFloat(1, 10, 100),
            'fuel_level' => fake()->randomFloat(1, 10, 100),
        ];
    }

    public function withNotes(string $notes = 'Test log entry notes'): static
    {
        return $this->state(fn () => [
            'notes' => $notes,
        ]);
    }

    public function withJourney(int $journeyId): static
    {
        return $this->state(fn () => [
            'journey_id' => $journeyId,
        ]);
    }
}
