<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Journey;
use App\Models\ShipLog;
use App\Services\MetricsService;
use Carbon\CarbonInterval;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminLogController extends Controller
{
    public function index(Request $request, MetricsService $metrics)
    {
        $journey = Journey::current();
        $period = $request->input('period', $journey ? 'journey' : '24h');
        $allowed = ['journey', '6h', '12h', '24h', '48h', '168h'];
        if (! in_array($period, $allowed)) {
            $period = '24h';
        }

        if ($period === 'journey' && $journey?->started_at) {
            $logs = ShipLog::where('journey_id', $journey->id)
                ->orderBy('recorded_at')
                ->get();
        } else {
            if ($period === 'journey') {
                $period = '24h';
            }
            $seconds = CarbonInterval::fromString($period)->totalSeconds;
            $start = now()->subSeconds((int) $seconds);
            $logs = ShipLog::where('recorded_at', '>=', $start)
                ->orderBy('recorded_at')
                ->get();
        }

        $rows = $this->buildRows($logs, $period === 'journey');

        return Inertia::render('Admin/Log', [
            'rows' => $rows,
            'period' => $period,
            'hasActiveJourney' => $journey !== null,
            'journeyTitle' => $journey?->title,
            'positionTimezone' => Inertia::defer(fn () => ($metrics->getWeatherData())['timezone'] ?? null),
        ]);
    }

    public function update(Request $request, ShipLog $shipLog)
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $shipLog->update([
            'notes' => $validated['notes'] ?: null,
        ]);

        return back();
    }

    private function buildRows($logs, bool $isJourney): array
    {
        $rows = [];
        $tripOffset = $isJourney && $logs->isNotEmpty() ? $logs->first()->trip_log : 0;

        foreach ($logs as $log) {
            $rows[] = [
                'id' => $log->id,
                'timestamp' => $log->recorded_at->timestamp,
                'course' => $log->course !== null ? (float) $log->course : null,
                'total_log' => $log->trip_log !== null ? (float) $log->trip_log : null,
                'trip_log' => $log->trip_log !== null && $tripOffset !== null
                    ? (float) $log->trip_log - (float) $tripOffset
                    : null,
                'wind_direction' => $log->wind_direction !== null ? (float) $log->wind_direction : null,
                'wind_speed' => $log->wind_speed !== null ? (float) $log->wind_speed : null,
                'pressure' => $log->pressure !== null ? (float) $log->pressure : null,
                'latitude' => $log->latitude !== null ? (float) $log->latitude : null,
                'longitude' => $log->longitude !== null ? (float) $log->longitude : null,
                'wp_distance' => $log->wp_distance !== null ? (float) $log->wp_distance : null,
                'wp_ttg' => $log->wp_ttg !== null ? (float) $log->wp_ttg : null,
                'battery_soc' => $log->battery_soc !== null ? (float) $log->battery_soc : null,
                'water_level' => $log->water_level !== null ? (float) $log->water_level : null,
                'fuel_level' => $log->fuel_level !== null ? (float) $log->fuel_level : null,
                'notes' => $log->notes,
            ];
        }

        if (! $isJourney) {
            foreach ($rows as &$row) {
                $row['total_log'] = $row['trip_log'] ?? null;
            }
            unset($row);
        }

        $cumDist = 0;
        $cumDmg = 0;
        for ($i = 0; $i < count($rows); $i++) {
            if ($i === 0) {
                $rows[$i]['dist'] = null;
                $rows[$i]['dmg'] = null;
                $rows[$i]['diff'] = null;
                $rows[$i]['cum_diff'] = 0;

                continue;
            }

            $prev = $rows[$i - 1];
            $curr = $rows[$i];

            $dist = ($curr['total_log'] !== null && $prev['total_log'] !== null)
                ? $curr['total_log'] - $prev['total_log']
                : null;

            $dmg = ($curr['wp_distance'] !== null && $prev['wp_distance'] !== null)
                ? $prev['wp_distance'] - $curr['wp_distance']
                : null;

            if ($dist !== null && $dmg !== null) {
                $cumDist += $dist;
                $cumDmg += $dmg;
            }

            $rows[$i]['dist'] = $dist;
            $rows[$i]['dmg'] = $dmg;
            $rows[$i]['diff'] = ($dist !== null && $dmg !== null) ? $dmg - $dist : null;
            $rows[$i]['cum_diff'] = round($cumDmg - $cumDist, 1);
        }

        return $rows;
    }
}
