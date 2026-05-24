<?php

namespace App\Http\Controllers;

use App\Models\Journey;
use Illuminate\Http\Request;
use Inertia\Inertia;

class JourneyViewController extends Controller
{
    public function index()
    {
        $active = Journey::current();

        if (!$active) {
            abort(404);
        }

        return redirect("/journey/{$active->slug}");
    }

    public function show(Request $request, string $slug)
    {
        $journey = Journey::where('slug', $slug)->firstOrFail();

        if (!$journey->is_public && !$request->user()) {
            abort(403);
        }

        $trackPoints = $journey->trackPoints()
            ->select(['recorded_at', 'latitude', 'longitude', 'speed_sog', 'heading', 'cog', 'depth', 'wind_speed_apparent', 'wind_angle_apparent', 'wind_speed_true', 'wind_direction_true', 'house_battery_voltage', 'house_battery_current', 'heel'])
            ->get()
            ->filter(fn ($p) => !(abs($p->latitude) < 0.1 && abs($p->longitude) < 0.1));

        $track = $trackPoints->map(fn ($p) => [
            $p->latitude, $p->longitude, $p->speed_sog ?? 0,
        ])->values()->toArray();

        $decimated = count($track) > 2000
            ? $this->decimateTrack($track, 2000)
            : $track;

        return Inertia::render('Public/Journey', [
            'journey' => [
                'id' => $journey->id,
                'slug' => $journey->slug,
                'title' => $journey->title,
                'from_port' => $journey->from_port,
                'to_port' => $journey->to_port,
                'started_at' => $journey->started_at->toIso8601String(),
                'ended_at' => $journey->ended_at?->toIso8601String(),
                'status' => $journey->status,
                'distance' => $journey->distance,
                'duration' => $journey->duration,
                'notes' => $journey->notes,
            ],
            'routeWaypoints' => $journey->route_waypoints ?? [],
            'gpsTrack' => $decimated,
            'trackPoints' => $trackPoints->values()->toArray(),
        ]);
    }

    public function track(Request $request, string $slug)
    {
        $journey = Journey::where('slug', $slug)->firstOrFail();

        if (!$journey->is_public && !$request->user()) {
            abort(403);
        }

        $points = $journey->trackPoints()
            ->select(['recorded_at', 'latitude', 'longitude', 'speed_sog', 'heading', 'cog', 'depth', 'wind_speed_apparent', 'wind_angle_apparent', 'wind_speed_true', 'wind_direction_true', 'house_battery_voltage', 'house_battery_current', 'heel'])
            ->get()
            ->filter(fn ($p) => !(abs($p->latitude) < 0.1 && abs($p->longitude) < 0.1))
            ->values();

        return response()->json($points);
    }

    protected function decimateTrack(array $track, int $maxPoints): array
    {
        $step = max(1, (int) ceil(count($track) / $maxPoints));
        $result = [];
        for ($i = 0; $i < count($track); $i += $step) {
            $result[] = $track[$i];
        }
        if (end($result) !== end($track)) {
            $result[] = end($track);
        }
        return $result;
    }
}
