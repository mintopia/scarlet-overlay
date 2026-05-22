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
            'speedHistory' => $prometheus->queryRange($history['speed'], '24h', '300s'),
            'tempHistoryForepeak' => $prometheus->queryRange($history['temp_forepeak'], '24h', '300s'),
            'tempHistoryQuarterberth' => $prometheus->queryRange($history['temp_quarterberth'], '24h', '300s'),
            'tempHistoryMainCabin' => $prometheus->queryRange($history['temp_main_cabin'], '24h', '300s'),
            'humidityHistoryForepeak' => $prometheus->queryRange($history['humidity_forepeak'], '24h', '300s'),
            'humidityHistoryQuarterberth' => $prometheus->queryRange($history['humidity_quarterberth'], '24h', '300s'),
            'humidityHistoryMainCabin' => $prometheus->queryRange($history['humidity_main_cabin'], '24h', '300s'),
        ]);
    }
}
