<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlanGroup;
use App\Models\PlanRoute;
use App\Services\GpxService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PlanRouteController extends Controller
{
    public function store(Request $request, PlanGroup $group, GpxService $gpxService)
    {
        $request->validate([
            'gpx_files' => ['required', 'array', 'min:1'],
            'gpx_files.*' => ['required', 'file', 'max:10240'],
        ]);

        $errors = [];

        foreach ($request->file('gpx_files') as $file) {
            try {
                $routes = $gpxService->storeAndParseRoute($file, $group->id);

                foreach ($routes as $result) {
                    $group->routes()->create([
                        'name' => $result['name'],
                        'gpx_path' => $result['path'],
                        'distance_nm' => $result['distance_nm'],
                        'color_index' => $group->nextRouteColorIndex(),
                        'track_points' => $result['track_points'],
                        'waypoints' => $result['waypoints'],
                        'sort_order' => $group->routes()->count(),
                    ]);
                }
            } catch (\Exception $e) {
                $errors[] = "Could not parse {$file->getClientOriginalName()} — invalid GPX format";
            }
        }

        if ($errors) {
            return back()->withErrors(['gpx_files' => $errors]);
        }

        return back();
    }

    public function update(Request $request, PlanRoute $route)
    {
        $validated = $request->validate([
            'is_enabled' => ['sometimes', 'boolean'],
            'name' => ['sometimes', 'string', 'max:200'],
        ]);

        $route->update($validated);

        return back();
    }

    public function destroy(PlanRoute $route)
    {
        if ($route->gpx_path && Storage::exists($route->gpx_path)) {
            Storage::delete($route->gpx_path);
        }

        $route->delete();

        return back();
    }
}
