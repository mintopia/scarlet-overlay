<?php

namespace App\Http\Controllers\Admin;

use App\Events\ForceReload;
use App\Http\Controllers\Controller;
use App\Models\BoatSetting;
use App\Services\MediaMtxService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Settings', [
            'settings' => BoatSetting::getAll(),
        ]);
    }

    public function updateIdentity(Request $request)
    {
        $validated = $request->validate([
            'boat_name' => ['required', 'string', 'max:100'],
            'mmsi' => ['nullable', 'string', 'size:9', 'regex:/^\d{9}$/'],
        ]);

        BoatSetting::setValue('boat_name', $validated['boat_name']);
        BoatSetting::setValue('mmsi', $validated['mmsi'] ?? '');

        return back()->with('success', 'Boat identity updated.');
    }

    public function updateStream(Request $request, MediaMtxService $mediaMtx)
    {
        $validated = $request->validate([
            'srt_url' => ['nullable', 'string', 'max:500'],
            'srt_stats_url' => ['nullable', 'url', 'max:500'],
        ]);

        $srtUrl = $validated['srt_url'] ?? '';
        BoatSetting::setValue('srt_url', $srtUrl);
        BoatSetting::setValue('srt_stats_url', $validated['srt_stats_url'] ?? '');

        $mediaMtx->setPathSource('live', $srtUrl);

        return back()->with('success', 'Stream settings updated.');
    }

    public function updateCamera(Request $request)
    {
        $validated = $request->validate([
            'camera_url' => ['nullable', 'url', 'max:500'],
        ]);

        BoatSetting::setValue('camera_url', $validated['camera_url'] ?? '');

        return back()->with('success', 'Camera settings updated.');
    }

    public function forceReload()
    {
        event(new ForceReload);

        return back()->with('success', 'Reload signal sent.');
    }
}
