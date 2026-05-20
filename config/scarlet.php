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

        'battery' => [
            'min_voltage' => (float) env('BATTERY_MIN_VOLTAGE', 10.0),
            'max_voltage' => (float) env('BATTERY_MAX_VOLTAGE', 14.4),
        ],

        'mappings' => [
            'boat' => [
                'speed_sog' => 'scarlet_gps_speed_kn',
                'heading' => 'scarlet_gps_heading_deg',
                'air_temp' => 'scarlet_environment_temperature_celsius',
            ],

            'tracker' => [
                'battery_voltage' => 'scarlet_system_battery_voltage_volts',
                'usb_powered' => 'scarlet_system_usb_powered',
                'lte_connected' => 'scarlet_system_lte_connected',
                'lte_rssi' => 'scarlet_system_lte_rssi_dBm',
                'lte_signal_quality' => 'scarlet_system_lte_signal_quality',
                'lte_rat' => 'scarlet_system_lte_rat',
                'wifi_connected' => 'scarlet_system_wifi_connected',
                'wifi_rssi' => 'scarlet_system_wifi_rssi_dBm',
                'uptime' => 'scarlet_system_uptime_seconds',
                'heap_free' => 'scarlet_system_free_heap_bytes',
                'mode' => 'scarlet_system_mode',
                'cabin_temp' => 'scarlet_environment_temperature_celsius',
                'cabin_humidity' => 'scarlet_environment_humidity_percent',
            ],

            'gps' => [
                'latitude' => 'scarlet_gps_latitude_deg',
                'longitude' => 'scarlet_gps_longitude_deg',
                'altitude' => 'scarlet_gps_altitude_meters',
                'satellites' => 'scarlet_gps_satellites',
                'hdop' => 'scarlet_gps_hdop',
                'speed' => 'scarlet_gps_speed_kn',
                'heading' => 'scarlet_gps_heading_deg',
            ],

            'history' => [
                'battery' => 'scarlet_system_battery_voltage_volts',
                'temperature' => 'scarlet_environment_temperature_celsius',
                'humidity' => 'scarlet_environment_humidity_percent',
                'speed' => 'scarlet_gps_speed_kn',
            ],
        ],
    ],
];
