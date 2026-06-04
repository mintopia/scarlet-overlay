<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\PlanGroup;
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

    public function destroy(PlanGroup $group)
    {
        $group->delete();

        return back();
    }
}
