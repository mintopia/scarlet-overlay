<?php
namespace App\Services;

use App\Models\Gps;
use App\Models\Weather;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WeatherService
{
    public function __construct(protected GpsService $gpsService)
    {

    }

    public function getWeatherForHome(bool $force = false): Weather
    {
        if ($force) {
            return $this->fetchWeatherForHome();
        }
        return Cache::get('weather.home.latest', function() {
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
            'current' => 'weather_code,temperature_2m,is_day,wind_speed_10m,wind_direction_10m',
            'wind_speed_unit' => 'kn',
            'timezone' => 'auto',
        ];

        Log::debug("Fetching weather forecast for {$latitude}, {$longitude}");
        $forecast = Http::get("{$forecastUri}forecast", $forecastQuery)->json();

        $marineQuery = [
            'longitude' => $longitude,
            'latitude' => $latitude,
            'current' => 'wave_height,wave_direction,wave_period,sea_surface_temperature,ocean_current_velocity,ocean_current_direction',
            'wind_speed_unit' => 'kn',
        ];
        Log::debug("Fetching marine forecast for {$latitude}, {$longitude}");
        $marine = Http::get("{$marineUri}marine", $marineQuery)->json();

        $weather = new Weather;
        $weather->latitude = $forecast['latitude'] ?? null;
        $weather->longitude = $forecast['longitude'] ?? null;
        $weather->timezone = $forecast['timezone'] ?? 'UTC';
        $weather->temp = (float)$forecast['current']['temperature_2m'] ?? null;
        $weather->daytime = (bool)$forecast['current']['is_day'] ?? true;
        $weather->wmoCode = (float)$forecast['current']['weather_code'] ?? 0;
        $weather->windSpeed = (float)$forecast['current']['wind_speed_10m'] ?? 0;
        $weather->windDirection = (int)$forecast['current']['wind_direction_10m'] ?? 0;
        $weather->seaTemp = (float)$marine['current']['sea_surface_temperature'] ?? null;
        $weather->current = (float)$marine['current']['ocean_current_velocity'] ?? 0;
        $weather->currentDirection = (int)$marine['current']['ocean_current_direction'] ?? 0;
        $weather->waveHeight = (float)$marine['current']['wave_height'] ?? 0;
        $weather->waveDirection = (int)$marine['current']['wave_direction'] ?? 0;
        $weather->wavePeriod = (float)$marine['current']['wave_period'] ?? 0;
        return $weather;
    }
}
