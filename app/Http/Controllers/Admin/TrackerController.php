<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CanonicalReader;
use App\Services\MetricsService;
use Inertia\Inertia;

class TrackerController extends Controller
{
    public function index(MetricsService $metrics, CanonicalReader $canonical)
    {
        $hourAgo = now()->subHour()->timestamp;
        $sixHoursAgo = now()->subHours(6)->timestamp;
        $now = now()->timestamp;

        return Inertia::render('Admin/Tracker', [
            'tracker' => $metrics->getTrackerMetrics(),
            'gps' => $metrics->getGpsMetrics(),
            'signalHistory' => [
                'lte' => $this->series($canonical, 'tracker_lte_rssi', '60s', $hourAgo, $now),
                'wifi' => $this->series($canonical, 'tracker_wifi_rssi', '60s', $hourAgo, $now),
            ],
            'gpsHistory' => $this->series($canonical, 'gps_satellites', '60s', $hourAgo, $now),
            'cpuHistory' => $this->series($canonical, 'tracker_cpu', '60s', $hourAgo, $now),
            'tempHistory' => $this->series($canonical, 'cabin_temp_forepeak', '120s', $sixHoursAgo, $now),
            'humidityHistory' => $this->series($canonical, 'cabin_humidity_forepeak', '120s', $sixHoursAgo, $now),
        ]);
    }

    /**
     * @return array<int, array{timestamp: int, value: float}>
     */
    private function series(CanonicalReader $canonical, string $key, string $step, int $start, int $end): array
    {
        return array_map(
            fn (array $p): array => ['timestamp' => $p['t'], 'value' => $p['v']],
            $canonical->readRange($key, null, $step, $start, $end),
        );
    }
}
