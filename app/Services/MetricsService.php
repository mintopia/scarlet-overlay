<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class MetricsService
{
    public function __construct(protected PrometheusService $prometheus) {}

    public function getBoatMetrics(): array
    {
        return $this->prometheus->queryMultiple([
            'speed_sog' => 'scarlet_gps_speed_kn',
            'heading' => 'scarlet_gps_heading_deg',
            'depth' => 'scarlet_gps_altitude_meters',
            'air_temp' => 'scarlet_environment_temperature_celsius',
        ]);
    }

    public function getTrackerMetrics(): array
    {
        return $this->prometheus->queryMultiple([
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
        ]);
    }

    public function getGpsMetrics(): array
    {
        return $this->prometheus->queryMultiple([
            'latitude' => 'scarlet_gps_latitude_deg',
            'longitude' => 'scarlet_gps_longitude_deg',
            'altitude' => 'scarlet_gps_altitude_meters',
            'satellites' => 'scarlet_gps_satellites',
            'hdop' => 'scarlet_gps_hdop',
            'speed' => 'scarlet_gps_speed_kn',
            'heading' => 'scarlet_gps_heading_deg',
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
