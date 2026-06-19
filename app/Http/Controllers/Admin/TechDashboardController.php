<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ReadsDashboardPower;
use App\Http\Controllers\Controller;
use App\Models\BoatSetting;
use App\Services\CanonicalCatalog;
use App\Services\CanonicalReader;
use Inertia\Inertia;
use Inertia\Response;

class TechDashboardController extends Controller
{
    use ReadsDashboardPower;

    public function index(CanonicalReader $reader, CanonicalCatalog $catalog): Response
    {
        $contracts = $this->resolveContracts($reader, $catalog);

        $bitrateHistory = $reader->readRange('srt_pub_bitrate', '1h');
        $droppedHistory = $reader->readRange('srt_pub_dropped', '1h');

        return Inertia::render('Admin/Dash/Tech', [
            'contracts' => $contracts,
            'bitrateHistory' => $bitrateHistory,
            'droppedHistory' => $droppedHistory,
            'housePowerHistory' => $this->housePowerHistory($reader),
            'ecoflowPowerHistory' => $this->ecoflowPowerHistory($reader),
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
}
