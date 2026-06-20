<?php

declare(strict_types=1);

namespace App\Support;

class CanonicalBaseline
{
    /**
     * The code-defined canonical catalog baseline (structured descriptors).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function definitions(): array
    {
        $serial = (string) config('scarlet.canonical.ecoflow_serial', 'P231ZE1APJ3P0446');
        $ecoTopic = fn (string $field): string => "ecoflow/{$serial}_BMSHeartBeatReport/{$field}";

        /** @var array<string, mixed> $jobBoatTracker */
        $jobBoatTracker = [['label' => 'job', 'op' => 'equals', 'value' => 'boat-tracker']];

        /** @var array<string, mixed> $jobWeather */
        $jobWeather = [['label' => 'job', 'op' => 'equals', 'value' => 'weather']];

        /** @var array<string, mixed> $jobSrt */
        $jobSrt = [['label' => 'job', 'op' => 'equals', 'value' => 'scarlet-srt']];

        $radToDeg = [['op' => 'multiply', 'value' => 180], ['op' => 'divide', 'value' => 3.14159265359]];
        $mToNm = [['op' => 'divide', 'value' => 1852]];
        $msToKn = [['op' => 'multiply', 'value' => 1.94384]];
        $ratioToPct = [['op' => 'multiply', 'value' => 100]];
        $kToC = [['op' => 'subtract', 'value' => 273.15]];
        $sToH = [['op' => 'divide', 'value' => 3600]];

