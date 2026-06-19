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

class SkipperDashboardController extends Controller
{
    use BuildsGpsTrack, ReadsDashboardPower, ResolvesCanonicalContracts;

    public function index(CanonicalReader $reader, CanonicalCatalog $catalog, MetricsService $metrics): Response
    {
        $contracts = $this->resolveContracts($reader, $catalog);

        $depthHistory = $reader->readRange('depth_below_surface', '3h');
        $pressureHistory = $reader->readRange('cabin_pressure_forepeak', '3h');
        $speedHistory = $reader->readRange('speed_sog', '1h');
        $housePowerHistory = $this->housePowerHistory($reader);

        $gpsTrack = $this->buildGpsTrack($metrics);

        // Route-leg position keys (wp_next_lat, wp_prev_lat, etc.) do not exist
        // in CanonicalBaseline — pass empty array; Vue will skip route-leg rendering.
        $routeLegs = [];

        return Inertia::render('Admin/Dash/Skipper', [
            'contracts' => $contracts,
            'depthHistory' => $depthHistory,
            'pressureHistory' => $pressureHistory,
            'speedHistory' => $speedHistory,
            'housePowerHistory' => $housePowerHistory,
            'routeLegs' => $routeLegs,
            'gpsTrack' => $gpsTrack,
            'reverb' => config('scarlet.reverb'),
            'reverbKey' => config('broadcasting.connections.reverb.key'),
        ]);
    }
}
