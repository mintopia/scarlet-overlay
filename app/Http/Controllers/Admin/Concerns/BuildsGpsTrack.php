<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\Journey;
use App\Services\MetricsService;

trait BuildsGpsTrack
{
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
