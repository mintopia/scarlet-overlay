<?php

namespace App\Http\Controllers;

use App\Models\BoatSetting;
use App\Models\Journey;
use App\Services\MetricsService;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(MetricsService $metrics)
    {
        $journey = Journey::current();
        $routeJourney = $journey
            ?? Journey::planned()
            ?? Journey::completed()->whereNotNull('route_waypoints')->orderByDesc('ended_at')->first();

        $currentOrLatest = $journey ?? Journey::completed()->orderByDesc('ended_at')->first();
        $previous = Journey::completed()->orderByDesc('ended_at')
            ->when($currentOrLatest, fn ($q) => $q->where('id', '!=', $currentOrLatest->id))
            ->first();

        $gpsTrack = [];
        if ($previous?->started_at) {
            $gpsTrack = $metrics->getGpsTrack(null, '60s', $previous->started_at->timestamp, $currentOrLatest?->started_at?->timestamp ?? now()->timestamp);
        }
        if ($currentOrLatest?->started_at) {
            $gpsTrack = array_merge($gpsTrack, $metrics->getGpsTrack(null, '15s', $currentOrLatest->started_at->timestamp));
        }

        return Inertia::render('Public/Dashboard', [
            'initialMetrics' => $metrics->getAllMetrics(),
            'gpsTrack' => $gpsTrack,
            'boatName' => BoatSetting::getValue('boat_name', config('scarlet.name')),
            'passageFrom' => $journey?->from_port ?? '',
            'passageTo' => $journey?->to_port ?? '',
            'portName' => Journey::lastPort() ?? '',
            'tileUrl' => '/openseamap/{z}/{x}/{y}',
            'reverb' => config('scarlet.reverb'),
            'reverbKey' => config('broadcasting.connections.reverb.key'),
            'tripDistance' => $journey ? $journey->distance : $this->trackDistance($gpsTrack),
            'routeWaypoints' => $routeJourney?->route_waypoints ?? [],
        ]);
    }

    private function trackDistance(array $track): float
    {
        if (count($track) < 2) {
            return 0;
        }

        $total = 0;
        for ($i = 1; $i < count($track); $i++) {
            $dLat = deg2rad($track[$i][0] - $track[$i - 1][0]);
            $dLon = deg2rad($track[$i][1] - $track[$i - 1][1]);
            $a = sin($dLat / 2) ** 2 + cos(deg2rad($track[$i - 1][0])) * cos(deg2rad($track[$i][0])) * sin($dLon / 2) ** 2;
            $total += 3440.065 * 2 * atan2(sqrt($a), sqrt(1 - $a));
        }

        return round($total, 1);
    }
}
