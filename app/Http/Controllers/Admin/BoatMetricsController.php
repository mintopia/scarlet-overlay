<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Journey;
use App\Services\MetricRegistry;
use App\Services\MetricsService;
use Inertia\Inertia;

class BoatMetricsController extends Controller
{
    public function index(MetricsService $metrics, MetricRegistry $registry)
    {
        $journey = Journey::current();

        return Inertia::render('Admin/BoatMetrics', [
            'boat' => $metrics->getBoatMetrics(),
            'batteryHistory' => $registry->fetchRange('house_battery_voltage', '300s', start: now()->subHours(24)->timestamp),
            'batteryPowerHistory' => $registry->fetchRange('house_battery_current', '300s', start: now()->subHours(24)->timestamp),
            'speedHistory' => $registry->fetchRangeWithFallback('speed_sog', '300s', start: now()->subHours(24)->timestamp),
            'tempHistoryForepeak' => $registry->fetchRange('cabin_temp_forepeak', '300s', start: now()->subHours(24)->timestamp),
            'tempHistoryQuarterberth' => $registry->fetchRange('cabin_temp_quarterberth', '300s', start: now()->subHours(24)->timestamp),
            'tempHistoryMainCabin' => $registry->fetchRange('cabin_temp_main', '300s', start: now()->subHours(24)->timestamp),
            'humidityHistoryForepeak' => $registry->fetchRange('cabin_humidity_forepeak', '300s', start: now()->subHours(24)->timestamp),
            'humidityHistoryQuarterberth' => $registry->fetchRange('cabin_humidity_quarterberth', '300s', start: now()->subHours(24)->timestamp),
            'humidityHistoryMainCabin' => $registry->fetchRange('cabin_humidity_main', '300s', start: now()->subHours(24)->timestamp),
            'fuelHistory' => $registry->fetchRange('fuel_level', '300s', start: now()->subHours(24)->timestamp),
            'waterHistory' => $registry->fetchRange('water_level', '300s', start: now()->subHours(24)->timestamp),
            'tripDistance' => $journey?->distance,
        ]);
    }
}
