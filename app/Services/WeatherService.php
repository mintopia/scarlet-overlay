<?php

namespace App\Services;

use App\Models\Weather;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WeatherService
{
    public function __construct(protected GpsService $gpsService) {}

    public function getWeatherForHome(bool $force = false): Weather
    {
        if ($force) {
            return $this->fetchWeatherForHome();
        }

        return Cache::get('weather.home.latest', function () {
            return $this->fetchWeatherForHome();
        });
    }

    public function getWeather(bool $force = false): Weather
    {
        if ($force) {
            return $this->fetchWeather(true);
        }

        return Cache::get('weather.latest', function () {
            return $this->fetchWeather();
        });
    }

    protected function fetchWeather(bool $forceGps = false): Weather
    {
        $gps = $this->gpsService->getLocation($forceGps);
        $weather = $this->fetchWeatherForLatLong($gps->latitude, $gps->longitude);
        Cache::put('weather.latest', $weather, 300);

        return $weather;
    }

    protected function fetchWeatherForHome(): Weather
    {
        $weather = $this->fetchWeatherForLatLong(config('scarlet.home.latitude'), config('scarlet.home.longitude'));
        Cache::put('weather.home.latest', $weather, 300);

        return $weather;
    }

    protected function fetchWeatherForLatLong(float $latitude, float $longitude): Weather
    {

        $forecastUri = config('scarlet.weather.endpoints.forecast');
        $marineUri = config('scarlet.weather.endpoints.marine');

        $forecastQuery = [
            'longitude' => $longitude,
            'latitude' => $latitude,
            'current' => 'weather_code,temperature_2m,is_day,wind_speed_10m,wind_gusts_10m,wind_direction_10m,surface_pressure',
            'hourly' => 'temperature_2m,weather_code,wind_speed_10m,wind_gusts_10m,precipitation',
            'forecast_hours' => 24,
            'wind_speed_unit' => 'kn',
            'timezone' => 'auto',
        ];

        Log::debug("Fetching weather forecast for {$latitude}, {$longitude}");
        $forecast = Http::timeout(10)->get("{$forecastUri}forecast", $forecastQuery)->json();

        $marineQuery = [
            'longitude' => $longitude,
            'latitude' => $latitude,
            'current' => 'wave_height,wave_direction,wave_period,sea_surface_temperature,ocean_current_velocity,ocean_current_direction',
            'wind_speed_unit' => 'kn',
        ];
        Log::debug("Fetching marine forecast for {$latitude}, {$longitude}");
        $marine = Http::timeout(10)->get("{$marineUri}marine", $marineQuery)->json();

        $weather = new Weather;
        $weather->latitude = $forecast['latitude'] ?? null;
        $weather->longitude = $forecast['longitude'] ?? null;
        $weather->timezone = $forecast['timezone'] ?? 'UTC';
        $weather->temp = isset($forecast['current']['temperature_2m']) ? (float) $forecast['current']['temperature_2m'] : null;
        $weather->daytime = (bool) ($forecast['current']['is_day'] ?? true);
        $weather->wmoCode = (float) ($forecast['current']['weather_code'] ?? 0);
        $weather->windSpeed = isset($forecast['current']['wind_speed_10m']) ? (float) $forecast['current']['wind_speed_10m'] : null;
        $weather->windGusts = isset($forecast['current']['wind_gusts_10m']) ? (float) $forecast['current']['wind_gusts_10m'] : null;
        $weather->windDirection = isset($forecast['current']['wind_direction_10m']) ? (int) $forecast['current']['wind_direction_10m'] : null;
        $weather->pressure = isset($forecast['current']['surface_pressure']) ? (float) $forecast['current']['surface_pressure'] : null;
        $weather->seaTemp = isset($marine['current']['sea_surface_temperature']) ? (float) $marine['current']['sea_surface_temperature'] : null;
        $weather->current = isset($marine['current']['ocean_current_velocity']) ? (float) $marine['current']['ocean_current_velocity'] : null;
        $weather->currentDirection = isset($marine['current']['ocean_current_direction']) ? (int) $marine['current']['ocean_current_direction'] : null;
        $weather->waveHeight = isset($marine['current']['wave_height']) ? (float) $marine['current']['wave_height'] : null;
        $weather->waveDirection = isset($marine['current']['wave_direction']) ? (int) $marine['current']['wave_direction'] : null;
        $weather->wavePeriod = isset($marine['current']['wave_period']) ? (float) $marine['current']['wave_period'] : null;

        $hourlyTimes = $forecast['hourly']['time'] ?? [];
        $hourlyTemps = $forecast['hourly']['temperature_2m'] ?? [];
        $hourlyCodes = $forecast['hourly']['weather_code'] ?? [];
        $hourlyWind = $forecast['hourly']['wind_speed_10m'] ?? [];
        $hourlyGusts = $forecast['hourly']['wind_gusts_10m'] ?? [];
        $hourlyPrecip = $forecast['hourly']['precipitation'] ?? [];
        $now = now($weather->timezone);

        foreach ($hourlyTimes as $i => $time) {
            $hour = Carbon::parse($time, $weather->timezone);
            if ($hour->lte($now)) {
                continue;
            }
            $weather->forecast[] = [
                'time' => $hour->toIso8601String(),
                'temp' => $hourlyTemps[$i] ?? null,
                'code' => $hourlyCodes[$i] ?? 0,
                'wind' => $hourlyWind[$i] ?? null,
                'gusts' => $hourlyGusts[$i] ?? null,
                'precip' => $hourlyPrecip[$i] ?? null,
            ];
        }

        return $weather;
    }
}
