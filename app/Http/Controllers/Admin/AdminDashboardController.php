<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Journey;
use App\Services\MetricsService;
use Inertia\Inertia;

class AdminDashboardController extends Controller
{
    public function index(MetricsService $metrics)
    {
        $activeJourney = Journey::current();
        $recentJourneys = Journey::completed()
            ->orderByDesc('ended_at')
            ->take(5)
            ->get()
            ->map(fn (Journey $j) => [
                'id' => $j->id,
                'slug' => $j->slug,
                'title' => $j->title,
                'started_at' => $j->started_at->toIso8601String(),
                'ended_at' => $j->ended_at?->toIso8601String(),
                'distance' => $j->distance,
                'duration' => $j->duration,
            ]);

        return Inertia::render('Admin/Dashboard', [
            'boat' => $metrics->getBoatMetrics(),
            'gps' => $metrics->getGpsMetrics(),
            'tracker' => $metrics->getTrackerMetrics(),
            'activeJourney' => $activeJourney ? [
                'id' => $activeJourney->id,
                'title' => $activeJourney->title,
                'from_port' => $activeJourney->from_port,
                'to_port' => $activeJourney->to_port,
                'started_at' => $activeJourney->started_at->toIso8601String(),
                'distance' => $activeJourney->distance,
                'duration' => $activeJourney->duration,
            ] : null,
            'recentJourneys' => $recentJourneys,
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
