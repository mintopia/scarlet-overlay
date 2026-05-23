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
                // Navigation
                'speed_sog' => 'scarlet_signalk_navigation_speedOverGround * 1.94384',
                'speed_stw' => 'scarlet_signalk_navigation_speedThroughWater * 1.94384',
                'heading' => 'scarlet_boat_heading_deg',
                'cog' => 'scarlet_signalk_navigation_courseOverGroundTrue * 180 / 3.14159265359',
                'depth' => 'scarlet_boat_depth_meters',
                'heel' => 'scarlet_signalk_navigation_attitude_roll * 180 / 3.14159265359',
                'trip_log' => 'scarlet_signalk_navigation_trip_log / 1852',
                'nav_wp_distance' => 'scarlet_signalk_navigation_courseGreatCircle_nextPoint_distance / 1852',
                'nav_wp_ttg' => 'scarlet_signalk_navigation_courseGreatCircle_nextPoint_timeToGo',

                // Wind (apparent from Signal K, true computed from apparent + SOG + heading)
                'wind_speed_apparent' => 'scarlet_signalk_environment_wind_speedApparent * 1.94384',
                'wind_angle_apparent' => 'scarlet_signalk_environment_wind_angleApparent * 180 / 3.14159265359',
                '_aws' => 'scarlet_signalk_environment_wind_speedApparent',
                '_awa' => 'scarlet_signalk_environment_wind_angleApparent',
                '_sog' => 'scarlet_signalk_navigation_speedOverGround',
                '_heading' => 'scarlet_signalk_navigation_headingTrue',

                // Environment
                'water_temp' => 'scarlet_signalk_environment_water_temperature - 273.15',

                // Batteries (Signal K: bank 0 = house, bank 1 = engine)
                'house_battery_voltage' => 'scarlet_signalk_electrical_batteries_0_voltage',
                'house_battery_soc' => 'scarlet_signalk_electrical_batteries_0_capacity_stateOfCharge * 100',
                'house_battery_current' => 'scarlet_signalk_electrical_batteries_0_current',
                'house_battery_time_remaining' => 'scarlet_signalk_electrical_batteries_0_capacity_timeRemaining / 3600',
                'engine_battery_voltage' => 'scarlet_signalk_electrical_batteries_1_voltage',

                // Tanks
                'fuel_level' => 'clamp_max(scarlet_signalk_tanks_fuel_currentLevel / 0.91, 1) * 100',
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
                'cpu_usage' => 'scarlet_system_cpu_usage_percent',
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

            // Signal K position (float64 precision, smooth updates)
            'signalk_position' => [
                'latitude' => 'scarlet_signalk_navigation_position_latitude',
                'longitude' => 'scarlet_signalk_navigation_position_longitude',
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
                'fuel_level' => 'clamp_max(scarlet_signalk_tanks_fuel_currentLevel / 0.91, 1) * 100',
                'water_level' => 'scarlet_mqtt_percent{topic="watertank"}',
                'cpu_usage' => 'scarlet_system_cpu_usage_percent',
            ],

            'explore' => [
                // Navigation
                'speed' => [
                    'label' => 'Speed (SOG)',
                    'unit' => 'kn',
                    'color' => 'oklch(0.54 0.22 27)',
                    'query' => 'scarlet_signalk_navigation_speedOverGround * 1.94384',
                    'fallback' => 'scarlet_gps_speed_kn',
                    'group' => 'navigation',
                ],
                'depth' => [
                    'label' => 'Depth',
                    'unit' => 'm',
                    'color' => 'oklch(0.55 0.15 240)',
                    'query' => 'scarlet_boat_depth_meters',
                    'group' => 'navigation',
                ],
                'heading' => [
                    'label' => 'Heading',
                    'unit' => '°',
                    'color' => 'oklch(0.45 0.005 40)',
                    'query' => 'scarlet_boat_heading_deg',
                    'group' => 'navigation',
                ],
                'cog' => [
                    'label' => 'Course Over Ground',
                    'unit' => '°',
                    'color' => 'oklch(0.55 0.15 240)',
                    'query' => 'scarlet_signalk_navigation_courseOverGroundTrue * 180 / 3.14159265359',
                    'group' => 'navigation',
                ],
                'nav_wp_distance' => [
                    'label' => 'Distance to Waypoint',
                    'unit' => 'nm',
                    'color' => 'oklch(0.55 0.15 240)',
                    'query' => 'scarlet_signalk_navigation_courseGreatCircle_nextPoint_distance / 1852',
                    'group' => 'navigation',
                ],
                'nav_wp_ttg' => [
                    'label' => 'Time to Waypoint',
                    'unit' => 's',
                    'color' => 'oklch(0.60 0.16 240)',
                    'query' => 'scarlet_signalk_navigation_courseGreatCircle_nextPoint_timeToGo',
                    'group' => 'navigation',
                ],

                // Wind
                'wind_speed_true' => [
                    'label' => 'True Wind Speed',
                    'unit' => 'kn',
                    'color' => 'oklch(0.54 0.22 27)',
                    'query' => 'scarlet_boat_wind_speed_kn',
                    'group' => 'wind',
                ],
                'wind_direction_true' => [
                    'label' => 'True Wind Direction',
                    'unit' => '°',
                    'color' => 'oklch(0.65 0.18 40)',
                    'query' => 'scarlet_boat_wind_direction_deg',
                    'group' => 'wind',
                ],
                'wind_speed_apparent' => [
                    'label' => 'Apparent Wind Speed',
                    'unit' => 'kn',
                    'color' => 'oklch(0.60 0.16 330)',
                    'query' => 'scarlet_signalk_environment_wind_speedApparent * 1.94384',
                    'group' => 'wind',
                ],
                'wind_angle_apparent' => [
                    'label' => 'Apparent Wind Angle',
                    'unit' => '°',
                    'color' => 'oklch(0.60 0.16 330)',
                    'query' => 'scarlet_signalk_environment_wind_angleApparent * 180 / 3.14159265359',
                    'group' => 'wind',
                ],

                // Power
                'battery_voltage' => [
                    'label' => 'Battery Voltage',
                    'unit' => 'V',
                    'color' => 'oklch(0.62 0.15 155)',
                    'query' => 'scarlet_signalk_electrical_batteries_0_voltage',
                    'group' => 'power',
                ],
                'battery_current' => [
                    'label' => 'Battery Current',
                    'unit' => 'A',
                    'color' => 'oklch(0.65 0.18 40)',
                    'query' => 'scarlet_signalk_electrical_batteries_0_current',
                    'group' => 'power',
                ],
                'battery_power' => [
                    'label' => 'Battery Power',
                    'unit' => 'W',
                    'color' => 'oklch(0.62 0.15 155)',
                    'query' => 'scarlet_signalk_electrical_batteries_0_current * scarlet_signalk_electrical_batteries_0_voltage',
                    'group' => 'power',
                    'signed' => true,
                ],
                'battery_soc' => [
                    'label' => 'Battery SOC',
                    'unit' => '%',
                    'color' => 'oklch(0.62 0.15 155)',
                    'query' => 'scarlet_signalk_electrical_batteries_0_capacity_stateOfCharge * 100',
                    'group' => 'power',
                ],
                'engine_battery_voltage' => [
                    'label' => 'Engine Battery',
                    'unit' => 'V',
                    'color' => 'oklch(0.65 0.18 40)',
                    'query' => 'scarlet_signalk_electrical_batteries_1_voltage',
                    'group' => 'power',
                ],

                // Cabin
                'temp_forepeak' => [
                    'label' => 'Temp: Forepeak',
                    'unit' => '°C',
                    'color' => 'oklch(0.70 0.14 70)',
                    'query' => 'scarlet_environment_temperature_celsius',
                    'group' => 'cabin',
                ],
                'temp_quarterberth' => [
                    'label' => 'Temp: Quarterberth',
                    'unit' => '°C',
                    'color' => 'oklch(0.60 0.16 240)',
                    'query' => 'scarlet_mqtt_temperature{topic="zigbee2mqtt/Quarterberth"}',
                    'group' => 'cabin',
                ],
                'temp_main_cabin' => [
                    'label' => 'Temp: Main Cabin',
                    'unit' => '°C',
                    'color' => 'oklch(0.65 0.18 330)',
                    'query' => 'scarlet_mqtt_temperature{topic="zigbee2mqtt/Main Cabin"}',
                    'group' => 'cabin',
                ],
                'humidity_forepeak' => [
                    'label' => 'Humidity: Forepeak',
                    'unit' => '%',
                    'color' => 'oklch(0.70 0.14 70)',
                    'query' => 'scarlet_environment_humidity_percent',
                    'group' => 'cabin',
                ],
                'humidity_quarterberth' => [
                    'label' => 'Humidity: Quarterberth',
                    'unit' => '%',
                    'color' => 'oklch(0.60 0.16 240)',
                    'query' => 'scarlet_mqtt_humidity{topic="zigbee2mqtt/Quarterberth"}',
                    'group' => 'cabin',
                ],
                'humidity_main_cabin' => [
                    'label' => 'Humidity: Main Cabin',
                    'unit' => '%',
                    'color' => 'oklch(0.65 0.18 330)',
                    'query' => 'scarlet_mqtt_humidity{topic="zigbee2mqtt/Main Cabin"}',
                    'group' => 'cabin',
                ],

                // Tanks
                'fuel_level' => [
                    'label' => 'Diesel Level',
                    'unit' => '%',
                    'color' => 'oklch(0.70 0.14 70)',
                    'query' => 'clamp_max(scarlet_signalk_tanks_fuel_currentLevel / 0.91, 1) * 100',
                    'group' => 'tanks',
                ],
                'water_level' => [
                    'label' => 'Fresh Water Level',
                    'unit' => '%',
                    'color' => 'oklch(0.55 0.15 240)',
                    'query' => 'scarlet_mqtt_percent{topic="watertank"}',
                    'group' => 'tanks',
                ],

                // Tracker
                'lte_rssi' => [
                    'label' => 'LTE Signal',
                    'unit' => 'dBm',
                    'color' => 'oklch(0.54 0.22 27)',
                    'query' => 'scarlet_system_lte_rssi_dBm',
                    'group' => 'tracker',
                ],
                'wifi_rssi' => [
                    'label' => 'WiFi Signal',
                    'unit' => 'dBm',
                    'color' => 'oklch(0.55 0.15 240)',
                    'query' => 'scarlet_system_wifi_rssi_dBm',
                    'group' => 'tracker',
                ],
                'gps_satellites' => [
                    'label' => 'GPS Satellites',
                    'unit' => '',
                    'color' => 'oklch(0.62 0.15 155)',
                    'query' => 'scarlet_gps_satellites',
                    'group' => 'tracker',
                ],
                'cpu_usage' => [
                    'label' => 'CPU Usage',
                    'unit' => '%',
                    'color' => 'oklch(0.60 0.16 330)',
                    'query' => 'scarlet_system_cpu_usage_percent',
                    'group' => 'tracker',
                ],
            ],

            'log' => [
                'trip_log' => 'scarlet_signalk_navigation_trip_log / 1852',
                'aws' => 'scarlet_signalk_environment_wind_speedApparent',
                'awa' => 'scarlet_signalk_environment_wind_angleApparent',
                'sog' => 'scarlet_signalk_navigation_speedOverGround',
                'heading' => 'scarlet_signalk_navigation_headingTrue',
                'latitude' => 'scarlet_signalk_navigation_position_latitude',
                'longitude' => 'scarlet_signalk_navigation_position_longitude',
                'wp_distance' => 'scarlet_signalk_navigation_courseGreatCircle_nextPoint_distance / 1852',
                'wp_ttg' => 'scarlet_signalk_navigation_courseGreatCircle_nextPoint_timeToGo',
                'battery_soc' => 'scarlet_signalk_electrical_batteries_0_capacity_stateOfCharge * 100',
                'water_level' => 'scarlet_mqtt_percent{topic="watertank"}',
                'fuel_level' => 'scarlet_signalk_tanks_fuel_currentLevel * 100',
            ],
        ],
    ],
];
