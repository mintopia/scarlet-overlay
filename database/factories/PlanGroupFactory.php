<?php

namespace Database\Factories;

use App\Models\Plan;
use App\Models\PlanGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlanGroupFactory extends Factory
{
    protected $model = PlanGroup::class;

    public function definition(): array
    {
        return [
            'plan_id' => Plan::factory(),
            'name' => fake()->name()."'s Routes",
            'color_index' => fake()->numberBetween(0, 5),
            'sort_order' => 0,
        ];
    }
}
