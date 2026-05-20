<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\MetricsService;
use App\Services\PrometheusService;
use Inertia\Inertia;

class BoatMetricsController extends Controller
{
    public function index(MetricsService $metrics, PrometheusService $prometheus)
    {
        return Inertia::render('Admin/BoatMetrics', [
            'boat' => $metrics->getBoatMetrics(),
            'batteryHistory' => $prometheus->queryRange('scarlet_system_battery_voltage_volts', '24h', '300s'),
            'tempHistory' => $prometheus->queryRange('scarlet_environment_temperature_celsius', '24h', '300s'),
            'humidityHistory' => $prometheus->queryRange('scarlet_environment_humidity_percent', '24h', '300s'),
            'speedHistory' => $prometheus->queryRange('scarlet_gps_speed_kn', '24h', '300s'),
        ]);
    }
}
