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
    'time' => [
        'offset' => env('SCARLET_TIME_OFFSET', 0),
        'label' => env('SCARLET_TIME_LABEL', 'UTC'),
    ],
    'name' => env('SCARLET_NAME', 'Scarlet'),
    'passage' => env('SCARLET_PASSAGE', ''),
    'mmsi' => env('SCARLET_MMSI', ''),
    'home' => [
        'name' => env('SCARLET_HOME_NAME', 'UK'),
        'longitude' => env('SCARLET_HOME_LONGITUDE', -0.13853150944904064),
        'latitude' => env('SCARLET_HOME_LATITUDE', 51.53433094575387)
    ]
];