        return [
            // ── Tanks (already seeded — keep) ─────────────────────────────────────

            [
                'key' => 'fuel_level', 'label' => 'Diesel', 'group' => 'tank',
                'storage_unit' => 'pct', 'display_unit' => '%', 'volatile' => true,
                'trend_fn' => 'median', 'trend_window' => '10m',
                'staleness_threshold_s' => 3600, 'coverage_window_s' => 3600, 'coverage_min' => 0.5,
                'enabled' => true, 'description' => 'Diesel tank level (SignalK preferred, MQTT tanklevel fallback).',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_signalk_tanks_fuel_0_currentLevel', 'label_matchers' => [], 'source_class' => 'both', 'source_kind' => 'ratio', 'select_fn' => 'last', 'unit_transform' => $ratioToPct, 'staleness_threshold_s' => 1800],
                    ['priority' => 2, 'source_metric_name' => 'scarlet_mqtt_percent', 'label_matchers' => [['label' => 'topic', 'op' => 'equals', 'value' => 'tanklevel']], 'source_class' => 'both', 'source_kind' => 'percent', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'water_fresh_level', 'label' => 'Fresh Water', 'group' => 'tank',
                'storage_unit' => 'pct', 'display_unit' => '%', 'volatile' => true,
                'trend_fn' => 'median', 'trend_window' => '10m',
                'staleness_threshold_s' => 3600, 'coverage_window_s' => 3600, 'coverage_min' => 0.5,
                'enabled' => true, 'description' => 'Fresh water tank level (SignalK preferred, MQTT watertank fallback).',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_signalk_tanks_freshWater_0_currentLevel', 'label_matchers' => [], 'source_class' => 'both', 'source_kind' => 'ratio', 'select_fn' => 'last', 'unit_transform' => $ratioToPct, 'staleness_threshold_s' => 1800],
                    ['priority' => 2, 'source_metric_name' => 'scarlet_mqtt_percent', 'label_matchers' => [['label' => 'topic', 'op' => 'equals', 'value' => 'watertank']], 'source_class' => 'both', 'source_kind' => 'percent', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],

            // ── Sailing / Nav ──────────────────────────────────────────────────────

            [
                'key' => 'speed_sog', 'label' => 'Speed (SOG)', 'group' => 'nav',
                'storage_unit' => 'kn', 'display_unit' => 'kn', 'volatile' => true,
                'trend_fn' => 'last', 'trend_window' => '2m',
                'staleness_threshold_s' => 120, 'coverage_window_s' => 300, 'coverage_min' => 0.5,
                'enabled' => true, 'description' => 'Speed over ground in knots (SignalK m/s → kn).',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_signalk_navigation_speedOverGround', 'label_matchers' => [], 'source_class' => 'both', 'source_kind' => 'speed_ms', 'select_fn' => 'last', 'unit_transform' => $msToKn, 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'speed_stw', 'label' => 'Speed (STW)', 'group' => 'nav',
                'storage_unit' => 'kn', 'display_unit' => 'kn', 'volatile' => true,
                'trend_fn' => 'last', 'trend_window' => '2m',
                'staleness_threshold_s' => 120, 'coverage_window_s' => 300, 'coverage_min' => 0.5,
                'enabled' => true, 'description' => 'Speed through water in knots (SignalK m/s → kn).',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_signalk_navigation_speedThroughWater', 'label_matchers' => [], 'source_class' => 'both', 'source_kind' => 'speed_ms', 'select_fn' => 'last', 'unit_transform' => $msToKn, 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'vmg', 'label' => 'VMG', 'group' => 'nav',
                'storage_unit' => 'kn', 'display_unit' => 'kn', 'volatile' => true,
                'trend_fn' => 'last', 'trend_window' => '2m',
                'staleness_threshold_s' => 120, 'coverage_window_s' => 300, 'coverage_min' => 0.5,
                'gate_metric_name' => 'scarlet_signalk_navigation_course_calcValues_timeToGo', 'gate_max_value' => 1209600.0,
                'enabled' => true, 'description' => 'Velocity made good toward waypoint (SignalK m/s → kn).',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_signalk_navigation_course_calcValues_velocityMadeGood', 'label_matchers' => [], 'source_class' => 'both', 'source_kind' => 'speed_ms', 'select_fn' => 'last', 'unit_transform' => $msToKn, 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'heading_true', 'label' => 'Heading (True)', 'group' => 'nav',
                'storage_unit' => 'deg', 'display_unit' => '°', 'volatile' => true,
                'trend_fn' => 'last', 'trend_window' => '2m',
                'staleness_threshold_s' => 120, 'coverage_window_s' => 300, 'coverage_min' => 0.5,
                'enabled' => true, 'description' => 'True heading in degrees (SignalK rad → deg, magnetic fallback).',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_signalk_navigation_headingTrue', 'label_matchers' => [], 'source_class' => 'both', 'source_kind' => 'angle_rad', 'select_fn' => 'last', 'unit_transform' => $radToDeg, 'staleness_threshold_s' => null],
                    ['priority' => 2, 'source_metric_name' => 'scarlet_signalk_navigation_headingMagnetic', 'label_matchers' => [], 'source_class' => 'both', 'source_kind' => 'angle_rad', 'select_fn' => 'last', 'unit_transform' => $radToDeg, 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'heading_magnetic', 'label' => 'Heading (Magnetic)', 'group' => 'nav',
                'storage_unit' => 'deg', 'display_unit' => '°', 'volatile' => true,
                'trend_fn' => 'last', 'trend_window' => '2m',
                'staleness_threshold_s' => 120, 'coverage_window_s' => 300, 'coverage_min' => 0.5,
                'enabled' => true, 'description' => 'Magnetic heading in degrees (SignalK rad → deg).',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_signalk_navigation_headingMagnetic', 'label_matchers' => [], 'source_class' => 'both', 'source_kind' => 'angle_rad', 'select_fn' => 'last', 'unit_transform' => $radToDeg, 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'cog', 'label' => 'Course Over Ground', 'group' => 'nav',
                'storage_unit' => 'deg', 'display_unit' => '°', 'volatile' => true,
                'trend_fn' => 'last', 'trend_window' => '2m',
                'staleness_threshold_s' => 120, 'coverage_window_s' => 300, 'coverage_min' => 0.5,
                'enabled' => true, 'description' => 'Course over ground true in degrees (SignalK rad → deg).',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_signalk_navigation_courseOverGroundTrue', 'label_matchers' => [], 'source_class' => 'both', 'source_kind' => 'angle_rad', 'select_fn' => 'last', 'unit_transform' => $radToDeg, 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'magnetic_variation', 'label' => 'Magnetic Variation', 'group' => 'nav',
                'storage_unit' => 'deg', 'display_unit' => '°', 'volatile' => true,
                'trend_fn' => 'last', 'trend_window' => '2m',
                'staleness_threshold_s' => 120, 'coverage_window_s' => 300, 'coverage_min' => 0.5,
                'enabled' => true, 'description' => 'Magnetic variation in degrees (SignalK rad → deg).',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_signalk_navigation_magneticVariation', 'label_matchers' => [], 'source_class' => 'both', 'source_kind' => 'angle_rad', 'select_fn' => 'last', 'unit_transform' => $radToDeg, 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'depth_below_surface', 'label' => 'Depth', 'group' => 'nav',
                'storage_unit' => 'm', 'display_unit' => 'm', 'volatile' => true,
                'trend_fn' => 'last', 'trend_window' => '2m',
                'staleness_threshold_s' => 120, 'coverage_window_s' => 300, 'coverage_min' => 0.5,
                'enabled' => true, 'description' => 'Water depth below surface in metres.',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_signalk_environment_depth_belowSurface', 'label_matchers' => [], 'source_class' => 'both', 'source_kind' => 'depth_m', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'depth_below_transducer', 'label' => 'Depth (Transducer)', 'group' => 'nav',
                'storage_unit' => 'm', 'display_unit' => 'm', 'volatile' => true,
                'trend_fn' => 'last', 'trend_window' => '2m',
                'staleness_threshold_s' => 120, 'coverage_window_s' => 300, 'coverage_min' => 0.5,
                'enabled' => true, 'description' => 'Water depth below transducer in metres.',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_signalk_environment_depth_belowTransducer', 'label_matchers' => [], 'source_class' => 'both', 'source_kind' => 'depth_m', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'rate_of_turn', 'label' => 'Rate of Turn', 'group' => 'nav',
                'storage_unit' => 'deg_s', 'display_unit' => '°/s', 'volatile' => true,
                'trend_fn' => 'last', 'trend_window' => '2m',
                'staleness_threshold_s' => 120, 'coverage_window_s' => 300, 'coverage_min' => 0.5,
                'enabled' => true, 'description' => 'Rate of turn in degrees/second (SignalK rad/s → deg/s).',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_signalk_navigation_rateOfTurn', 'label_matchers' => [], 'source_class' => 'both', 'source_kind' => 'angle_rad', 'select_fn' => 'last', 'unit_transform' => $radToDeg, 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'heel', 'label' => 'Heel', 'group' => 'nav',
                'storage_unit' => 'deg', 'display_unit' => '°', 'volatile' => true,
                'trend_fn' => 'last', 'trend_window' => '2m',
                'staleness_threshold_s' => 120, 'coverage_window_s' => 300, 'coverage_min' => 0.5,
                'enabled' => true, 'description' => 'Heel angle in degrees (SignalK attitude roll rad → deg).',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_signalk_navigation_attitude_roll', 'label_matchers' => [], 'source_class' => 'both', 'source_kind' => 'angle_rad', 'select_fn' => 'last', 'unit_transform' => $radToDeg, 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'pitch', 'label' => 'Pitch', 'group' => 'nav',
                'storage_unit' => 'deg', 'display_unit' => '°', 'volatile' => true,
                'trend_fn' => 'last', 'trend_window' => '2m',
                'staleness_threshold_s' => 120, 'coverage_window_s' => 300, 'coverage_min' => 0.5,
                'enabled' => true, 'description' => 'Pitch angle in degrees (SignalK attitude pitch rad → deg).',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_signalk_navigation_attitude_pitch', 'label_matchers' => [], 'source_class' => 'both', 'source_kind' => 'angle_rad', 'select_fn' => 'last', 'unit_transform' => $radToDeg, 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'trip_log', 'label' => 'Trip Log', 'group' => 'nav',
                'storage_unit' => 'nm', 'display_unit' => 'nm', 'volatile' => true,
                'trend_fn' => 'last', 'trend_window' => '2m',
                'staleness_threshold_s' => 120, 'coverage_window_s' => 300, 'coverage_min' => 0.5,
                'enabled' => true, 'description' => 'Trip log distance in nautical miles (SignalK m → nm).',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_signalk_navigation_trip_log', 'label_matchers' => [], 'source_class' => 'both', 'source_kind' => 'distance_m', 'select_fn' => 'last', 'unit_transform' => $mToNm, 'staleness_threshold_s' => null],
                ],
            ],

            // SignalK nav (unmapped — surfaced from catalog)

            [
                'key' => 'xte', 'label' => 'Cross-Track Error', 'group' => 'nav',
                'storage_unit' => 'm', 'display_unit' => 'm', 'volatile' => true,
                'trend_fn' => 'last', 'trend_window' => '2m',
                'staleness_threshold_s' => 120, 'coverage_window_s' => 300, 'coverage_min' => 0.5,
                'valid_min' => -185200.0, 'valid_max' => 185200.0,
                'gate_metric_name' => 'scarlet_signalk_navigation_course_calcValues_timeToGo', 'gate_max_value' => 1209600.0,
                'enabled' => true, 'description' => 'Cross-track error in metres (SignalK course calcValues XTE).',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_signalk_navigation_course_calcValues_crossTrackError', 'label_matchers' => [], 'source_class' => 'both', 'source_kind' => 'distance_m', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'bearing_to_wp_true', 'label' => 'Bearing to Waypoint', 'group' => 'nav',
                'storage_unit' => 'deg', 'display_unit' => '°', 'volatile' => true,
                'trend_fn' => 'last', 'trend_window' => '2m',
                'staleness_threshold_s' => 120, 'coverage_window_s' => 300, 'coverage_min' => 0.5,
                'gate_metric_name' => 'scarlet_signalk_navigation_course_calcValues_timeToGo', 'gate_max_value' => 1209600.0,
                'enabled' => true, 'description' => 'True bearing to next waypoint in degrees.',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_signalk_navigation_course_calcValues_bearingTrue', 'label_matchers' => [], 'source_class' => 'both', 'source_kind' => 'angle_rad', 'select_fn' => 'last', 'unit_transform' => $radToDeg, 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'track_bearing_true', 'label' => 'Track Bearing (True)', 'group' => 'nav',
                'storage_unit' => 'deg', 'display_unit' => '°', 'volatile' => true,
                'trend_fn' => 'last', 'trend_window' => '2m',
                'staleness_threshold_s' => 120, 'coverage_window_s' => 300, 'coverage_min' => 0.5,
                'gate_metric_name' => 'scarlet_signalk_navigation_course_calcValues_timeToGo', 'gate_max_value' => 1209600.0,
                'enabled' => true, 'description' => 'True bearing of active track in degrees.',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_signalk_navigation_course_calcValues_bearingTrackTrue', 'label_matchers' => [], 'source_class' => 'both', 'source_kind' => 'angle_rad', 'select_fn' => 'last', 'unit_transform' => $radToDeg, 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'wp_distance', 'label' => 'Distance to Waypoint', 'group' => 'nav',
                'storage_unit' => 'nm', 'display_unit' => 'nm', 'volatile' => true,
                'trend_fn' => 'last', 'trend_window' => '2m',
                'staleness_threshold_s' => 120, 'coverage_window_s' => 300, 'coverage_min' => 0.5,
                'valid_min' => 0.0, 'valid_max' => 1000.0,
                'gate_metric_name' => 'scarlet_signalk_navigation_course_calcValues_timeToGo', 'gate_max_value' => 1209600.0,
                'enabled' => true, 'description' => 'Distance to next waypoint in nautical miles (m → nm).',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_signalk_navigation_course_calcValues_distance', 'label_matchers' => [], 'source_class' => 'both', 'source_kind' => 'distance_m', 'select_fn' => 'last', 'unit_transform' => $mToNm, 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'wp_ttg', 'label' => 'Time to Waypoint', 'group' => 'nav',
                'storage_unit' => 's', 'display_unit' => 's', 'volatile' => true,
                'trend_fn' => 'last', 'trend_window' => '2m',
                'staleness_threshold_s' => 120, 'coverage_window_s' => 300, 'coverage_min' => 0.5,
                'valid_min' => 0.0, 'valid_max' => 1209600.0,
                'gate_metric_name' => 'scarlet_signalk_navigation_course_calcValues_timeToGo', 'gate_max_value' => 1209600.0,
                'enabled' => true, 'description' => 'Time to go to next waypoint in seconds.',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_signalk_navigation_course_calcValues_timeToGo', 'label_matchers' => [], 'source_class' => 'both', 'source_kind' => 'duration_s', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'current_set_true', 'label' => 'Current Set (True)', 'group' => 'nav',
                'storage_unit' => 'deg', 'display_unit' => '°', 'volatile' => true,
                'trend_fn' => 'last', 'trend_window' => '2m',
                'staleness_threshold_s' => 120, 'coverage_window_s' => 300, 'coverage_min' => 0.5,
                'enabled' => true, 'description' => 'True direction of water current in degrees.',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_signalk_environment_current_setTrue', 'label_matchers' => [], 'source_class' => 'both', 'source_kind' => 'angle_rad', 'select_fn' => 'last', 'unit_transform' => $radToDeg, 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'current_drift', 'label' => 'Current Drift', 'group' => 'nav',
                'storage_unit' => 'kn', 'display_unit' => 'kn', 'volatile' => true,
                'trend_fn' => 'last', 'trend_window' => '2m',
                'staleness_threshold_s' => 120, 'coverage_window_s' => 300, 'coverage_min' => 0.5,
                'enabled' => true, 'description' => 'Water current speed in knots (m/s → kn).',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_signalk_environment_current_drift', 'label_matchers' => [], 'source_class' => 'both', 'source_kind' => 'speed_ms', 'select_fn' => 'last', 'unit_transform' => $msToKn, 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'rudder_angle', 'label' => 'Rudder Angle', 'group' => 'nav',
                'storage_unit' => 'deg', 'display_unit' => '°', 'volatile' => true,
                'trend_fn' => 'last', 'trend_window' => '2m',
                'staleness_threshold_s' => 120, 'coverage_window_s' => 300, 'coverage_min' => 0.5,
                'enabled' => true, 'description' => 'Rudder angle in degrees (SignalK rad → deg).',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_signalk_steering_rudderAngle', 'label_matchers' => [], 'source_class' => 'both', 'source_kind' => 'angle_rad', 'select_fn' => 'last', 'unit_transform' => $radToDeg, 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'autopilot_state', 'label' => 'Autopilot State', 'group' => 'nav',
                'storage_unit' => 'state', 'display_unit' => '', 'volatile' => true,
                'trend_fn' => 'last', 'trend_window' => '2m',
                'staleness_threshold_s' => 120, 'coverage_window_s' => 300, 'coverage_min' => 0.5,
                'enabled' => true, 'description' => 'Autopilot engaged state (enum 0=standby, 1=auto, 2=wind, 3=track).',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_signalk_steering_autopilot_state', 'label_matchers' => [], 'source_class' => 'both', 'source_kind' => 'state', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],

            // ── Position (GPS / SignalK position) ─────────────────────────────────

            [
                'key' => 'position_latitude', 'label' => 'Latitude', 'group' => 'position',
                'storage_unit' => 'deg', 'display_unit' => '°', 'volatile' => true,
                'trend_fn' => 'last', 'trend_window' => '2m',
                'staleness_threshold_s' => 120, 'coverage_window_s' => 300, 'coverage_min' => 0.5,
                'reject_null_island' => true,
                'valid_min' => -90.0, 'valid_max' => 90.0,
                'enabled' => true, 'description' => 'Latitude in degrees (SignalK preferred, GPS signalk then onboard fallback).',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_signalk_navigation_position_latitude', 'label_matchers' => [], 'source_class' => 'both', 'source_kind' => 'angle_deg', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                    ['priority' => 2, 'source_metric_name' => 'scarlet_gps_latitude_deg', 'label_matchers' => [['label' => 'gps_source', 'op' => 'equals', 'value' => 'signalk']], 'source_class' => 'both', 'source_kind' => 'angle_deg', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                    ['priority' => 3, 'source_metric_name' => 'scarlet_gps_latitude_deg', 'label_matchers' => [['label' => 'gps_source', 'op' => 'equals', 'value' => 'onboard']], 'source_class' => 'both', 'source_kind' => 'angle_deg', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'position_longitude', 'label' => 'Longitude', 'group' => 'position',
                'storage_unit' => 'deg', 'display_unit' => '°', 'volatile' => true,
                'trend_fn' => 'last', 'trend_window' => '2m',
                'staleness_threshold_s' => 120, 'coverage_window_s' => 300, 'coverage_min' => 0.5,
                'reject_null_island' => true,
                'valid_min' => -180.0, 'valid_max' => 180.0,
                'enabled' => true, 'description' => 'Longitude in degrees (SignalK preferred, GPS signalk then onboard fallback).',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_signalk_navigation_position_longitude', 'label_matchers' => [], 'source_class' => 'both', 'source_kind' => 'angle_deg', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                    ['priority' => 2, 'source_metric_name' => 'scarlet_gps_longitude_deg', 'label_matchers' => [['label' => 'gps_source', 'op' => 'equals', 'value' => 'signalk']], 'source_class' => 'both', 'source_kind' => 'angle_deg', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                    ['priority' => 3, 'source_metric_name' => 'scarlet_gps_longitude_deg', 'label_matchers' => [['label' => 'gps_source', 'op' => 'equals', 'value' => 'onboard']], 'source_class' => 'both', 'source_kind' => 'angle_deg', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'gps_altitude', 'label' => 'Altitude', 'group' => 'position',
                'storage_unit' => 'm', 'display_unit' => 'm', 'volatile' => false,
                'trend_fn' => 'last', 'trend_window' => '2m',
                'staleness_threshold_s' => 120, 'coverage_window_s' => 300, 'coverage_min' => 0.5,
                'enabled' => true, 'description' => 'GPS altitude in metres.',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_gps_altitude_meters', 'label_matchers' => [], 'source_class' => 'both', 'source_kind' => 'distance_m', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'gps_satellites', 'label' => 'Satellites', 'group' => 'position',
                'storage_unit' => 'count', 'display_unit' => 'count', 'volatile' => false,
                'trend_fn' => 'last', 'trend_window' => '2m',
                'staleness_threshold_s' => 120, 'coverage_window_s' => 300, 'coverage_min' => 0.5,
                'enabled' => true, 'description' => 'Number of GPS satellites in use.',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_gps_satellites', 'label_matchers' => [], 'source_class' => 'both', 'source_kind' => 'count', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'gps_hdop', 'label' => 'HDOP', 'group' => 'position',
                'storage_unit' => '', 'display_unit' => '', 'volatile' => false,
                'trend_fn' => 'last', 'trend_window' => '2m',
                'staleness_threshold_s' => 120, 'coverage_window_s' => 300, 'coverage_min' => 0.5,
                'enabled' => true, 'description' => 'GPS horizontal dilution of precision.',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_gps_hdop', 'label_matchers' => [], 'source_class' => 'both', 'source_kind' => 'ratio', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'gps_speed', 'label' => 'GPS Speed', 'group' => 'position',
                'storage_unit' => 'kn', 'display_unit' => 'kn', 'volatile' => true,
                'trend_fn' => 'last', 'trend_window' => '2m',
                'staleness_threshold_s' => 120, 'coverage_window_s' => 300, 'coverage_min' => 0.5,
                'enabled' => true, 'description' => 'GPS speed over ground in knots (already knots).',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_gps_speed_kn', 'label_matchers' => [], 'source_class' => 'both', 'source_kind' => 'speed_kn', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'gps_heading', 'label' => 'GPS Heading', 'group' => 'position',
                'storage_unit' => 'deg', 'display_unit' => '°', 'volatile' => true,
                'trend_fn' => 'last', 'trend_window' => '2m',
                'staleness_threshold_s' => 120, 'coverage_window_s' => 300, 'coverage_min' => 0.5,
                'enabled' => true, 'description' => 'GPS heading in degrees (already degrees).',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_gps_heading_deg', 'label_matchers' => [], 'source_class' => 'both', 'source_kind' => 'angle_deg', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],

            // ── Wind ──────────────────────────────────────────────────────────────

            [
                'key' => 'wind_speed_apparent', 'label' => 'Apparent Wind Speed', 'group' => 'wind',
                'storage_unit' => 'kn', 'display_unit' => 'kn', 'volatile' => true,
                'trend_fn' => 'last', 'trend_window' => '2m',
                'staleness_threshold_s' => 120, 'coverage_window_s' => 300, 'coverage_min' => 0.5,
                'enabled' => true, 'description' => 'Apparent wind speed in knots (SignalK m/s → kn).',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_signalk_environment_wind_speedApparent', 'label_matchers' => [], 'source_class' => 'both', 'source_kind' => 'speed_ms', 'select_fn' => 'last', 'unit_transform' => $msToKn, 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'wind_angle_apparent', 'label' => 'Apparent Wind Angle', 'group' => 'wind',
                'storage_unit' => 'deg', 'display_unit' => '°', 'volatile' => true,
                'trend_fn' => 'last', 'trend_window' => '2m',
                'staleness_threshold_s' => 120, 'coverage_window_s' => 300, 'coverage_min' => 0.5,
                'enabled' => true, 'description' => 'Apparent wind angle in degrees (SignalK rad → deg).',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_signalk_environment_wind_angleApparent', 'label_matchers' => [], 'source_class' => 'both', 'source_kind' => 'angle_rad', 'select_fn' => 'last', 'unit_transform' => $radToDeg, 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'wind_direction_true', 'label' => 'True Wind Direction', 'group' => 'wind',
                'storage_unit' => 'deg', 'display_unit' => '°', 'volatile' => true,
                'trend_fn' => 'last', 'trend_window' => '2m',
                'staleness_threshold_s' => 120, 'coverage_window_s' => 300, 'coverage_min' => 0.5,
                'enabled' => true, 'description' => 'True wind direction in degrees — derived at read time from apparent wind, speed through water, and heading (ADR 0007).',
                'derived_fn' => 'true_wind_direction',
                'derived_inputs' => ['aws' => 'wind_speed_apparent', 'awa' => 'wind_angle_apparent', 'stw' => 'speed_stw', 'heading' => 'heading_true'],
                'sources' => [],
            ],
            [
                'key' => 'wind_speed_true', 'label' => 'True Wind Speed', 'group' => 'wind',
                'storage_unit' => 'kn', 'display_unit' => 'kn', 'volatile' => true,
                'trend_fn' => 'last', 'trend_window' => '2m',
                'staleness_threshold_s' => 120, 'coverage_window_s' => 300, 'coverage_min' => 0.5,
                'enabled' => true, 'description' => 'True wind speed in knots — derived at read time from apparent wind, speed through water, and heading (ADR 0007).',
                'derived_fn' => 'true_wind_speed',
                'derived_inputs' => ['aws' => 'wind_speed_apparent', 'awa' => 'wind_angle_apparent', 'stw' => 'speed_stw', 'heading' => 'heading_true'],
                'sources' => [],
            ],

            // ── Power ─────────────────────────────────────────────────────────────

            [
                'key' => 'house_battery_soc', 'label' => 'House Battery SOC', 'group' => 'power',
                'storage_unit' => 'pct', 'display_unit' => '%', 'volatile' => true,
                'trend_fn' => 'last', 'trend_window' => '2m',
                'staleness_threshold_s' => 300, 'coverage_window_s' => 600, 'coverage_min' => 0.5,
                'enabled' => true, 'description' => 'House battery state of charge in percent (SignalK ratio → %).',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_signalk_electrical_batteries_0_capacity_stateOfCharge', 'label_matchers' => [], 'source_class' => 'both', 'source_kind' => 'ratio', 'select_fn' => 'last', 'unit_transform' => $ratioToPct, 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'house_battery_voltage', 'label' => 'House Battery Voltage', 'group' => 'power',
                'storage_unit' => 'v', 'display_unit' => 'V', 'volatile' => true,
                'trend_fn' => 'last', 'trend_window' => '2m',
                'staleness_threshold_s' => 300, 'coverage_window_s' => 600, 'coverage_min' => 0.5,
                'enabled' => true, 'description' => 'House battery voltage in volts.',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_signalk_electrical_batteries_0_voltage', 'label_matchers' => [], 'source_class' => 'both', 'source_kind' => 'voltage', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'house_battery_current', 'label' => 'House Battery Current', 'group' => 'power',
                'storage_unit' => 'a', 'display_unit' => 'A', 'volatile' => true,
                'trend_fn' => 'last', 'trend_window' => '2m',
                'staleness_threshold_s' => 300, 'coverage_window_s' => 600, 'coverage_min' => 0.5,
                'enabled' => true, 'description' => 'House battery current in amps (positive = charging).',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_signalk_electrical_batteries_0_current', 'label_matchers' => [], 'source_class' => 'both', 'source_kind' => 'current', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'house_battery_time_remaining', 'label' => 'Battery Time Remaining', 'group' => 'power',
                'storage_unit' => 'h', 'display_unit' => 'h', 'volatile' => true,
                'trend_fn' => 'last', 'trend_window' => '2m',
                'staleness_threshold_s' => 300, 'coverage_window_s' => 600, 'coverage_min' => 0.5,
                'enabled' => true, 'description' => 'Estimated time remaining on house battery in hours (SignalK seconds → hours).',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_signalk_electrical_batteries_0_capacity_timeRemaining', 'label_matchers' => [], 'source_class' => 'both', 'source_kind' => 'duration_s', 'select_fn' => 'last', 'unit_transform' => $sToH, 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'engine_battery_voltage', 'label' => 'Engine Battery Voltage', 'group' => 'power',
                'storage_unit' => 'v', 'display_unit' => 'V', 'volatile' => true,
                'trend_fn' => 'last', 'trend_window' => '2m',
                'staleness_threshold_s' => 300, 'coverage_window_s' => 600, 'coverage_min' => 0.5,
                'enabled' => true, 'description' => 'Engine (starter) battery voltage in volts.',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_signalk_electrical_batteries_1_voltage', 'label_matchers' => [], 'source_class' => 'both', 'source_kind' => 'voltage', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],

            // EcoFlow (existing 3 kept; extend with remain_time + voltage)

            [
                'key' => 'ecoflow_soc', 'label' => 'EcoFlow SOC', 'group' => 'power',
                'storage_unit' => 'pct', 'display_unit' => '%', 'volatile' => false,
                'trend_fn' => 'median', 'trend_window' => '10m',
                'staleness_threshold_s' => 3600, 'coverage_window_s' => 3600, 'coverage_min' => 0.3,
                'enabled' => true, 'description' => 'EcoFlow Delta state of charge (BMS actSoc).',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_mqtt_value', 'label_matchers' => [['label' => 'topic', 'op' => 'equals', 'value' => $ecoTopic('actSoc')]], 'source_class' => 'both', 'source_kind' => 'percent', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'ecoflow_input_watts', 'label' => 'EcoFlow Input', 'group' => 'power',
                'storage_unit' => 'w', 'display_unit' => 'W', 'volatile' => false,
                'trend_fn' => 'median', 'trend_window' => '10m',
                'staleness_threshold_s' => 3600, 'coverage_window_s' => 3600, 'coverage_min' => 0.3,
                'enabled' => true, 'description' => 'EcoFlow Delta total input power.',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_mqtt_value', 'label_matchers' => [['label' => 'topic', 'op' => 'equals', 'value' => $ecoTopic('inputWatts')]], 'source_class' => 'both', 'source_kind' => 'watts', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'ecoflow_output_watts', 'label' => 'EcoFlow Output', 'group' => 'power',
                'storage_unit' => 'w', 'display_unit' => 'W', 'volatile' => false,
                'trend_fn' => 'median', 'trend_window' => '10m',
                'staleness_threshold_s' => 3600, 'coverage_window_s' => 3600, 'coverage_min' => 0.3,
                'enabled' => true, 'description' => 'EcoFlow Delta total output power.',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_mqtt_value', 'label_matchers' => [['label' => 'topic', 'op' => 'equals', 'value' => $ecoTopic('outputWatts')]], 'source_class' => 'both', 'source_kind' => 'watts', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'ecoflow_remain_time', 'label' => 'EcoFlow Remaining Time', 'group' => 'power',
                'storage_unit' => 'min', 'display_unit' => 'min', 'volatile' => false,
                'trend_fn' => 'median', 'trend_window' => '10m',
                'staleness_threshold_s' => 3600, 'coverage_window_s' => 3600, 'coverage_min' => 0.3,
                'enabled' => true, 'description' => 'EcoFlow Delta estimated time remaining in minutes (BMS remainTime).',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_mqtt_value', 'label_matchers' => [['label' => 'topic', 'op' => 'equals', 'value' => $ecoTopic('remainTime')]], 'source_class' => 'both', 'source_kind' => 'duration_min', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'ecoflow_voltage', 'label' => 'EcoFlow Voltage', 'group' => 'power',
                'storage_unit' => 'v', 'display_unit' => 'V', 'volatile' => false,
                'trend_fn' => 'median', 'trend_window' => '10m',
                'staleness_threshold_s' => 3600, 'coverage_window_s' => 3600, 'coverage_min' => 0.3,
                'enabled' => true, 'description' => 'EcoFlow Delta battery voltage (BMS vol, mV → V).',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_mqtt_value', 'label_matchers' => [['label' => 'topic', 'op' => 'equals', 'value' => $ecoTopic('vol')]], 'source_class' => 'both', 'source_kind' => 'voltage_mv', 'select_fn' => 'last', 'unit_transform' => [['op' => 'divide', 'value' => 1000]], 'staleness_threshold_s' => null],
                ],
            ],

            // ── Cabin / Environment ───────────────────────────────────────────────

            [
                'key' => 'cabin_temp_forepeak', 'label' => 'Temp: Forepeak', 'group' => 'cabin',
                'storage_unit' => 'c', 'display_unit' => '°C', 'volatile' => false,
                'trend_fn' => 'last', 'trend_window' => '2m',
                'staleness_threshold_s' => 3600, 'coverage_window_s' => 3600, 'coverage_min' => 0.3,
                'enabled' => true, 'description' => 'Forepeak cabin temperature in °C (Zigbee2MQTT).',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_mqtt_temperature', 'label_matchers' => [['label' => 'topic', 'op' => 'equals', 'value' => 'zigbee2mqtt/Forepeak cabin']], 'source_class' => 'both', 'source_kind' => 'temp_c', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'cabin_temp_quarterberth', 'label' => 'Temp: Quarterberth', 'group' => 'cabin',
                'storage_unit' => 'c', 'display_unit' => '°C', 'volatile' => false,
                'trend_fn' => 'last', 'trend_window' => '2m',
                'staleness_threshold_s' => 3600, 'coverage_window_s' => 3600, 'coverage_min' => 0.3,
                'enabled' => true, 'description' => 'Quarterberth cabin temperature in °C (Zigbee2MQTT).',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_mqtt_temperature', 'label_matchers' => [['label' => 'topic', 'op' => 'equals', 'value' => 'zigbee2mqtt/Quarterberth']], 'source_class' => 'both', 'source_kind' => 'temp_c', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'cabin_temp_main', 'label' => 'Temp: Main Cabin', 'group' => 'cabin',
                'storage_unit' => 'c', 'display_unit' => '°C', 'volatile' => false,
                'trend_fn' => 'last', 'trend_window' => '2m',
                'staleness_threshold_s' => 3600, 'coverage_window_s' => 3600, 'coverage_min' => 0.3,
                'enabled' => true, 'description' => 'Main cabin temperature in °C (Zigbee2MQTT).',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_mqtt_temperature', 'label_matchers' => [['label' => 'topic', 'op' => 'equals', 'value' => 'zigbee2mqtt/Main Cabin']], 'source_class' => 'both', 'source_kind' => 'temp_c', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'cabin_humidity_forepeak', 'label' => 'Humidity: Forepeak', 'group' => 'cabin',
                'storage_unit' => 'pct', 'display_unit' => '%', 'volatile' => false,
                'trend_fn' => 'last', 'trend_window' => '2m',
                'staleness_threshold_s' => 3600, 'coverage_window_s' => 3600, 'coverage_min' => 0.3,
                'enabled' => true, 'description' => 'Forepeak cabin humidity in % (Zigbee2MQTT).',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_mqtt_humidity', 'label_matchers' => [['label' => 'topic', 'op' => 'equals', 'value' => 'zigbee2mqtt/Forepeak cabin']], 'source_class' => 'both', 'source_kind' => 'percent', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'cabin_humidity_quarterberth', 'label' => 'Humidity: Quarterberth', 'group' => 'cabin',
                'storage_unit' => 'pct', 'display_unit' => '%', 'volatile' => false,
                'trend_fn' => 'last', 'trend_window' => '2m',
                'staleness_threshold_s' => 3600, 'coverage_window_s' => 3600, 'coverage_min' => 0.3,
                'enabled' => true, 'description' => 'Quarterberth cabin humidity in % (Zigbee2MQTT).',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_mqtt_humidity', 'label_matchers' => [['label' => 'topic', 'op' => 'equals', 'value' => 'zigbee2mqtt/Quarterberth']], 'source_class' => 'both', 'source_kind' => 'percent', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'cabin_humidity_main', 'label' => 'Humidity: Main Cabin', 'group' => 'cabin',
                'storage_unit' => 'pct', 'display_unit' => '%', 'volatile' => false,
                'trend_fn' => 'last', 'trend_window' => '2m',
                'staleness_threshold_s' => 3600, 'coverage_window_s' => 3600, 'coverage_min' => 0.3,
                'enabled' => true, 'description' => 'Main cabin humidity in % (Zigbee2MQTT).',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_mqtt_humidity', 'label_matchers' => [['label' => 'topic', 'op' => 'equals', 'value' => 'zigbee2mqtt/Main Cabin']], 'source_class' => 'both', 'source_kind' => 'percent', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'cabin_pressure_forepeak', 'label' => 'Barometric Pressure', 'group' => 'cabin',
                'storage_unit' => 'hpa', 'display_unit' => 'hPa', 'volatile' => false,
                'trend_fn' => 'last', 'trend_window' => '2m',
                'staleness_threshold_s' => 3600, 'coverage_window_s' => 3600, 'coverage_min' => 0.3,
                'enabled' => true, 'description' => 'Barometric pressure from forepeak sensor in hPa (Zigbee2MQTT).',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_mqtt_pressure', 'label_matchers' => [['label' => 'topic', 'op' => 'equals', 'value' => 'zigbee2mqtt/Forepeak cabin']], 'source_class' => 'both', 'source_kind' => 'pressure_hpa', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'water_temp', 'label' => 'Sea Water Temperature', 'group' => 'environment',
                'storage_unit' => 'c', 'display_unit' => '°C', 'volatile' => true,
                'trend_fn' => 'last', 'trend_window' => '2m',
                'staleness_threshold_s' => 120, 'coverage_window_s' => 300, 'coverage_min' => 0.5,
                'enabled' => true, 'description' => 'Sea water temperature in °C (SignalK Kelvin → °C).',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_signalk_environment_water_temperature', 'label_matchers' => [], 'source_class' => 'both', 'source_kind' => 'temp_k', 'select_fn' => 'last', 'unit_transform' => $kToC, 'staleness_threshold_s' => null],
                ],
            ],

            // ── Weather / Marine ──────────────────────────────────────────────────

            [
                'key' => 'wx_air_temp', 'label' => 'Air Temperature', 'group' => 'weather',
                'storage_unit' => 'c', 'display_unit' => '°C', 'volatile' => false,
                'trend_fn' => 'last', 'trend_window' => '30m',
                'staleness_threshold_s' => 1800, 'coverage_window_s' => 7200, 'coverage_min' => 0.3,
                'enabled' => true, 'description' => 'Forecast air temperature in °C (open-meteo, job="weather").',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_weather_temperature_celsius', 'label_matchers' => $jobWeather, 'source_class' => 'both', 'source_kind' => 'temp_c', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'wx_wind_speed', 'label' => 'Forecast Wind Speed', 'group' => 'weather',
                'storage_unit' => 'kn', 'display_unit' => 'kn', 'volatile' => false,
                'trend_fn' => 'last', 'trend_window' => '30m',
                'staleness_threshold_s' => 1800, 'coverage_window_s' => 7200, 'coverage_min' => 0.3,
                'enabled' => true, 'description' => 'Forecast wind speed in knots (job="weather").',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_weather_wind_speed_kn', 'label_matchers' => $jobWeather, 'source_class' => 'both', 'source_kind' => 'speed_kn', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'wx_wind_gust', 'label' => 'Forecast Wind Gust', 'group' => 'weather',
                'storage_unit' => 'kn', 'display_unit' => 'kn', 'volatile' => false,
                'trend_fn' => 'last', 'trend_window' => '30m',
                'staleness_threshold_s' => 1800, 'coverage_window_s' => 7200, 'coverage_min' => 0.3,
                'enabled' => true, 'description' => 'Forecast wind gust speed in knots (job="weather").',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_weather_wind_gusts_kn', 'label_matchers' => $jobWeather, 'source_class' => 'both', 'source_kind' => 'speed_kn', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'wx_wind_dir', 'label' => 'Forecast Wind Direction', 'group' => 'weather',
                'storage_unit' => 'deg', 'display_unit' => '°', 'volatile' => false,
                'trend_fn' => 'last', 'trend_window' => '30m',
                'staleness_threshold_s' => 1800, 'coverage_window_s' => 7200, 'coverage_min' => 0.3,
                'enabled' => true, 'description' => 'Forecast wind direction in degrees (job="weather").',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_weather_wind_direction_deg', 'label_matchers' => $jobWeather, 'source_class' => 'both', 'source_kind' => 'angle_deg', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'wx_pressure', 'label' => 'Forecast Pressure', 'group' => 'weather',
                'storage_unit' => 'hpa', 'display_unit' => 'hPa', 'volatile' => false,
                'trend_fn' => 'last', 'trend_window' => '30m',
                'staleness_threshold_s' => 1800, 'coverage_window_s' => 7200, 'coverage_min' => 0.3,
                'enabled' => true, 'description' => 'Forecast sea-level pressure in hPa (job="weather").',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_weather_pressure_hpa', 'label_matchers' => $jobWeather, 'source_class' => 'both', 'source_kind' => 'pressure_hpa', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'sea_wave_height', 'label' => 'Wave Height', 'group' => 'weather',
                'storage_unit' => 'm', 'display_unit' => 'm', 'volatile' => false,
                'trend_fn' => 'last', 'trend_window' => '30m',
                'staleness_threshold_s' => 1800, 'coverage_window_s' => 7200, 'coverage_min' => 0.3,
                'enabled' => true, 'description' => 'Significant wave height in metres (job="weather").',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_weather_wave_height_m', 'label_matchers' => $jobWeather, 'source_class' => 'both', 'source_kind' => 'distance_m', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'sea_wave_period', 'label' => 'Wave Period', 'group' => 'weather',
                'storage_unit' => 's', 'display_unit' => 's', 'volatile' => false,
                'trend_fn' => 'last', 'trend_window' => '30m',
                'staleness_threshold_s' => 1800, 'coverage_window_s' => 7200, 'coverage_min' => 0.3,
                'enabled' => true, 'description' => 'Mean wave period in seconds (job="weather").',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_weather_wave_period_s', 'label_matchers' => $jobWeather, 'source_class' => 'both', 'source_kind' => 'duration_s', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'sea_wave_direction', 'label' => 'Wave Direction', 'group' => 'weather',
                'storage_unit' => 'deg', 'display_unit' => '°', 'volatile' => false,
                'trend_fn' => 'last', 'trend_window' => '30m',
                'staleness_threshold_s' => 1800, 'coverage_window_s' => 7200, 'coverage_min' => 0.3,
                'enabled' => true, 'description' => 'Mean wave direction in degrees (job="weather").',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_weather_wave_direction_deg', 'label_matchers' => $jobWeather, 'source_class' => 'both', 'source_kind' => 'angle_deg', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'sea_current_speed', 'label' => 'Sea Current Speed', 'group' => 'weather',
                'storage_unit' => 'kn', 'display_unit' => 'kn', 'volatile' => false,
                'trend_fn' => 'last', 'trend_window' => '30m',
                'staleness_threshold_s' => 1800, 'coverage_window_s' => 7200, 'coverage_min' => 0.3,
                'enabled' => true, 'description' => 'Ocean surface current speed in knots (job="weather").',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_weather_current_speed_kn', 'label_matchers' => $jobWeather, 'source_class' => 'both', 'source_kind' => 'speed_kn', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'sea_current_dir', 'label' => 'Sea Current Direction', 'group' => 'weather',
                'storage_unit' => 'deg', 'display_unit' => '°', 'volatile' => false,
                'trend_fn' => 'last', 'trend_window' => '30m',
                'staleness_threshold_s' => 1800, 'coverage_window_s' => 7200, 'coverage_min' => 0.3,
                'enabled' => true, 'description' => 'Ocean surface current direction in degrees (job="weather").',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_weather_current_direction_deg', 'label_matchers' => $jobWeather, 'source_class' => 'both', 'source_kind' => 'angle_deg', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],

            // ── Tracker (ESP32, job="boat-tracker") ───────────────────────────────

            [
                'key' => 'tracker_battery_voltage', 'label' => 'Tracker Battery', 'group' => 'tracker',
                'storage_unit' => 'v', 'display_unit' => 'V', 'volatile' => true,
                'trend_fn' => 'last', 'trend_window' => '2m',
                'staleness_threshold_s' => 120, 'coverage_window_s' => 300, 'coverage_min' => 0.5,
                'enabled' => true, 'description' => 'ESP32 tracker battery voltage in volts (job="boat-tracker").',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_system_battery_voltage_volts', 'label_matchers' => $jobBoatTracker, 'source_class' => 'both', 'source_kind' => 'voltage', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'tracker_cpu', 'label' => 'Tracker CPU', 'group' => 'tracker',
                'storage_unit' => 'pct', 'display_unit' => '%', 'volatile' => true,
                'trend_fn' => 'last', 'trend_window' => '2m',
                'staleness_threshold_s' => 120, 'coverage_window_s' => 300, 'coverage_min' => 0.5,
                'enabled' => true, 'description' => 'ESP32 tracker CPU usage in % (job="boat-tracker").',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_system_cpu_usage_percent', 'label_matchers' => $jobBoatTracker, 'source_class' => 'both', 'source_kind' => 'percent', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'tracker_free_heap', 'label' => 'Tracker Free Heap', 'group' => 'tracker',
                'storage_unit' => 'bytes', 'display_unit' => 'B', 'volatile' => true,
                'trend_fn' => 'last', 'trend_window' => '2m',
                'staleness_threshold_s' => 120, 'coverage_window_s' => 300, 'coverage_min' => 0.5,
                'enabled' => true, 'description' => 'ESP32 tracker free heap memory in bytes (job="boat-tracker").',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_system_free_heap_bytes', 'label_matchers' => $jobBoatTracker, 'source_class' => 'both', 'source_kind' => 'bytes', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'tracker_temp', 'label' => 'Tracker Temperature', 'group' => 'tracker',
                'storage_unit' => 'c', 'display_unit' => '°C', 'volatile' => true,
                'trend_fn' => 'last', 'trend_window' => '2m',
                'staleness_threshold_s' => 120, 'coverage_window_s' => 300, 'coverage_min' => 0.5,
                'enabled' => true, 'description' => 'ESP32 tracker board temperature in °C (job="boat-tracker").',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_system_temperature_celsius', 'label_matchers' => $jobBoatTracker, 'source_class' => 'both', 'source_kind' => 'temp_c', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'tracker_humidity', 'label' => 'Tracker Humidity', 'group' => 'tracker',
                'storage_unit' => 'pct', 'display_unit' => '%', 'volatile' => true,
                'trend_fn' => 'last', 'trend_window' => '2m',
                'staleness_threshold_s' => 120, 'coverage_window_s' => 300, 'coverage_min' => 0.5,
                'enabled' => true, 'description' => 'ESP32 tracker ambient humidity in % (job="boat-tracker").',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_system_humidity_percent', 'label_matchers' => $jobBoatTracker, 'source_class' => 'both', 'source_kind' => 'percent', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'tracker_lte_connected', 'label' => 'LTE Connected', 'group' => 'tracker',
                'storage_unit' => 'bool', 'display_unit' => '', 'volatile' => true,
                'trend_fn' => 'last', 'trend_window' => '2m',
                'staleness_threshold_s' => 120, 'coverage_window_s' => 300, 'coverage_min' => 0.5,
                'enabled' => true, 'description' => 'LTE connection state (1=connected, job="boat-tracker").',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_system_lte_connected', 'label_matchers' => $jobBoatTracker, 'source_class' => 'both', 'source_kind' => 'bool', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'tracker_lte_rssi', 'label' => 'LTE RSSI', 'group' => 'tracker',
                'storage_unit' => 'dbm', 'display_unit' => 'dBm', 'volatile' => true,
                'trend_fn' => 'last', 'trend_window' => '2m',
                'staleness_threshold_s' => 120, 'coverage_window_s' => 300, 'coverage_min' => 0.5,
                'enabled' => true, 'description' => 'LTE signal strength in dBm (job="boat-tracker").',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_system_lte_rssi_dBm', 'label_matchers' => $jobBoatTracker, 'source_class' => 'both', 'source_kind' => 'dbm', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'tracker_lte_quality', 'label' => 'LTE Signal Quality', 'group' => 'tracker',
                'storage_unit' => 'score', 'display_unit' => '', 'volatile' => true,
                'trend_fn' => 'last', 'trend_window' => '2m',
                'staleness_threshold_s' => 120, 'coverage_window_s' => 300, 'coverage_min' => 0.5,
                'enabled' => true, 'description' => 'LTE signal quality score (job="boat-tracker").',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_system_lte_signal_quality', 'label_matchers' => $jobBoatTracker, 'source_class' => 'both', 'source_kind' => 'score', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'tracker_lte_rat', 'label' => 'LTE Radio Access Type', 'group' => 'tracker',
                'storage_unit' => 'state', 'display_unit' => '', 'volatile' => true,
                'trend_fn' => 'last', 'trend_window' => '2m',
                'staleness_threshold_s' => 120, 'coverage_window_s' => 300, 'coverage_min' => 0.5,
                'enabled' => true, 'description' => 'LTE radio access technology type (job="boat-tracker").',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_system_lte_rat', 'label_matchers' => $jobBoatTracker, 'source_class' => 'both', 'source_kind' => 'state', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'tracker_wifi_connected', 'label' => 'WiFi Connected', 'group' => 'tracker',
                'storage_unit' => 'bool', 'display_unit' => '', 'volatile' => true,
                'trend_fn' => 'last', 'trend_window' => '2m',
                'staleness_threshold_s' => 120, 'coverage_window_s' => 300, 'coverage_min' => 0.5,
                'enabled' => true, 'description' => 'WiFi connection state (1=connected, job="boat-tracker").',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_system_wifi_connected', 'label_matchers' => $jobBoatTracker, 'source_class' => 'both', 'source_kind' => 'bool', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'tracker_wifi_rssi', 'label' => 'WiFi RSSI', 'group' => 'tracker',
                'storage_unit' => 'dbm', 'display_unit' => 'dBm', 'volatile' => true,
                'trend_fn' => 'last', 'trend_window' => '2m',
                'staleness_threshold_s' => 120, 'coverage_window_s' => 300, 'coverage_min' => 0.5,
                'enabled' => true, 'description' => 'WiFi signal strength in dBm (job="boat-tracker").',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_system_wifi_rssi_dBm', 'label_matchers' => $jobBoatTracker, 'source_class' => 'both', 'source_kind' => 'dbm', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'tracker_mode', 'label' => 'Tracker Mode', 'group' => 'tracker',
                'storage_unit' => 'state', 'display_unit' => '', 'volatile' => true,
                'trend_fn' => 'last', 'trend_window' => '2m',
                'staleness_threshold_s' => 120, 'coverage_window_s' => 300, 'coverage_min' => 0.5,
                'enabled' => true, 'description' => 'Tracker operating mode (job="boat-tracker").',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_system_mode', 'label_matchers' => $jobBoatTracker, 'source_class' => 'both', 'source_kind' => 'state', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'tracker_uptime', 'label' => 'Tracker Uptime', 'group' => 'tracker',
                'storage_unit' => 's', 'display_unit' => 's', 'volatile' => true,
                'trend_fn' => 'last', 'trend_window' => '2m',
                'staleness_threshold_s' => 120, 'coverage_window_s' => 300, 'coverage_min' => 0.5,
                'enabled' => true, 'description' => 'Tracker uptime in seconds (job="boat-tracker").',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_system_uptime_seconds', 'label_matchers' => $jobBoatTracker, 'source_class' => 'both', 'source_kind' => 'duration_s', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'tracker_usb_powered', 'label' => 'Tracker USB Power', 'group' => 'tracker',
                'storage_unit' => 'bool', 'display_unit' => '', 'volatile' => true,
                'trend_fn' => 'last', 'trend_window' => '2m',
                'staleness_threshold_s' => 120, 'coverage_window_s' => 300, 'coverage_min' => 0.5,
                'enabled' => true, 'description' => 'Tracker USB power connected (1=yes, job="boat-tracker").',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_system_usb_powered', 'label_matchers' => $jobBoatTracker, 'source_class' => 'both', 'source_kind' => 'bool', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],

            // ── Streaming (SRT, job="scarlet-srt") ───────────────────────────────

            [
                'key' => 'srt_up', 'label' => 'SRT Up', 'group' => 'streaming',
                'storage_unit' => 'bool', 'display_unit' => '', 'volatile' => true,
                'trend_fn' => 'last', 'trend_window' => '1m',
                'staleness_threshold_s' => 60, 'coverage_window_s' => 120, 'coverage_min' => 0.5,
                'enabled' => true, 'description' => 'SRT server up state (1=up, job="scarlet-srt").',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_srt_up', 'label_matchers' => $jobSrt, 'source_class' => 'both', 'source_kind' => 'bool', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'srt_pub_connected', 'label' => 'SRT Publisher Connected', 'group' => 'streaming',
                'storage_unit' => 'bool', 'display_unit' => '', 'volatile' => true,
                'trend_fn' => 'last', 'trend_window' => '1m',
                'staleness_threshold_s' => 60, 'coverage_window_s' => 120, 'coverage_min' => 0.5,
                'enabled' => true, 'description' => 'SRT publisher connected (1=yes, job="scarlet-srt").',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_srt_publisher_connected', 'label_matchers' => $jobSrt, 'source_class' => 'both', 'source_kind' => 'bool', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'srt_pub_bitrate', 'label' => 'SRT Publisher Bitrate', 'group' => 'streaming',
                'storage_unit' => 'bps', 'display_unit' => 'bps', 'volatile' => true,
                'trend_fn' => 'last', 'trend_window' => '1m',
                'staleness_threshold_s' => 60, 'coverage_window_s' => 120, 'coverage_min' => 0.5,
                'enabled' => true, 'description' => 'SRT publisher stream bitrate in bps (job="scarlet-srt").',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_srt_publisher_bitrate_bps', 'label_matchers' => $jobSrt, 'source_class' => 'both', 'source_kind' => 'bps', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'srt_pub_rtt', 'label' => 'SRT Publisher RTT', 'group' => 'streaming',
                'storage_unit' => 'ms', 'display_unit' => 'ms', 'volatile' => true,
                'trend_fn' => 'last', 'trend_window' => '1m',
                'staleness_threshold_s' => 60, 'coverage_window_s' => 120, 'coverage_min' => 0.5,
                'enabled' => true, 'description' => 'SRT publisher round-trip time in ms (job="scarlet-srt").',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_srt_publisher_rtt_ms', 'label_matchers' => $jobSrt, 'source_class' => 'both', 'source_kind' => 'duration_ms', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'srt_pub_latency', 'label' => 'SRT Publisher Latency', 'group' => 'streaming',
                'storage_unit' => 'ms', 'display_unit' => 'ms', 'volatile' => true,
                'trend_fn' => 'last', 'trend_window' => '1m',
                'staleness_threshold_s' => 60, 'coverage_window_s' => 120, 'coverage_min' => 0.5,
                'enabled' => true, 'description' => 'SRT publisher negotiated latency in ms (job="scarlet-srt").',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_srt_publisher_latency_ms', 'label_matchers' => $jobSrt, 'source_class' => 'both', 'source_kind' => 'duration_ms', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'srt_pub_dropped', 'label' => 'SRT Publisher Dropped', 'group' => 'streaming',
                'storage_unit' => 'count', 'display_unit' => '', 'volatile' => true,
                'trend_fn' => 'last', 'trend_window' => '1m',
                'staleness_threshold_s' => 60, 'coverage_window_s' => 120, 'coverage_min' => 0.5,
                'enabled' => true, 'description' => 'SRT publisher cumulative dropped packets (job="scarlet-srt").',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_srt_publisher_dropped_packets_total', 'label_matchers' => $jobSrt, 'source_class' => 'both', 'source_kind' => 'count', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'srt_pub_network', 'label' => 'SRT Publisher Network', 'group' => 'streaming',
                'storage_unit' => 'bps', 'display_unit' => 'bps', 'volatile' => true,
                'trend_fn' => 'last', 'trend_window' => '1m',
                'staleness_threshold_s' => 60, 'coverage_window_s' => 120, 'coverage_min' => 0.5,
                'enabled' => true, 'description' => 'SRT publisher total network bytes/s (job="scarlet-srt").',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_srt_publisher_network_bps', 'label_matchers' => $jobSrt, 'source_class' => 'both', 'source_kind' => 'bps', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'srt_con_bitrate', 'label' => 'SRT Consumer Bitrate', 'group' => 'streaming',
                'storage_unit' => 'bps', 'display_unit' => 'bps', 'volatile' => true,
                'trend_fn' => 'last', 'trend_window' => '1m',
                'staleness_threshold_s' => 60, 'coverage_window_s' => 120, 'coverage_min' => 0.5,
                'enabled' => true, 'description' => 'SRT consumer (relay/ingest) stream bitrate in bps (job="scarlet-srt").',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_srt_consumer_bitrate_bps', 'label_matchers' => $jobSrt, 'source_class' => 'both', 'source_kind' => 'bps', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'srt_con_rtt', 'label' => 'SRT Consumer RTT', 'group' => 'streaming',
                'storage_unit' => 'ms', 'display_unit' => 'ms', 'volatile' => true,
                'trend_fn' => 'last', 'trend_window' => '1m',
                'staleness_threshold_s' => 60, 'coverage_window_s' => 120, 'coverage_min' => 0.5,
                'enabled' => true, 'description' => 'SRT consumer round-trip time in ms (job="scarlet-srt").',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_srt_consumer_rtt_ms', 'label_matchers' => $jobSrt, 'source_class' => 'both', 'source_kind' => 'duration_ms', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'srt_con_latency', 'label' => 'SRT Consumer Latency', 'group' => 'streaming',
                'storage_unit' => 'ms', 'display_unit' => 'ms', 'volatile' => true,
                'trend_fn' => 'last', 'trend_window' => '1m',
                'staleness_threshold_s' => 60, 'coverage_window_s' => 120, 'coverage_min' => 0.5,
                'enabled' => true, 'description' => 'SRT consumer negotiated latency in ms (job="scarlet-srt").',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_srt_consumer_latency_ms', 'label_matchers' => $jobSrt, 'source_class' => 'both', 'source_kind' => 'duration_ms', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'srt_con_dropped', 'label' => 'SRT Consumer Dropped', 'group' => 'streaming',
                'storage_unit' => 'count', 'display_unit' => '', 'volatile' => true,
                'trend_fn' => 'last', 'trend_window' => '1m',
                'staleness_threshold_s' => 60, 'coverage_window_s' => 120, 'coverage_min' => 0.5,
                'enabled' => true, 'description' => 'SRT consumer cumulative dropped packets (job="scarlet-srt").',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_srt_consumer_dropped_packets_total', 'label_matchers' => $jobSrt, 'source_class' => 'both', 'source_kind' => 'count', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],
        ];
    }
}
