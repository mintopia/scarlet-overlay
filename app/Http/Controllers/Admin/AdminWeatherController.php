<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\MetricsService;
use Inertia\Inertia;

class AdminWeatherController extends Controller
{
    public function index(MetricsService $metrics)
    {
        return Inertia::render('Admin/Weather', [
            'weather' => $metrics->getWeatherData(),
            'boat' => $metrics->getBoatMetrics(),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
