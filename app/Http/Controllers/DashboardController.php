<?php

namespace App\Http\Controllers;

use App\Models\BoatSetting;
use App\Services\MetricsService;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(MetricsService $metrics)
    {
        return Inertia::render('Public/Dashboard', [
            'initialMetrics' => $metrics->getAllMetrics(),
            'boatName' => BoatSetting::getValue('boat_name', config('scarlet.name')),
            'passageFrom' => BoatSetting::getValue('passage_from', ''),
            'passageTo' => BoatSetting::getValue('passage_to', ''),
            'portName' => BoatSetting::getValue('port_name', ''),
            'tileUrl' => '/openseamap/{z}/{x}/{y}',
            'reverb' => config('scarlet.reverb'),
            'reverbKey' => config('broadcasting.connections.reverb.key'),
        ]);
    }
}
