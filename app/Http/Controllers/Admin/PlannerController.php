<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Services\MetricsService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PlannerController extends Controller
{
    public function index()
    {
        $plans = Plan::query()
            ->withCount(['groups', 'routes'])
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Plan $p) => [
                'id' => $p->id,
                'slug' => $p->slug,
                'title' => $p->title,
                'groups_count' => $p->groups_count,
                'routes_count' => $p->routes_count,
                'is_shared' => $p->share_token !== null,
                'created_at' => $p->created_at->toIso8601String(),
                'updated_at' => $p->updated_at->toIso8601String(),
            ]);

        return Inertia::render('Admin/Planner', [
            'plans' => $plans,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
        ]);

        $plan = Plan::create([
            'title' => $validated['title'],
        ]);

        return redirect()->route('admin.planner.show', $plan);
    }

    public function show(Plan $plan, MetricsService $metrics)
    {
        $plan->load(['groups.routes']);

        $gps = $metrics->getGpsMetrics();
        $defaultLat = $gps['latitude'] ?? 37.1028;
        $defaultLng = $gps['longitude'] ?? -8.6740;

        return Inertia::render('Admin/PlannerShow', [
            'plan' => $plan->toDetailArray(),
            'defaultCenter' => [$defaultLat, $defaultLng],
        ]);
    }

    public function update(Request $request, Plan $plan)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
        ]);

        $plan->update($validated);

        return back();
    }

    public function destroy(Plan $plan)
    {
        $plan->delete();

        return redirect()->route('admin.planner');
    }

    public function share(Plan $plan)
    {
        if (! $plan->share_token) {
            $plan->generateShareToken();
        }

        return back();
    }

    public function unshare(Plan $plan)
    {
        $plan->share_token = null;
        $plan->save();

        return back();
    }
}
