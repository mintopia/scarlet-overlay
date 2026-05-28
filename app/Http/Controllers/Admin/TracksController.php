<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Journey;
use App\Services\MetricsService;
use Carbon\CarbonInterval;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TracksController extends Controller
{
    public function index(Request $request, MetricsService $metrics)
    {
        $activeJourney = Journey::current();
        $period = $request->input('period', $activeJourney ? 'journey' : '24h');
        $timeRanges = ['24h', '48h', '7d', '30d'];

        $journey = null;
        if (str_starts_with($period, 'journey:')) {
            $journeyId = (int) str_replace('journey:', '', $period);
            $journey = Journey::find($journeyId);
        } elseif ($period === 'journey') {
            $journey = $activeJourney;
        }

        if ($journey?->started_at) {
            $start = $journey->started_at->timestamp;
            $end = ($journey->ended_at ?? now())->timestamp;
        } else {
            if (! in_array($period, $timeRanges)) {
                $period = '24h';
            }
            $seconds = CarbonInterval::fromString($period)->totalSeconds;
            $start = now()->subSeconds((int) $seconds)->timestamp;
            $end = now()->timestamp;
        }

        $duration = $end - $start;
        $targetPoints = 8000;
        $step = max(15, (int) ceil($duration / $targetPoints)).'s';

        $gpsTrack = $metrics->getGpsTrack(null, $step, $start, $end);

        $allJourneys = Journey::whereNotNull('started_at')
            ->orderByDesc('started_at')
            ->get(['id', 'title', 'status', 'route_waypoints'])
            ->map(fn (Journey $j) => [
                'id' => $j->id,
                'title' => $j->title,
                'active' => $j->status === 'active',
                'route_waypoints' => $j->route_waypoints,
            ]);

        $visibleJourneys = Journey::whereNotNull('started_at')
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('started_at', [date('Y-m-d H:i:s', $start), date('Y-m-d H:i:s', $end)])
                    ->orWhereBetween('ended_at', [date('Y-m-d H:i:s', $start), date('Y-m-d H:i:s', $end)])
                    ->orWhere(function ($q2) use ($start, $end) {
                        $q2->where('started_at', '<=', date('Y-m-d H:i:s', $start))
                            ->where(function ($q3) use ($end) {
                                $q3->where('ended_at', '>=', date('Y-m-d H:i:s', $end))
                                    ->orWhereNull('ended_at');
                            });
                    });
            })
            ->whereNotNull('route_waypoints')
            ->get(['route_waypoints']);

        $routeWaypoints = $visibleJourneys->pluck('route_waypoints')->filter()->values()->all();

        return Inertia::render('Admin/Tracks', [
            'gpsTrack' => $gpsTrack,
            'journeys' => $allJourneys,
            'period' => $period,
            'routeWaypoints' => $routeWaypoints,
        ]);
    }
}
