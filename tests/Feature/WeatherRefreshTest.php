<?php

namespace Tests\Feature;

use App\Services\CanonicalCatalog;
use App\Support\CanonicalBaseline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WeatherRefreshTest extends TestCase
{
    use RefreshDatabase;

    public function test_refresh_command_pulls_and_caches_weather(): void
    {
        // GPS is resolved through the canonical reader; fake VM so a position is available.
        app(CanonicalCatalog::class)->applyBaseline(CanonicalBaseline::definitions(), 'reset', 'test');

        Http::fake([
            '*/api/v1/query*' => Http::response([
                'status' => 'success',
                'data' => ['result' => [['value' => [now()->timestamp, '50.0']]]],
            ]),
            'api.open-meteo.com/*' => Http::response(['current' => ['temperature_2m' => 17.0, 'weather_code' => 3, 'wind_speed_10m' => 14, 'wind_gusts_10m' => 19, 'wind_direction_10m' => 225, 'surface_pressure' => 1014], 'hourly' => ['time' => [], 'temperature_2m' => []]], 200),
            'marine-api.open-meteo.com/*' => Http::response(['current' => ['wave_height' => 1.2, 'wave_period' => 6, 'wave_direction' => 240, 'sea_surface_temperature' => 15.0]], 200),
        ]);

        $this->artisan('weather:refresh')->assertExitCode(0);

        $this->assertNotNull(Cache::get('weather.latest'));
    }
}
