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

class OpsDashboardController extends Controller
{
    use BuildsGpsTrack, ReadsDashboardPower, ResolvesCanonicalContracts;

    public function index(CanonicalReader $reader, CanonicalCatalog $catalog, MetricsService $metrics): Response
    {
        $contracts = $this->resolveContracts($reader, $catalog);

        $housePowerHistory = $this->housePowerHistory($reader);
        $ecoflowPowerHistory = $this->ecoflowPowerHistory($reader);

        $fuelHistory = $reader->readRange('fuel_level', '24h');
        $waterHistory = $reader->readRange('water_fresh_level', '24h');

        $gpsTrack = $this->buildGpsTrack($metrics);
        $gps = $metrics->getGpsMetrics();
        $forecast = $metrics->getWeatherForecast();

        return Inertia::render('Admin/Dash/Ops', [
            'contracts' => $contracts,
            'housePowerHistory' => $housePowerHistory,
            'ecoflowPowerHistory' => $ecoflowPowerHistory,
            'fuelHistory' => $fuelHistory,
            'waterHistory' => $waterHistory,
            'gpsTrack' => $gpsTrack,
            'gps' => $gps,
            'forecast' => $forecast,
            'reverb' => config('scarlet.reverb'),
            'reverbKey' => config('broadcasting.connections.reverb.key'),
        ]);
    }
}
