<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\MetricRegistry;
use App\Services\MetricsService;
use Inertia\Inertia;

class TrackerController extends Controller
{
    public function index(MetricsService $metrics, MetricRegistry $registry)
    {
        return Inertia::render('Admin/Tracker', [
            'tracker' => $metrics->getTrackerMetrics(),
            'gps' => $metrics->getGpsMetrics(),
            'signalHistory' => [
                'lte' => $registry->fetchRange('tracker_lte_rssi', '60s', start: now()->subHour()->timestamp),
                'wifi' => $registry->fetchRange('tracker_wifi_rssi', '60s', start: now()->subHour()->timestamp),
            ],
            'gpsHistory' => $registry->fetchRange('gps_satellites', '60s', start: now()->subHour()->timestamp),
            'cpuHistory' => $registry->fetchRange('tracker_cpu', '60s', start: now()->subHour()->timestamp),
            'tempHistory' => $registry->fetchRange('cabin_temp_forepeak', '120s', start: now()->subHours(6)->timestamp),
            'humidityHistory' => $registry->fetchRange('cabin_humidity_forepeak', '120s', start: now()->subHours(6)->timestamp),
        ]);
    }
}
