<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\PlanGroup;
use App\Models\PlanRoute;
use Illuminate\Http\Request;

class PlanGroupController extends Controller
{
    public function store(Request $request, Plan $plan)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $nextColorIndex = $plan->groups()->count() % 6;

        $plan->groups()->create([
            'name' => $validated['name'],
            'color_index' => $nextColorIndex,
            'sort_order' => $plan->groups()->count(),
        ]);

        return back();
    }

    public function update(Request $request, PlanGroup $group)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $group->update($validated);

        return back();
    }

    public function reorder(Request $request, PlanGroup $group)
    {
        $validated = $request->validate([
            'route_ids' => ['required', 'array', 'min:1'],
            'route_ids.*' => ['required', 'integer'],
        ]);

        $routeIds = $validated['route_ids'];
        $groupRouteIds = $group->routes()->pluck('id')->all();

        if (array_diff($routeIds, $groupRouteIds) || array_diff($groupRouteIds, $routeIds)) {
            abort(422, 'Route IDs do not match this group.');
        }

        foreach ($routeIds as $index => $routeId) {
            PlanRoute::where('id', $routeId)->update(['sort_order' => $index]);
        }

        return back();
    }

    public function destroy(PlanGroup $group)
    {
        $group->delete();

        return back();
    }
}
