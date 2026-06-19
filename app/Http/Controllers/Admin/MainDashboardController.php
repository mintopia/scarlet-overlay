<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\BuildsGpsTrack;
use App\Http\Controllers\Admin\Concerns\ReadsDashboardPower;
use App\Http\Controllers\Admin\Concerns\ResolvesCanonicalContracts;
use App\Http\Controllers\Controller;
use App\Services\CanonicalCatalog;
use App\Services\CanonicalReader;
use App\Services\MetricsService;
use Inertia\Inertia;
use Inertia\Response;

class MainDashboardController extends Controller
{
    use BuildsGpsTrack, ReadsDashboardPower, ResolvesCanonicalContracts;

    public function index(CanonicalReader $reader, CanonicalCatalog $catalog, MetricsService $metrics): Response
    {
        $contracts = $this->resolveContracts($reader, $catalog);

        $housePowerHistory = $this->housePowerHistory($reader);

        $fuelHistory = $reader->readRange('fuel_level', '24h');
        $waterHistory = $reader->readRange('water_fresh_level', '24h');

        $depthHistory = $reader->readRange('depth_below_surface', '3h');
        $speedHistory = $reader->readRange('speed_sog', '1h');

        $gpsTrack = $this->buildGpsTrack($metrics);
        $gps = $metrics->getGpsMetrics();

        return Inertia::render('Admin/Dash/Main', [
            'contracts' => $contracts,
            'housePowerHistory' => $housePowerHistory,
            'fuelHistory' => $fuelHistory,
            'waterHistory' => $waterHistory,
            'depthHistory' => $depthHistory,
            'speedHistory' => $speedHistory,
            'gpsTrack' => $gpsTrack,
            'gps' => $gps,
            'reverb' => config('scarlet.reverb'),
            'reverbKey' => config('broadcasting.connections.reverb.key'),
        ]);
    }
}
