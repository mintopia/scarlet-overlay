<?php

namespace App\Http\Controllers;

use App\Models\BoatSetting;
use App\Models\Journey;
use App\Services\MetricsService;
use Inertia\Inertia;

class OverlayController extends Controller
{
    public function index(MetricsService $metrics)
    {
        Inertia::setRootView('overlay-app');

        $journey = Journey::current();
        $routeJourney = $journey
            ?? Journey::planned()
            ?? Journey::completed()->whereNotNull('route_waypoints')->orderByDesc('ended_at')->first();

        return Inertia::render('Public/Overlay', [
            'initialMetrics' => $metrics->getAllMetrics(),
            'gpsTrack' => $metrics->getGpsTrack(),
            'boatName' => BoatSetting::getValue('boat_name', config('scarlet.name')),
            'passageFrom' => $journey?->from_port ?? '',
            'passageTo' => $journey?->to_port ?? '',
            'portName' => Journey::lastPort() ?? '',
            'routeWaypoints' => $routeJourney?->route_waypoints ?? [],
        ]);
    }

    public function camera(MetricsService $metrics)
    {
        Inertia::setRootView('overlay-app');

        $journey = Journey::current();

        return Inertia::render('Public/Camera', [
            'initialMetrics' => $metrics->getAllMetrics(),
            'boatName' => BoatSetting::getValue('boat_name', config('scarlet.name')),
            'passageFrom' => $journey?->from_port ?? '',
            'passageTo' => $journey?->to_port ?? '',
            'portName' => Journey::lastPort() ?? '',
            'feedUrl' => BoatSetting::getValue('camera_url', ''),
        ]);
    }
}
