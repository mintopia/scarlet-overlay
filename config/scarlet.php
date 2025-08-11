<?php
return [
    'gps' => [
        'endpoint' => env('GPS_ENDPOINT'),
        'device' => env('GPS_DEVICE'),
        'username' => env('GPS_USERNAME'),
        'password' => env('GPS_PASSWORD'),
    ],
    'weather' => [
        'endpoints' => [
            'forecast' => env('WEATHER_FORECAST_ENDPOINT', 'https://api.open-meteo.com/v1/'),
            'marine' => env('WEATHER_MARINE_ENDPOINT', 'https://marine-api.open-meteo.com/v1/'),
        ],
    ],
];
