<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\MetricsService;
use App\Services\PrometheusService;
use Inertia\Inertia;

class SkipperOverviewController extends Controller
{
    public function index(MetricsService $metrics, PrometheusService $prometheus)
    {
        $boat = $metrics->getBoatMetrics();
        $gps = $metrics->getGpsMetrics();
        $weather = $metrics->getWeatherData();

        $depthHistory = $prometheus->queryRange(
            config('scarlet.metrics.mappings.boat.depth'),
            '1h',
            '60s'
        );

        $powerHistory = $prometheus->queryRange(
            'scarlet_signalk_electrical_batteries_0_voltage * scarlet_signalk_electrical_batteries_0_current',
            '1h',
            '60s'
        );

        $pressureHistory = $prometheus->queryRange(
            config('scarlet.metrics.mappings.history.pressure_forepeak'),
            '24h',
            '15m'
        );

        $speedHistory = $prometheus->queryRange(
            config('scarlet.metrics.mappings.boat.speed_sog'),
            '1h',
            '60s'
        );

        return Inertia::render('Admin/SkipperOverview', [
            'boat' => $boat,
            'gps' => $gps,
            'weather' => $weather,
            'depthHistory' => $depthHistory,
            'powerHistory' => $powerHistory,
            'pressureHistory' => $pressureHistory,
            'speedHistory' => $speedHistory,
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
