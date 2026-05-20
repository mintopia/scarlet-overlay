<?php

namespace App\Http\Controllers;

use App\Models\BoatSetting;
use App\Services\MetricsService;
use Inertia\Inertia;

class OverlayController extends Controller
{
    public function index(MetricsService $metrics)
    {
        Inertia::setRootView('overlay-app');

        return Inertia::render('Public/Overlay', [
            'initialMetrics' => $metrics->getAllMetrics(),
            'boatName' => BoatSetting::getValue('boat_name', config('scarlet.name')),
            'passageFrom' => BoatSetting::getValue('passage_from', ''),
            'passageTo' => BoatSetting::getValue('passage_to', ''),
            'portName' => BoatSetting::getValue('port_name', ''),
            'utcOffset' => config('scarlet.time.offset'),
            'timeLabel' => config('scarlet.time.label'),
        ]);
    }
}
