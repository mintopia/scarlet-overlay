<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        return [
            'title' => fake()->words(3, true),
        ];
    }

    public function shared(): static
    {
        return $this->state(fn () => [
            'share_token' => fake()->sha1(),
        ]);
    }
}
