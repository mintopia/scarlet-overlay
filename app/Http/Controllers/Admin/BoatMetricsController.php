<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Journey;
use App\Services\CanonicalReader;
use App\Services\MetricsService;
use Inertia\Inertia;

class BoatMetricsController extends Controller
{
    public function index(MetricsService $metrics, CanonicalReader $canonical)
    {
        $journey = Journey::current();
        $start = now()->subHours(24)->timestamp;
        $end = now()->timestamp;

        return Inertia::render('Admin/BoatMetrics', [
            'boat' => $metrics->getBoatMetrics(),
            'batteryHistory' => $this->series($canonical, 'house_battery_voltage', '300s', $start, $end),
            'batteryPowerHistory' => $this->series($canonical, 'house_battery_current', '300s', $start, $end),
            'speedHistory' => $this->series($canonical, 'speed_sog', '300s', $start, $end),
            'tempHistoryForepeak' => $this->series($canonical, 'cabin_temp_forepeak', '300s', $start, $end),
            'tempHistoryQuarterberth' => $this->series($canonical, 'cabin_temp_quarterberth', '300s', $start, $end),
            'tempHistoryMainCabin' => $this->series($canonical, 'cabin_temp_main', '300s', $start, $end),
            'humidityHistoryForepeak' => $this->series($canonical, 'cabin_humidity_forepeak', '300s', $start, $end),
            'humidityHistoryQuarterberth' => $this->series($canonical, 'cabin_humidity_quarterberth', '300s', $start, $end),
            'humidityHistoryMainCabin' => $this->series($canonical, 'cabin_humidity_main', '300s', $start, $end),
            'fuelHistory' => $this->series($canonical, 'fuel_level', '300s', $start, $end),
            'waterHistory' => $this->series($canonical, 'water_fresh_level', '300s', $start, $end),
            'tripDistance' => $journey?->distance,
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
