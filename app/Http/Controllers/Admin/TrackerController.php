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
        $tracker = config('scarlet.metrics.mappings.tracker');
        $gps = config('scarlet.metrics.mappings.gps');
        $wrap = fn (string $q) => $prometheus->wrapForRange($q);

        return Inertia::render('Admin/Tracker', [
            'tracker' => $metrics->getTrackerMetrics(),
            'gps' => $metrics->getGpsMetrics(),
            'signalHistory' => [
                'lte' => $prometheus->queryRange($wrap($tracker['lte_rssi']), '1h', '60s'),
                'wifi' => $prometheus->queryRange($wrap($tracker['wifi_rssi']), '1h', '60s'),
            ],
            'gpsHistory' => $prometheus->queryRange($wrap($gps['satellites']), '1h', '60s'),
            'cpuHistory' => $prometheus->queryRange($wrap($tracker['cpu_usage']), '1h', '60s'),
            'tempHistory' => $prometheus->queryRange($wrap($tracker['cabin_temp']), '6h', '120s'),
            'humidityHistory' => $prometheus->queryRange($wrap($tracker['cabin_humidity']), '6h', '120s'),
        ]);
    }
}
