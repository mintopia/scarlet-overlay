<?php

namespace App\Http\Controllers;

use App\Services\MetricsService;

class WeatherMetricsController extends Controller
{
    public function __invoke(MetricsService $metrics)
    {
        $weather = $metrics->getWeatherData();

        if (! $weather) {
            return response("# Weather data unavailable\nscarlet_weather_up 0\n", 200)
                ->header('Content-Type', 'text/plain; version=0.0.4; charset=utf-8');
        }

        $lines = [];
        $lines[] = '# HELP scarlet_weather_up Whether weather data is available';
        $lines[] = '# TYPE scarlet_weather_up gauge';
        $lines[] = 'scarlet_weather_up 1';

        $this->gauge($lines, 'scarlet_weather_temperature_celsius', 'Air temperature in Celsius', $weather['temp']);
        $this->gauge($lines, 'scarlet_weather_pressure_hpa', 'Surface pressure in hPa', $weather['pressure']);
        $this->gauge($lines, 'scarlet_weather_wind_speed_kn', 'Wind speed in knots', $weather['wind']->speed ?? null);
        $this->gauge($lines, 'scarlet_weather_wind_gusts_kn', 'Wind gusts in knots', $weather['wind']->gusts ?? null);
        $this->gauge($lines, 'scarlet_weather_wind_direction_deg', 'Wind direction in degrees', $weather['wind']->direction ?? null);
        $this->gauge($lines, 'scarlet_weather_sea_temperature_celsius', 'Sea surface temperature in Celsius', $weather['seaTemp']);
        $this->gauge($lines, 'scarlet_weather_wave_height_m', 'Wave height in metres', $weather['waves']->height ?? null);
        $this->gauge($lines, 'scarlet_weather_wave_direction_deg', 'Wave direction in degrees', $weather['waves']->direction ?? null);
        $this->gauge($lines, 'scarlet_weather_wave_period_s', 'Wave period in seconds', $weather['waves']->period ?? null);
        $this->gauge($lines, 'scarlet_weather_current_speed_kn', 'Ocean current speed in knots', $weather['current']->speed ?? null);
        $this->gauge($lines, 'scarlet_weather_current_direction_deg', 'Ocean current direction in degrees', $weather['current']->direction ?? null);
        $this->gauge($lines, 'scarlet_weather_condition_code', 'WMO weather condition code', $weather['code'] ?? null);

        $lines[] = '';

        return response(implode("\n", $lines), 200)
            ->header('Content-Type', 'text/plain; version=0.0.4; charset=utf-8');
    }

    private function gauge(array &$lines, string $name, string $help, mixed $value): void
    {
        if ($value === null) {
            return;
        }
        $lines[] = "# HELP {$name} {$help}";
        $lines[] = "# TYPE {$name} gauge";
        $lines[] = "{$name} {$value}";
    }
}
