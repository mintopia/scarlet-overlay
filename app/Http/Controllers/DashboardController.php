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

        $recentJourney = $journey ?? Journey::completed()->orderByDesc('ended_at')->first();
        $journeyStart = $recentJourney?->started_at?->timestamp;

        if ($journeyStart) {
            $journeyDuration = now()->timestamp - $journeyStart;
            $recentStep = match (true) {
                $journeyDuration > 7 * 86400 => '120s',
                $journeyDuration > 3 * 86400 => '60s',
                $journeyDuration > 86400 => '30s',
                default => '15s',
            };
            $olderTrack = $metrics->getGpsTrack(null, '120s', now()->subDays(14)->timestamp, $journeyStart);
            $recentTrack = $metrics->getGpsTrack(null, $recentStep, $journeyStart);
            $gpsTrack = array_merge($olderTrack, $recentTrack);
        } else {
            $gpsTrack = $metrics->getGpsTrack('14d', '60s');
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
