<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class MetricsService
{
    public function __construct(protected PrometheusService $prometheus) {}

    public function getBoatMetrics(): array
    {
        return $this->prometheus->queryMultiple([
            'speed_sog' => 'scarlet_boat_speed_kn',
            'speed_stw' => 'scarlet_signalk_navigation_speed_through_water_kn',
            'heading' => 'scarlet_boat_heading_deg',
            'cog' => 'scarlet_signalk_navigation_course_over_ground_deg',
            'depth' => 'scarlet_boat_depth_m',
            'wind_speed_true' => 'scarlet_boat_wind_speed_kn',
            'wind_direction_true' => 'scarlet_boat_wind_direction_deg',
            'wind_speed_apparent' => 'scarlet_signalk_environment_wind_speed_apparent_kn',
            'wind_angle_apparent' => 'scarlet_signalk_environment_wind_angle_apparent_deg',
            'heel' => 'scarlet_signalk_navigation_attitude_heel_deg',
            'rudder' => 'scarlet_signalk_steering_rudder_angle_deg',
            'rate_of_turn' => 'scarlet_signalk_navigation_rate_of_turn_deg_per_min',
            'water_temp' => 'scarlet_signalk_environment_water_temperature_c',
            'pressure' => 'scarlet_signalk_environment_outside_pressure_hpa',
            'house_battery_voltage' => 'scarlet_signalk_electrical_batteries_house_voltage',
            'house_battery_soc' => 'scarlet_signalk_electrical_batteries_house_soc',
            'house_battery_current' => 'scarlet_signalk_electrical_batteries_house_current',
            'engine_battery_voltage' => 'scarlet_signalk_electrical_batteries_engine_voltage',
            'solar_power' => 'scarlet_signalk_electrical_solar_power_w',
            'solar_current' => 'scarlet_signalk_electrical_solar_current_a',
            'solar_voltage' => 'scarlet_signalk_electrical_solar_voltage_v',
            'load_current' => 'scarlet_signalk_electrical_load_current_a',
            'fuel_level' => 'scarlet_boat_fuel_level_pct',
            'water_level' => 'scarlet_boat_water_level_pct',
            'engine_rpm' => 'scarlet_signalk_propulsion_engine_revolutions',
            'engine_hours' => 'scarlet_signalk_propulsion_engine_run_time_h',
            'engine_coolant_temp' => 'scarlet_signalk_propulsion_engine_coolant_temp_c',
            'trip_log' => 'scarlet_signalk_navigation_trip_log_nm',
            'air_temp' => 'scarlet_signalk_environment_outside_temperature_c',
        ]);
    }

    public function getTrackerMetrics(): array
    {
        return $this->prometheus->queryMultiple([
            'battery_voltage' => 'scarlet_system_battery_voltage_volts',
            'battery_percent' => 'scarlet_system_battery_percent',
            'lte_rssi' => 'scarlet_system_lte_rssi_dbm',
            'wifi_rssi' => 'scarlet_system_wifi_rssi_dbm',
            'uptime' => 'scarlet_system_uptime_seconds',
            'heap_free' => 'scarlet_system_heap_free_bytes',
            'cabin_temp' => 'scarlet_environment_temperature_c',
            'cabin_humidity' => 'scarlet_environment_humidity_pct',
        ]);
    }

    public function getGpsMetrics(): array
    {
        return $this->prometheus->queryMultiple([
            'latitude' => 'scarlet_gps_latitude',
            'longitude' => 'scarlet_gps_longitude',
            'satellites' => 'scarlet_gps_satellites',
            'hdop' => 'scarlet_gps_hdop',
        ]);
    }

    public function getAllMetrics(): array
    {
        return [
            'boat' => $this->getBoatMetrics(),
            'tracker' => $this->getTrackerMetrics(),
            'gps' => $this->getGpsMetrics(),
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
