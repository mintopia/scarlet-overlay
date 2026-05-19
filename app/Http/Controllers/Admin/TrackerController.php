<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\MetricsService;
use App\Services\PrometheusService;
use Inertia\Inertia;

class TrackerController extends Controller
{
    public function index(MetricsService $metrics, PrometheusService $prometheus)
    {
        return Inertia::render('Admin/Tracker', [
            'tracker' => $metrics->getTrackerMetrics(),
            'gps' => $metrics->getGpsMetrics(),
            'signalHistory' => [
                'lte' => $prometheus->queryRange('scarlet_system_lte_rssi_dbm', '1h', '60s'),
                'wifi' => $prometheus->queryRange('scarlet_system_wifi_rssi_dbm', '1h', '60s'),
            ],
            'gpsHistory' => $prometheus->queryRange('scarlet_gps_satellites', '1h', '60s'),
            'tempHistory' => $prometheus->queryRange('scarlet_environment_temperature_c', '6h', '120s'),
            'humidityHistory' => $prometheus->queryRange('scarlet_environment_humidity_pct', '6h', '120s'),
        ]);
    }
}
