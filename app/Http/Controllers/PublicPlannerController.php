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
            'plan' => $plan->toDetailArray(),
            'defaultCenter' => [$defaultLat, $defaultLng],
        ]);
    }
}
