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

        return Inertia::render('Public/Dashboard', [
            'initialMetrics' => $metrics->getAllMetrics(),
            'gpsTrack' => $metrics->getGpsTrack(),
            'boatName' => BoatSetting::getValue('boat_name', config('scarlet.name')),
            'passageFrom' => $journey?->from_port ?? '',
            'passageTo' => $journey?->to_port ?? '',
            'portName' => Journey::lastPort() ?? '',
            'tileUrl' => '/openseamap/{z}/{x}/{y}',
            'reverb' => config('scarlet.reverb'),
            'reverbKey' => config('broadcasting.connections.reverb.key'),
            'tripOffset' => (float) BoatSetting::getValue('trip_offset', 0),
            'routeWaypoints' => $routeJourney?->route_waypoints ?? [],
        ]);
    }
}
