<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Journey;
use App\Services\MetricRegistry;
use App\Services\MetricsService;
use Inertia\Inertia;

class JourneyViewController extends Controller
{
    public function show(Journey $journey, MetricsService $metrics, MetricRegistry $registry)
    {
        $start = $journey->started_at?->timestamp;
        $end = ($journey->ended_at ?? now())->timestamp;

        if (! $start) {
            return redirect()->route('admin.journeys');
        }

        $duration = $end - $start;
        $step = max(15, (int) ceil($duration / 8000)).'s';

        $chartMetrics = ['speed_sog', 'vmg', 'fuel_level', 'water_level', 'house_battery_soc'];
        $charts = [];
        foreach ($chartMetrics as $key) {
            $charts[$key] = $registry->fetchRange($key, $step, $start, $end, fillGaps: false);
        }

        $current = $registry->fetchRange('house_battery_current', $step, $start, $end, fillGaps: false);
        $voltage = $registry->fetchRange('house_battery_voltage', $step, $start, $end, fillGaps: false);
        $voltageByTs = collect($voltage)->keyBy('timestamp');
        $batteryPower = [];
        foreach ($current as $pt) {
            $v = $voltageByTs->get($pt['timestamp']);
            $batteryPower[] = [
                'timestamp' => $pt['timestamp'],
                'value' => ($pt['value'] !== null && $v !== null) ? $pt['value'] * $v['value'] : null,
            ];
        }
        $charts['battery_power'] = $batteryPower;

        $gpsTrack = $metrics->getGpsTrack(null, $step, $start, $end);

        $logStart = $journey->started_at->startOfHour()->subHour()->timestamp;
        $logEnd = ($journey->ended_at ?? now())->endOfHour()->addHour()->timestamp;
        $logRows = $metrics->getLogData(null, '3600', $logStart, $logEnd);

        $exploreConfig = config('scarlet.metrics.mappings.explore');

        return Inertia::render('Admin/JourneyView', [
            'journey' => [
                'id' => $journey->id,
                'slug' => $journey->slug,
                'title' => $journey->title,
                'from_port' => $journey->from_port,
                'to_port' => $journey->to_port,
                'started_at' => $journey->started_at->toIso8601String(),
                'ended_at' => $journey->ended_at?->toIso8601String(),
                'status' => $journey->status,
                'distance' => $journey->distance,
                'duration' => $journey->duration,
                'notes' => $journey->notes,
                'route_waypoints' => $journey->route_waypoints,
                'is_public' => $journey->is_public,
            ],
            'charts' => $charts,
            'chartMeta' => collect($chartMetrics)->push('battery_power')->mapWithKeys(function ($key) use ($exploreConfig) {
                $explore = collect($exploreConfig)->first(fn ($e) => ($e['metric'] ?? null) === $key);

                return [$key => [
                    'label' => $explore['label'] ?? ucfirst(str_replace('_', ' ', $key)),
                    'unit' => $explore['unit'] ?? '',
                    'color' => $explore['color'] ?? 'oklch(0.55 0.15 240)',
                    'type' => $explore['type'] ?? 'standard',
                ]];
            })->all(),
            'gpsTrack' => $gpsTrack,
            'logRows' => $logRows,
        ]);
    }
}
