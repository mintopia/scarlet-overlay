<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ReadsDashboardPower;
use App\Http\Controllers\Controller;
use App\Models\Journey;
use App\Services\CanonicalCatalog;
use App\Services\CanonicalReader;
use App\Services\MetricsService;
use Inertia\Inertia;
use Inertia\Response;

class OpsDashboardController extends Controller
{
    use ReadsDashboardPower;

    public function index(CanonicalReader $reader, CanonicalCatalog $catalog, MetricsService $metrics): Response
    {
        $contracts = $this->resolveContracts($reader, $catalog);

        $housePowerHistory = $this->housePowerHistory($reader);
        $ecoflowPowerHistory = $this->ecoflowPowerHistory($reader);

        $fuelHistory = $reader->readRange('fuel_level', '24h');
        $waterHistory = $reader->readRange('water_fresh_level', '24h');

        $gpsTrack = $this->buildGpsTrack($metrics);

        return Inertia::render('Admin/Dash/Ops', [
            'contracts' => $contracts,
            'housePowerHistory' => $housePowerHistory,
            'ecoflowPowerHistory' => $ecoflowPowerHistory,
            'fuelHistory' => $fuelHistory,
            'waterHistory' => $waterHistory,
            'gpsTrack' => $gpsTrack,
            'reverb' => config('scarlet.reverb'),
            'reverbKey' => config('broadcasting.connections.reverb.key'),
        ]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function resolveContracts(CanonicalReader $reader, CanonicalCatalog $catalog): array
    {
        if (! config('scarlet.canonical.enabled')) {
            return [];
        }

        $keys = array_keys($catalog->all());

        return array_filter($reader->readMany($keys), fn ($c) => $c !== null);
    }

    /**
     * Build GPS track from current and previous journeys, mirroring DashboardController.
     *
     * @return array<int, array{float, float}>
     */
    private function buildGpsTrack(MetricsService $metrics): array
    {
        $journey = Journey::current();
        $currentOrLatest = $journey ?? Journey::completed()->orderByDesc('ended_at')->first();
        $previous = Journey::completed()->orderByDesc('ended_at')
            ->when($currentOrLatest, fn ($q) => $q->where('id', '!=', $currentOrLatest->id))
            ->first();

        $gpsTrack = [];

        if ($previous?->started_at) {
            $prevEnd = $currentOrLatest?->started_at?->timestamp ?? now()->timestamp;
            $prevRange = $prevEnd - $previous->started_at->timestamp;
            $prevStep = max(15, (int) ceil($prevRange / 25000)).'s';
            $gpsTrack = $metrics->getGpsTrack(null, $prevStep, $previous->started_at->timestamp, $prevEnd);
        }

        if ($currentOrLatest?->started_at) {
            $trackEnd = $journey ? null : $currentOrLatest->ended_at?->timestamp;
            $rangeSeconds = ($trackEnd ?? now()->timestamp) - $currentOrLatest->started_at->timestamp;
            $step = max(15, (int) ceil($rangeSeconds / 25000)).'s';
            $gpsTrack = array_merge($gpsTrack, $metrics->getGpsTrack(null, $step, $currentOrLatest->started_at->timestamp, $trackEnd));
        }

        return $gpsTrack;
    }
}
