<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Journey;
use App\Services\MetricRegistry;
use App\Services\MetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ExploreController extends Controller
{
    private const STEP_MAP = [
        '1h' => '15s',
        '6h' => '60s',
        '12h' => '120s',
        '24h' => '300s',
        '3d' => '900s',
        '7d' => '1800s',
        '30d' => '7200s',
    ];

    private const DEFAULT_REFRESH = [
        '1h' => 15, '6h' => 15, '12h' => 15, '24h' => 15,
        '3d' => 0, '7d' => 0, '30d' => 0,
    ];

    public function index(Request $request, MetricRegistry $registry, MetricsService $metrics)
    {
        $slug = $request->query('metric');
        $allMetrics = config('scarlet.metrics.mappings.explore');

        if (! $slug || ! isset($allMetrics[$slug])) {
            return $this->dashboard($request, $registry, $metrics);
        }

        $metric = $allMetrics[$slug];
        $metric['slug'] = $slug;

        $passage = $this->getPassageData();
        $range = $request->query('range', $passage['available'] ? 'passage' : '24h');
        [$start, $end, $step] = $this->resolveTimeRange($request, $range);

        $data = $this->queryMetricRange($registry, $metric, null, $step, $start, $end, $metrics);

        $overlays = [];
        $overlayParam = $request->query('overlay', '');
        if ($overlayParam) {
            foreach (explode(',', $overlayParam) as $overlaySlugs) {
                $os = trim($overlaySlugs);
                if (isset($allMetrics[$os]) && $os !== $slug) {
                    $overlays[] = [
                        'metric' => array_merge($allMetrics[$os], ['slug' => $os]),
                        'data' => $this->queryMetricRange($registry, $allMetrics[$os], null, $step, $start, $end, $metrics),
                    ];
                }
            }
        }

        $grouped = [];
        foreach ($allMetrics as $s => $m) {
            $grouped[$m['group']][$s] = $m;
        }

        $refresh = (int) $request->query('refresh', self::DEFAULT_REFRESH[$range] ?? 0);

        $journeys = Journey::whereNotNull('started_at')
            ->orderByDesc('started_at')
            ->limit(10)
            ->get(['id', 'title', 'from_port', 'to_port', 'started_at', 'ended_at']);

        $propulsionData = [];
        if (in_array($slug, ['speed', 'stw']) && isset($allMetrics['battery_current'])) {
            $propulsionData = $this->queryMetricRange($registry, $allMetrics['battery_current'], null, $step, $start, $end, $metrics);
        }

        return Inertia::render('Admin/Explore', [
            'metric' => $metric,
            'metrics' => $grouped,
            'data' => $data,
            'overlays' => $overlays,
            'range' => $range,
            'start' => $start,
            'end' => $end,
            'step' => $step,
            'refresh' => $refresh,
            'passage' => $passage,
            'journeys' => $journeys,
            'propulsionData' => $propulsionData,
        ]);
    }

    public function series(Request $request, MetricRegistry $registry, MetricsService $metrics): JsonResponse
    {
        $allMetrics = config('scarlet.metrics.mappings.explore');
        $start = (int) $request->query('start');
        $end = (int) $request->query('end');
        $step = $request->query('step', '300s');

        $slugs = [];
        if ($request->query('metrics')) {
            $slugs = array_map('trim', explode(',', $request->query('metrics')));
        } elseif ($request->query('metric')) {
            $slugs = [trim($request->query('metric'))];
        }

        $results = [];
        foreach ($slugs as $slug) {
            if (! isset($allMetrics[$slug])) {
                return response()->json(['error' => "Unknown metric: {$slug}"], 422);
            }
            $data = $this->queryMetricRange($registry, $allMetrics[$slug], null, $step, $start, $end, $metrics);
            $values = array_column($data, 'value');

            $results[] = [
                'metric' => $slug,
                'data' => $data,
                'stats' => [
                    'current' => ! empty($values) ? end($values) : null,
                    'min' => ! empty($values) ? min($values) : null,
                    'max' => ! empty($values) ? max($values) : null,
                    'avg' => ! empty($values) ? round(array_sum($values) / count($values), 2) : null,
                ],
            ];
        }

        return response()->json($results);
    }

    private function resolveTimeRange(Request $request, string $range): array
    {
        $end = (int) $request->query('end', now()->timestamp);
        $start = (int) $request->query('start', 0);

        if ($start > 0) {
            $step = $this->calculateStep($end - $start);

            return [$start, $end, $step];
        }

        if ($range === 'passage') {
            $passage = $this->getPassageData();
            if ($passage['available']) {
                $step = $this->calculateStep($passage['end'] - $passage['start']);

                return [$passage['start'], $passage['end'], $step];
            }
            $range = '24h';
        }

        $step = self::STEP_MAP[$range] ?? '300s';
        $duration = $this->rangeToDuration($range);
        $start = now()->subSeconds($duration)->timestamp;

        return [$start, $end, $step];
    }

    private function rangeToDuration(string $range): int
    {
        return match ($range) {
            '1h' => 3600,
            '6h' => 21600,
            '12h' => 43200,
            '24h' => 86400,
            '3d' => 259200,
            '7d' => 604800,
            '30d' => 2592000,
            default => 86400,
        };
    }

    private function calculateStep(int $durationSeconds): string
    {
        $step = max(15, (int) floor($durationSeconds / 300));

        return $step.'s';
    }

    public function current(MetricRegistry $registry, MetricsService $metrics): JsonResponse
    {
        $allMetrics = config('scarlet.metrics.mappings.explore');
        $keys = [];
        foreach ($allMetrics as $slug => $metric) {
            if (! empty($metric['computed'])) {
                continue;
            }
            $keys[] = $metric['metric'];
        }

        $registryValues = $registry->fetchInstant(array_unique($keys));

        $values = [];
        foreach ($allMetrics as $slug => $metric) {
            if (! empty($metric['computed'])) {
                continue;
            }
            $values[$slug] = $registryValues[$metric['metric']] ?? null;
        }

        $computedWind = $metrics->getLatestTrueWind();
        if ($computedWind) {
            $values['wind_speed_true'] = $computedWind['speed'] ?? null;
            $values['wind_direction_true'] = $computedWind['direction'] ?? null;
        }

        $batteryPower = $this->computeBatteryPower($registry);
        if ($batteryPower !== null) {
            $values['battery_power'] = $batteryPower;
        }

        return response()->json($values);
    }

    private function dashboard(Request $request, MetricRegistry $registry, MetricsService $metrics)
    {
        $allMetrics = config('scarlet.metrics.mappings.explore');
        $groups = config('scarlet.metrics.mappings.explore_groups');

        $grouped = [];
        foreach ($allMetrics as $slug => $metric) {
            $metric['slug'] = $slug;
            $grouped[$metric['group']][$slug] = $metric;
        }

        $keys = [];
        foreach ($allMetrics as $slug => $metric) {
            if (! empty($metric['computed'])) {
                continue;
            }
            $keys[] = $metric['metric'];
        }

        $registryValues = $registry->fetchInstant(array_unique($keys));

        $currentValues = [];
        foreach ($allMetrics as $slug => $metric) {
            if (! empty($metric['computed'])) {
                continue;
            }
            $currentValues[$slug] = $registryValues[$metric['metric']] ?? null;
        }

        $computedWind = $metrics->getLatestTrueWind();
        if ($computedWind) {
            $currentValues['wind_speed_true'] = $computedWind['speed'] ?? null;
            $currentValues['wind_direction_true'] = $computedWind['direction'] ?? null;
        }

        $batteryPower = $this->computeBatteryPower($registry);
        if ($batteryPower !== null) {
            $currentValues['battery_power'] = $batteryPower;
        }

        return Inertia::render('Admin/ExploreDashboard', [
            'groups' => $groups,
            'metrics' => $grouped,
            'currentValues' => $currentValues,
        ]);
    }

    private function queryMetricRange(MetricRegistry $registry, array $metric, ?string $duration, string $step, int $start, int $end, ?MetricsService $metrics = null): array
    {
        if (! empty($metric['computed'])) {
            if ($metrics) {
                $field = match ($metric['computed']) {
                    'true_wind_speed' => 'speed',
                    'true_wind_direction' => 'direction',
                    default => null,
                };
                if ($field) {
                    return $metrics->getTrueWindSeries($field, $duration, $step, $start, $end);
                }

                if ($metric['computed'] === 'battery_power') {
                    $current = $registry->fetchRange('house_battery_current', $step, $start, $end, fillGaps: true);
                    $voltage = $registry->fetchRange('house_battery_voltage', $step, $start, $end, fillGaps: true);
                    $voltByTs = collect($voltage)->keyBy('timestamp');

                    return collect($current)->map(function (array $point) use ($voltByTs): array {
                        $v = $voltByTs->get($point['timestamp']);
                        $value = ($point['value'] !== null && $v && $v['value'] !== null)
                            ? $point['value'] * $v['value']
                            : null;

                        return ['timestamp' => $point['timestamp'], 'value' => $value];
                    })->all();
                }
            }

            return [];
        }

        $registryKey = $metric['metric'];

        return $registry->fetchRangeWithFallback($registryKey, $step, $start, $end);
    }

    private function computeBatteryPower(MetricRegistry $registry): ?float
    {
        $values = $registry->fetchInstant(['house_battery_current', 'house_battery_voltage']);
        $current = $values['house_battery_current'] ?? null;
        $voltage = $values['house_battery_voltage'] ?? null;

        if ($current === null || $voltage === null) {
            return null;
        }

        return $current * $voltage;
    }

    private function getPassageData(): array
    {
        $journey = Journey::latest('started_at')->first();

        if (! $journey || ! $journey->started_at) {
            return ['available' => false, 'start' => null, 'end' => null];
        }

        return [
            'available' => true,
            'start' => $journey->started_at->timestamp,
            'end' => $journey->ended_at?->timestamp ?? now()->timestamp,
        ];
    }
}
