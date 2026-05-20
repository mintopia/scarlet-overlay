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
        $history = config('scarlet.metrics.mappings.history');

        return Inertia::render('Admin/BoatMetrics', [
            'boat' => $metrics->getBoatMetrics(),
            'batteryHistory' => $prometheus->queryRange($history['battery'], '24h', '300s'),
            'tempHistory' => $prometheus->queryRange($history['temperature'], '24h', '300s'),
            'humidityHistory' => $prometheus->queryRange($history['humidity'], '24h', '300s'),
            'speedHistory' => $prometheus->queryRange($history['speed'], '24h', '300s'),
        ]);
    }
}
