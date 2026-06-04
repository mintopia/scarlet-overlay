<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\GpsResource;
use App\Http\Resources\V1\WeatherResource;
use App\Services\GpsService;
use App\Services\MetricsService;
use App\Services\WeatherService;
use Illuminate\Http\JsonResponse;

class ApiController extends Controller
{
    public function gps(GpsService $gpsService): GpsResource
    {
        $gps = $gpsService->getLocation();

        return new GpsResource($gps);
    }

    public function weather(WeatherService $weatherService): WeatherResource
    {
        $weather = $weatherService->getWeather();

        return new WeatherResource($weather);
    }

    public function weather_home(WeatherService $weatherService): WeatherResource
    {
        $weather = $weatherService->getWeatherForHome();

        return new WeatherResource($weather);
    }

    public function metrics(MetricsService $metricsService): JsonResponse
    {
        return response()->json($metricsService->getAllMetrics());
    }
}
