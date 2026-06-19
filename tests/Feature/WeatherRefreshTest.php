<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WeatherRefreshTest extends TestCase
{
    public function test_refresh_command_pulls_and_caches_weather(): void
    {
        Http::fake([
            'api.open-meteo.com/*' => Http::response(['current' => ['temperature_2m' => 17.0, 'weather_code' => 3, 'wind_speed_10m' => 14, 'wind_gusts_10m' => 19, 'wind_direction_10m' => 225, 'surface_pressure' => 1014], 'hourly' => ['time' => [], 'temperature_2m' => []]], 200),
            'marine-api.open-meteo.com/*' => Http::response(['current' => ['wave_height' => 1.2, 'wave_period' => 6, 'wave_direction' => 240, 'sea_surface_temperature' => 15.0]], 200),
        ]);

        $this->artisan('weather:refresh')->assertExitCode(0);

        $this->assertNotNull(Cache::get('weather.latest'));
    }
}
