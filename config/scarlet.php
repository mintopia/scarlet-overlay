<?php
return [
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
        'latitude' => env('SCARLET_HOME_LATITUDE', 51.53433094575387),
    ],
    'reverb' => [
        'host' => env('REVERB_PUBLIC_HOST', env('REVERB_HOST', 'localhost')),
        'port' => (int) env('REVERB_PUBLIC_PORT', env('REVERB_PORT', 8080)),
        'scheme' => env('REVERB_PUBLIC_SCHEME', env('REVERB_SCHEME', 'http')),
    ],
    'mediamtx' => [
        'api_url' => env('MEDIAMTX_API_URL', 'http://mediamtx:9997'),
    ],
    'metrics' => [
        'prometheus_url' => env('PROMETHEUS_URL', 'http://prometheus:9090'),
        'push_interval' => (int) env('METRICS_PUSH_INTERVAL', 15),
        'queries' => [
            'network_latency' => [
                'query' => 'scarlet_network_latency_ms',
                'label' => 'Latency',
                'unit' => 'ms',
                'precision' => 0,
            ],
            'battery_voltage' => [
                'query' => 'scarlet_battery_voltage_volts',
                'label' => 'Battery',
                'unit' => 'V',
                'precision' => 1,
            ],
            'depth' => [
                'query' => 'scarlet_depth_meters',
                'label' => 'Depth',
                'unit' => 'm',
                'precision' => 1,
            ],
        ],
    ],
];
