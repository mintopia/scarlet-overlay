<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Journey;
use App\Services\MetricsService;
use App\Services\PrometheusService;
use Inertia\Inertia;

class BoatMetricsController extends Controller
{
    public function index(MetricsService $metrics, PrometheusService $prometheus)
    {
        $boat = config('scarlet.metrics.mappings.boat');
        $wrap = fn (string $q) => $prometheus->wrapForRange($q);
        $journey = Journey::current();

        return Inertia::render('Admin/BoatMetrics', [
            'boat' => $metrics->getBoatMetrics(),
            'batteryHistory' => $prometheus->queryRange($wrap($boat['house_battery_voltage']), '24h', '300s'),
            'batteryPowerHistory' => $prometheus->queryRange($wrap($boat['house_battery_current']).' * '.$wrap($boat['house_battery_voltage']), '24h', '300s'),
            'speedHistory' => $prometheus->queryRangeWithFallback($wrap($boat['speed_sog']), $wrap('scarlet_gps_speed_kn'), '24h', '300s'),
            'tempHistoryForepeak' => $prometheus->queryRange($wrap($boat['cabin_temp_forepeak']), '24h', '300s'),
            'tempHistoryQuarterberth' => $prometheus->queryRange($wrap($boat['cabin_temp_quarterberth']), '24h', '300s'),
            'tempHistoryMainCabin' => $prometheus->queryRange($wrap($boat['cabin_temp_main']), '24h', '300s'),
            'humidityHistoryForepeak' => $prometheus->queryRange($wrap($boat['cabin_humidity_forepeak']), '24h', '300s'),
            'humidityHistoryQuarterberth' => $prometheus->queryRange($wrap($boat['cabin_humidity_quarterberth']), '24h', '300s'),
            'humidityHistoryMainCabin' => $prometheus->queryRange($wrap($boat['cabin_humidity_main']), '24h', '300s'),
            'fuelHistory' => $prometheus->queryRange($wrap($boat['fuel_level']), '24h', '300s'),
            'waterHistory' => $prometheus->queryRange($wrap($boat['water_level']), '24h', '300s'),
            'tripDistance' => $journey?->distance,
        ]);
    }
}
