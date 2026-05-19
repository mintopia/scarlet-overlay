<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BoatSetting;
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

    public function updatePassage(Request $request)
    {
        $validated = $request->validate([
            'passage_from' => ['nullable', 'string', 'max:100'],
            'passage_to' => ['nullable', 'string', 'max:100'],
        ]);

        BoatSetting::setValue('passage_from', $validated['passage_from'] ?? '');
        BoatSetting::setValue('passage_to', $validated['passage_to'] ?? '');

        return back()->with('success', 'Passage updated.');
    }

    public function updatePort(Request $request)
    {
        $validated = $request->validate([
            'port_name' => ['nullable', 'string', 'max:100'],
        ]);

        BoatSetting::setValue('port_name', $validated['port_name'] ?? '');

        return back()->with('success', 'Port settings updated.');
    }
}
