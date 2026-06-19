<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Services\CanonicalReader;

trait MapsLegacyMetricKeys
{
    /**
     * Legacy registry metric key (as referenced by the explore mappings config) →
     * canonical catalog key. The canonical reader is the single source of values.
     *
     * @var array<string, string>
     */
    private static array $legacyToCanonical = [
        'speed_sog' => 'speed_sog',
        'speed_stw' => 'speed_stw',
        'heading' => 'heading_true',
        'heading_magnetic' => 'heading_magnetic',
        'cog' => 'cog',
        'depth' => 'depth_below_surface',
        'heel' => 'heel',
        'pitch' => 'pitch',
        'trip_log' => 'trip_log',
        'nav_wp_distance' => 'wp_distance',
        'nav_wp_ttg' => 'wp_ttg',
        'vmg' => 'vmg',
        'wind_speed_apparent' => 'wind_speed_apparent',
        'wind_angle_apparent' => 'wind_angle_apparent',
        'magnetic_variation' => 'magnetic_variation',
        'water_temp' => 'water_temp',
        'house_battery_voltage' => 'house_battery_voltage',
        'house_battery_soc' => 'house_battery_soc',
        'house_battery_current' => 'house_battery_current',
        'house_battery_time_remaining' => 'house_battery_time_remaining',
        'engine_battery_voltage' => 'engine_battery_voltage',
        'fuel_level' => 'fuel_level',
        'water_level' => 'water_fresh_level',
        'cabin_temp_quarterberth' => 'cabin_temp_quarterberth',
        'cabin_humidity_quarterberth' => 'cabin_humidity_quarterberth',
        'cabin_temp_main' => 'cabin_temp_main',
        'cabin_humidity_main' => 'cabin_humidity_main',
        'cabin_temp_forepeak' => 'cabin_temp_forepeak',
        'cabin_humidity_forepeak' => 'cabin_humidity_forepeak',
        'cabin_pressure_forepeak' => 'cabin_pressure_forepeak',
        'gps_latitude' => 'position_latitude',
        'gps_longitude' => 'position_longitude',
        'gps_altitude' => 'gps_altitude',
        'gps_satellites' => 'gps_satellites',
        'gps_hdop' => 'gps_hdop',
        'gps_speed' => 'gps_speed',
        'gps_heading' => 'gps_heading',
        'tracker_battery' => 'tracker_battery_voltage',
        'tracker_usb' => 'tracker_usb_powered',
        'tracker_lte_connected' => 'tracker_lte_connected',
        'tracker_lte_rssi' => 'tracker_lte_rssi',
        'tracker_lte_quality' => 'tracker_lte_quality',
        'tracker_lte_rat' => 'tracker_lte_rat',
        'tracker_wifi_connected' => 'tracker_wifi_connected',
        'tracker_wifi_rssi' => 'tracker_wifi_rssi',
        'tracker_uptime' => 'tracker_uptime',
        'tracker_heap' => 'tracker_free_heap',
        'tracker_mode' => 'tracker_mode',
        'tracker_cpu' => 'tracker_cpu',
    ];

    private function canonicalKey(string $legacyKey): ?string
    {
        return self::$legacyToCanonical[$legacyKey] ?? null;
    }

    /**
     * Resolve a canonical range series into the legacy {timestamp,value} point shape.
     *
     * @return array<int, array{timestamp: int, value: float}>
     */
    private function legacyRange(CanonicalReader $canonical, string $legacyKey, string $step, ?int $start, ?int $end): array
    {
        $canonicalKey = $this->canonicalKey($legacyKey);
        if ($canonicalKey === null) {
            return [];
        }

        return array_map(
            fn (array $p): array => ['timestamp' => $p['t'], 'value' => $p['v']],
            $canonical->readRange($canonicalKey, null, $step, $start, $end),
        );
    }
}
