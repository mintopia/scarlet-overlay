<?php

namespace Tests\Feature;

use App\Services\WeatherService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WeatherServiceTest extends TestCase
{
    public function test_null_api_values_become_null_not_zero(): void
    {
        Http::fake([
            '*/forecast*' => Http::response([
                'latitude' => 50.0, 'longitude' => -1.0, 'timezone' => 'UTC',
                'current' => [
                    'temperature_2m' => null,
                    'is_day' => 1,
                    'weather_code' => 0,
                    'wind_speed_10m' => 10.5,
                    'wind_gusts_10m' => null,
                    'wind_direction_10m' => 180,
                    'surface_pressure' => null,
                ],
                'hourly' => ['time' => [], 'temperature_2m' => [], 'weather_code' => [], 'wind_speed_10m' => [], 'wind_gusts_10m' => [], 'precipitation' => []],
            ]),
            '*/marine*' => Http::response([
                'current' => [
                    'sea_surface_temperature' => null,
                    'ocean_current_velocity' => 0.5,
                    'ocean_current_direction' => 90,
                    'wave_height' => null,
                    'wave_direction' => 200,
                    'wave_period' => null,
                ],
            ]),
        ]);

        $service = app(WeatherService::class);
        $weather = $service->getWeather(force: true);

        $this->assertNull($weather->temp);
        $this->assertNull($weather->windGusts);
        $this->assertNull($weather->pressure);
        $this->assertNull($weather->seaTemp);
        $this->assertNull($weather->waveHeight);
        $this->assertNull($weather->wavePeriod);
        $this->assertEquals(10.5, $weather->windSpeed);
        $this->assertEquals(0.5, $weather->current);
    }
}
