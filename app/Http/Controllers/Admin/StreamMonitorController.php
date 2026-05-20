<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BoatSetting;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;

class StreamMonitorController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/StreamMonitor', [
            'statsUrl' => BoatSetting::getValue('srt_stats_url', ''),
        ]);
    }

    public function stats()
    {
        $url = BoatSetting::getValue('srt_stats_url', '');

        if (!$url) {
            return response()->json(['error' => 'No stats URL configured'], 422);
        }

        $response = Http::timeout(5)->get($url);

        return response()->json($response->json());
    }
}
