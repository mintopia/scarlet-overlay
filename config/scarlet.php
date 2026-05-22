<?php
return [
    'weather' => [
        'endpoints' => [
            'forecast' => env('WEATHER_FORECAST_ENDPOINT', 'https://api.open-meteo.com/v1/'),
            'marine' => env('WEATHER_MARINE_ENDPOINT', 'https://marine-api.open-meteo.com/v1/'),
        ],
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
        'host' => env('REVERB_PUBLIC_HOST', ''),
        'port' => (int) env('REVERB_PUBLIC_PORT', 443),
        'scheme' => env('REVERB_PUBLIC_SCHEME', 'https'),
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
                // Navigation (Signal K — more accurate than tracker GPS)
                'speed_sog' => 'scarlet_signalk_navigation_speedOverGround * 1.94384',
                'speed_stw' => 'scarlet_signalk_navigation_speedThroughWater * 1.94384',
                'heading' => 'scarlet_signalk_navigation_headingMagnetic * 180 / 3.14159265359',
                'cog' => 'scarlet_signalk_navigation_courseOverGroundTrue * 180 / 3.14159265359',
                'depth' => 'scarlet_signalk_environment_depth_belowSurface',
                'heel' => 'scarlet_signalk_navigation_attitude_roll * 180 / 3.14159265359',
                'trip_log' => 'scarlet_signalk_navigation_trip_log / 1852',

                // Wind (apparent from Signal K, true from tracker)
                'wind_speed_apparent' => 'scarlet_signalk_environment_wind_speedApparent * 1.94384',
                'wind_angle_apparent' => 'scarlet_signalk_environment_wind_angleApparent * 180 / 3.14159265359',
                'wind_speed_true' => 'scarlet_boat_wind_speed_kn',
                'wind_direction_true' => 'scarlet_boat_wind_direction_deg',

                // Environment
                'air_temp' => 'scarlet_environment_temperature_celsius',
                'water_temp' => 'scarlet_signalk_environment_water_temperature - 273.15',

                // Batteries (Signal K: bank 0 = house, bank 1 = engine)
                'house_battery_voltage' => 'scarlet_signalk_electrical_batteries_0_voltage',
                'house_battery_soc' => 'scarlet_signalk_electrical_batteries_0_capacity_stateOfCharge * 100',
                'house_battery_current' => 'scarlet_signalk_electrical_batteries_0_current',
                'house_battery_time_remaining' => 'scarlet_signalk_electrical_batteries_0_capacity_timeRemaining / 3600',
                'engine_battery_voltage' => 'scarlet_signalk_electrical_batteries_1_voltage',

                // Tanks
                'fuel_level' => 'scarlet_boat_fuel_tank_percent',
                'water_level' => 'scarlet_mqtt_percent{topic="watertank"}',

                // Cabin environment (Zigbee sensors via MQTT)
                'cabin_temp_quarterberth' => 'scarlet_mqtt_temperature{topic="zigbee2mqtt/Quarterberth"}',
                'cabin_humidity_quarterberth' => 'scarlet_mqtt_humidity{topic="zigbee2mqtt/Quarterberth"}',
                'cabin_temp_main' => 'scarlet_mqtt_temperature{topic="zigbee2mqtt/Main Cabin"}',
                'cabin_humidity_main' => 'scarlet_mqtt_humidity{topic="zigbee2mqtt/Main Cabin"}',
                'cabin_temp_forepeak' => 'scarlet_environment_temperature_celsius',
                'cabin_humidity_forepeak' => 'scarlet_environment_humidity_percent',
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
                'track_latitude' => 'scarlet_gps_latitude_deg',
                'track_longitude' => 'scarlet_gps_longitude_deg',
                'track_sog' => 'scarlet_gps_speed_kn',
                'battery' => 'scarlet_signalk_electrical_batteries_0_voltage',
                'speed' => 'scarlet_signalk_navigation_speedOverGround * 1.94384',
                'temp_forepeak' => 'scarlet_environment_temperature_celsius',
                'temp_quarterberth' => 'scarlet_mqtt_temperature{topic="zigbee2mqtt/Quarterberth"}',
                'temp_main_cabin' => 'scarlet_mqtt_temperature{topic="zigbee2mqtt/Main Cabin"}',
                'humidity_forepeak' => 'scarlet_environment_humidity_percent',
                'humidity_quarterberth' => 'scarlet_mqtt_humidity{topic="zigbee2mqtt/Quarterberth"}',
                'humidity_main_cabin' => 'scarlet_mqtt_humidity{topic="zigbee2mqtt/Main Cabin"}',
                'battery_current' => 'scarlet_signalk_electrical_batteries_0_current',
                'battery_power' => 'scarlet_signalk_electrical_batteries_0_current * scarlet_signalk_electrical_batteries_0_voltage',
            ],
        ],
    ],
];
