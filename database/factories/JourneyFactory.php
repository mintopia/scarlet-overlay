<?php

namespace Database\Factories;

use App\Models\Journey;
use Illuminate\Database\Eloquent\Factories\Factory;

class JourneyFactory extends Factory
{
    protected $model = Journey::class;

    public function definition(): array
    {
        return [
            'from_port' => fake()->city(),
            'to_port' => fake()->city(),
            'started_at' => now()->subHours(3),
            'status' => 'completed',
            'ended_at' => now(),
            'is_public' => true,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => 'active',
            'ended_at' => null,
        ]);
    }

    public function abandoned(): static
    {
        return $this->state(fn () => [
            'status' => 'abandoned',
        ]);
    }
}
