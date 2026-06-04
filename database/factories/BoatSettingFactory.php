<?php

namespace Database\Factories;

use App\Models\BoatSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BoatSetting>
 */
class BoatSettingFactory extends Factory
{
    protected $model = BoatSetting::class;

    public function definition(): array
    {
        return [
            'key' => fake()->unique()->word(),
            'value' => fake()->word(),
        ];
    }
}
