<?php

namespace App\Http\Controllers;

use App\Models\BoatSetting;
use App\Services\MetricsService;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(MetricsService $metrics)
    {
        $journey = \App\Models\Journey::current();
        $routeJourney = $journey
            ?? \App\Models\Journey::planned()
            ?? \App\Models\Journey::completed()->whereNotNull('route_waypoints')->orderByDesc('ended_at')->first();

        return Inertia::render('Public/Dashboard', [
            'initialMetrics' => $metrics->getAllMetrics(),
            'gpsTrack' => $metrics->getGpsTrack(),
            'boatName' => BoatSetting::getValue('boat_name', config('scarlet.name')),
            'passageFrom' => $journey?->from_port ?? '',
            'passageTo' => $journey?->to_port ?? '',
            'portName' => BoatSetting::getValue('port_name', ''),
            'tileUrl' => '/openseamap/{z}/{x}/{y}',
            'reverb' => config('scarlet.reverb'),
            'reverbKey' => config('broadcasting.connections.reverb.key'),
            'tripOffset' => (float) BoatSetting::getValue('trip_offset', 0),
            'routeWaypoints' => $routeJourney?->route_waypoints ?? [],
        ]);
    }
}
