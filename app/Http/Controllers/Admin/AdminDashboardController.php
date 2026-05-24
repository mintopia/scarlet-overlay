<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Journey;
use App\Services\MetricsService;
use App\Services\PrometheusService;
use Inertia\Inertia;

class AdminDashboardController extends Controller
{
    public function index(MetricsService $metrics, PrometheusService $prometheus)
    {
        $activeJourney = Journey::current();
        $plannedJourney = $activeJourney ? null : Journey::planned();
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

        $routeJourney = $activeJourney
            ?? $plannedJourney
            ?? Journey::completed()->whereNotNull('route_waypoints')->orderByDesc('ended_at')->first();

        return Inertia::render('Admin/Dashboard', [
            'boat' => $metrics->getBoatMetrics(),
            'gps' => $metrics->getGpsMetrics(),
            'tracker' => $metrics->getTrackerMetrics(),
            'weather' => $metrics->getWeatherData(),
            'activeJourney' => $activeJourney ? [
                'id' => $activeJourney->id,
                'title' => $activeJourney->title,
                'from_port' => $activeJourney->from_port,
                'to_port' => $activeJourney->to_port,
                'started_at' => $activeJourney->started_at->toIso8601String(),
                'distance' => $activeJourney->distance,
                'duration' => $activeJourney->duration,
            ] : null,
            'plannedJourney' => $plannedJourney ? [
                'id' => $plannedJourney->id,
                'title' => $plannedJourney->title,
                'from_port' => $plannedJourney->from_port,
                'to_port' => $plannedJourney->to_port,
                'has_gpx' => $plannedJourney->gpx_route_path !== null,
            ] : null,
            'routeWaypoints' => $routeJourney?->route_waypoints ?? [],
            'recentJourneys' => $recentJourneys,
            'streamOnline' => (bool) $prometheus->query('scarlet_srt_up'),
            'streamPublisher' => (bool) $prometheus->query('scarlet_srt_publisher_connected'),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
