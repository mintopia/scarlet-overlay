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
        $mappings = config('scarlet.metrics.mappings');

        return Inertia::render('Admin/Tracker', [
            'tracker' => $metrics->getTrackerMetrics(),
            'gps' => $metrics->getGpsMetrics(),
            'signalHistory' => [
                'lte' => $prometheus->queryRange($mappings['tracker']['lte_rssi'], '1h', '60s'),
                'wifi' => $prometheus->queryRange($mappings['tracker']['wifi_rssi'], '1h', '60s'),
            ],
            'gpsHistory' => $prometheus->queryRange($mappings['gps']['satellites'], '1h', '60s'),
            'cpuHistory' => $prometheus->queryRange($mappings['history']['cpu_usage'], '1h', '60s'),
            'tempHistory' => $prometheus->queryRange($mappings['tracker']['cabin_temp'], '6h', '120s'),
            'humidityHistory' => $prometheus->queryRange($mappings['tracker']['cabin_humidity'], '6h', '120s'),
        ]);
    }
}
