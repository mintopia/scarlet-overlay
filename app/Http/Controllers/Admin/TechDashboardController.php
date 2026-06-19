<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BoatSetting;
use App\Services\CanonicalCatalog;
use App\Services\CanonicalReader;
use Inertia\Inertia;
use Inertia\Response;

class TechDashboardController extends Controller
{
    public function index(CanonicalReader $reader, CanonicalCatalog $catalog): Response
    {
        $contracts = $this->resolveContracts($reader, $catalog);

        $bitrateHistory = $reader->readRange('srt_pub_bitrate', '1h');
        $droppedHistory = $reader->readRange('srt_pub_dropped', '1h');

        // house_battery_power is not in the catalog — compute voltage × current (signed via current)
        $voltagePoints = $reader->readRange('house_battery_voltage', '6h');
        $currentPoints = $reader->readRange('house_battery_current', '6h');
        $housePowerHistory = $this->zipByTime($voltagePoints, $currentPoints, fn (float $v, float $a) => $v * $a);

        // ecoflow net power = input − output (positive = charging)
        $ecoflowInputPoints = $reader->readRange('ecoflow_input_watts', '6h');
        $ecoflowOutputPoints = $reader->readRange('ecoflow_output_watts', '6h');
        $ecoflowPowerHistory = $this->zipByTime($ecoflowInputPoints, $ecoflowOutputPoints, fn (float $inp, float $out) => $inp - $out);

        return Inertia::render('Admin/Dash/Tech', [
            'contracts' => $contracts,
            'bitrateHistory' => $bitrateHistory,
            'droppedHistory' => $droppedHistory,
            'housePowerHistory' => $housePowerHistory,
            'ecoflowPowerHistory' => $ecoflowPowerHistory,
            'pullEnabled' => BoatSetting::getValue('srt_pull_enabled') === '1',
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
     * Zip two time-series by timestamp, applying a combiner to matched points.
     *
     * @param  array<int, array{t: int, v: float}>  $seriesA
     * @param  array<int, array{t: int, v: float}>  $seriesB
     * @return array<int, array{t: int, v: float}>
     */
    private function zipByTime(array $seriesA, array $seriesB, \Closure $combine): array
    {
        if (empty($seriesA) || empty($seriesB)) {
            return [];
        }

        // Index series B by timestamp for O(n) lookup
        $bByTs = [];
        foreach ($seriesB as $point) {
            $bByTs[$point['t']] = $point['v'];
        }

        $result = [];
        foreach ($seriesA as $point) {
            if (! isset($bByTs[$point['t']])) {
                continue;
            }
            $result[] = ['t' => $point['t'], 'v' => $combine((float) $point['v'], (float) $bByTs[$point['t']])];
        }

        return $result;
    }
}
