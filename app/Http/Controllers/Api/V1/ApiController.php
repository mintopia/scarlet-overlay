<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\GpsResource;
use App\Http\Resources\V1\WeatherResource;
use App\Services\GpsService;
use App\Services\WeatherService;
use Illuminate\Http\Request;

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
}
