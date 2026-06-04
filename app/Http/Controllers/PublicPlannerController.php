<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Services\MetricsService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PublicPlannerController extends Controller
{
    public function show(Request $request, Plan $plan, MetricsService $metrics)
    {
        if (! $plan->share_token || $request->query('token') !== $plan->share_token) {
            abort(404);
        }

        $plan->load(['groups.routes']);

        $gps = $metrics->getGpsMetrics();
        $defaultLat = $gps['latitude'] ?? 37.1028;
        $defaultLng = $gps['longitude'] ?? -8.6740;

        return Inertia::render('Public/Planner', [
            'plan' => [
                'id' => $plan->id,
                'slug' => $plan->slug,
                'title' => $plan->title,
                'created_at' => $plan->created_at->toIso8601String(),
                'groups' => $plan->groups->map(fn ($g) => [
                    'id' => $g->id,
                    'name' => $g->name,
                    'color_index' => $g->color_index,
                    'routes' => $g->routes->map(fn ($r) => [
                        'id' => $r->id,
                        'name' => $r->name,
                        'distance_nm' => (float) $r->distance_nm,
                        'is_enabled' => $r->is_enabled,
                        'color_index' => $r->color_index,
                        'track_points' => $r->track_points,
                        'waypoints' => $r->waypoints,
                    ]),
                ]),
            ],
            'defaultCenter' => [$defaultLat, $defaultLng],
        ]);
    }
}
