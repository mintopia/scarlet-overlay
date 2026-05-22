<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ImportJourneyFromPrometheus;
use App\Models\Journey;
use App\Services\GpxService;
use App\Services\JourneyService;
use App\Services\MetricsService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class JourneyController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Journeys', [
            'journeys' => Journey::orderByDesc('started_at')->get()->map(fn (Journey $j) => [
                'id' => $j->id,
                'slug' => $j->slug,
                'title' => $j->title,
                'from_port' => $j->from_port,
                'to_port' => $j->to_port,
                'started_at' => $j->started_at->toIso8601String(),
                'ended_at' => $j->ended_at?->toIso8601String(),
                'status' => $j->status,
                'is_public' => $j->is_public,
                'distance' => $j->distance,
                'duration' => $j->duration,
            ]),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/JourneyCreate', [
            'hasActive' => Journey::active()->exists(),
        ]);
    }

    public function store(Request $request, GpxService $gpxService)
    {
        $validated = $request->validate([
            'from_port' => ['required', 'string', 'max:100'],
            'to_port' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'gpx_file' => ['nullable', 'file', 'max:10240'],
        ]);

        try {
            $journey = Journey::startNew($validated['from_port'], $validated['to_port'], [
                'notes' => $validated['notes'] ?? null,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        if ($request->hasFile('gpx_file')) {
            $result = $gpxService->storeAndParse($request->file('gpx_file'), $journey->id);
            $journey->update([
                'gpx_route_path' => $result['path'],
                'route_waypoints' => $result['waypoints'],
            ]);
        }

        return redirect()->route('admin.journeys')->with('success', 'Journey started.');
    }

    public function importForm()
    {
        return Inertia::render('Admin/JourneyImport');
    }

    public function import(Request $request, GpxService $gpxService)
    {
        $validated = $request->validate([
            'from_port' => ['required', 'string', 'max:100'],
            'to_port' => ['required', 'string', 'max:100'],
            'started_at' => ['required', 'date'],
            'ended_at' => ['required', 'date', 'after:started_at'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'gpx_file' => ['nullable', 'file', 'max:10240'],
        ]);

        $journey = Journey::create([
            'from_port' => $validated['from_port'],
            'to_port' => $validated['to_port'],
            'started_at' => $validated['started_at'],
            'ended_at' => $validated['ended_at'],
            'status' => 'completed',
            'notes' => $validated['notes'] ?? null,
        ]);

        if ($request->hasFile('gpx_file')) {
            $result = $gpxService->storeAndParse($request->file('gpx_file'), $journey->id);
            $journey->update([
                'gpx_route_path' => $result['path'],
                'route_waypoints' => $result['waypoints'],
            ]);
        }

        ImportJourneyFromPrometheus::dispatch(
            $journey->id,
            $validated['started_at'],
            $validated['ended_at'],
        );

        return redirect()->route('admin.journeys')->with('success', 'Import started. Track data will appear shortly.');
    }

    public function edit(Journey $journey)
    {
        return Inertia::render('Admin/JourneyEdit', [
            'journey' => $journey->only('id', 'slug', 'title', 'from_port', 'to_port', 'is_public', 'notes', 'status', 'gpx_route_path'),
        ]);
    }

    public function update(Request $request, Journey $journey)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['required', 'string', 'max:200', 'unique:journeys,slug,' . $journey->id],
            'from_port' => ['required', 'string', 'max:100'],
            'to_port' => ['required', 'string', 'max:100'],
            'is_public' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $journey->update($validated);

        return redirect()->route('admin.journeys')->with('success', 'Journey updated.');
    }

    public function destroy(Journey $journey)
    {
        $journey->delete();

        return redirect()->route('admin.journeys')->with('success', 'Journey deleted.');
    }

    public function end(Journey $journey, JourneyService $journeyService)
    {
        $journeyService->endJourney($journey);

        return redirect()->route('admin.journeys')->with('success', 'Journey ended.');
    }

    public function uploadGpx(Request $request, Journey $journey, GpxService $gpxService)
    {
        $request->validate([
            'gpx_file' => ['required', 'file', 'max:10240'],
        ]);

        $result = $gpxService->storeAndParse($request->file('gpx_file'), $journey->id);
        $journey->update([
            'gpx_route_path' => $result['path'],
            'route_waypoints' => $result['waypoints'],
        ]);

        return back()->with('success', 'GPX route uploaded.');
    }
}
