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
            'pressureHistory' => $prometheus->queryRange('scarlet_signalk_environment_outside_pressure_hpa', '24h', '300s'),
            'batteryHistory' => $prometheus->queryRange('scarlet_signalk_electrical_batteries_house_soc', '24h', '300s'),
            'waterTempHistory' => $prometheus->queryRange('scarlet_signalk_environment_water_temperature_c', '24h', '300s'),
            'airTempHistory' => $prometheus->queryRange('scarlet_signalk_environment_outside_temperature_c', '24h', '300s'),
        ]);
    }
}
