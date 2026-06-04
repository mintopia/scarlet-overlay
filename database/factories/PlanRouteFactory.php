<?php

namespace Database\Factories;

use App\Models\PlanGroup;
use App\Models\PlanRoute;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlanRouteFactory extends Factory
{
    protected $model = PlanRoute::class;

    public function definition(): array
    {
        $startLat = fake()->latitude(48, 51);
        $startLng = fake()->longitude(-3, -1);
        $endLat = fake()->latitude(48, 51);
        $endLng = fake()->longitude(-3, -1);

        return [
            'plan_group_id' => PlanGroup::factory(),
            'name' => fake()->city().' → '.fake()->city(),
            'gpx_path' => 'planner/gpx/'.fake()->uuid().'.gpx',
            'distance_nm' => fake()->randomFloat(1, 10, 200),
            'is_enabled' => true,
            'color_index' => 0,
            'track_points' => [
                [$startLat, $startLng],
                [($startLat + $endLat) / 2, ($startLng + $endLng) / 2],
                [$endLat, $endLng],
            ],
            'waypoints' => [
                ['name' => 'Start', 'lat' => $startLat, 'lng' => $startLng],
                ['name' => 'End', 'lat' => $endLat, 'lng' => $endLng],
            ],
            'sort_order' => 0,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn () => ['is_enabled' => false]);
    }
}
