<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\MapsLegacyMetricKeys;
use App\Http\Controllers\Controller;
use App\Models\Journey;
use App\Services\CanonicalReader;
use App\Services\MetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ExploreController extends Controller
{
    use MapsLegacyMetricKeys;

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

    public function index(Request $request, CanonicalReader $canonical, MetricsService $metrics)
    {
        $slug = $request->query('metric');
        $allMetrics = config('scarlet.metrics.mappings.explore');

        if (! $slug || ! isset($allMetrics[$slug])) {
            return $this->dashboard($request, $canonical, $metrics);
        }

        $metric = $allMetrics[$slug];
        $metric['slug'] = $slug;

        $passage = $this->getPassageData();
        $range = $request->query('range', $passage['available'] ? 'passage' : '24h');
        [$start, $end, $step] = $this->resolveTimeRange($request, $range);

        $data = $this->queryMetricRange($canonical, $metric, null, $step, $start, $end, $metrics);

        $overlays = [];
        $overlayParam = $request->query('overlay', '');
        if ($overlayParam) {
            foreach (explode(',', $overlayParam) as $overlaySlugs) {
                $os = trim($overlaySlugs);
                if (isset($allMetrics[$os]) && $os !== $slug) {
                    $overlays[] = [
                        'metric' => array_merge($allMetrics[$os], ['slug' => $os]),
                        'data' => $this->queryMetricRange($canonical, $allMetrics[$os], null, $step, $start, $end, $metrics),
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
            $propulsionData = $this->queryMetricRange($canonical, $allMetrics['battery_current'], null, $step, $start, $end, $metrics);
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

    public function series(Request $request, CanonicalReader $canonical, MetricsService $metrics): JsonResponse
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
            $data = $this->queryMetricRange($canonical, $allMetrics[$slug], null, $step, $start, $end, $metrics);
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

    public function current(CanonicalReader $canonical, MetricsService $metrics): JsonResponse
    {
        $values = $this->currentValues($canonical, $metrics);

        return response()->json($values);
    }

    private function dashboard(Request $request, CanonicalReader $canonical, MetricsService $metrics)
    {
        $allMetrics = config('scarlet.metrics.mappings.explore');
        $groups = config('scarlet.metrics.mappings.explore_groups');

        $grouped = [];
        foreach ($allMetrics as $slug => $metric) {
            $metric['slug'] = $slug;
            $grouped[$metric['group']][$slug] = $metric;
        }

        return Inertia::render('Admin/ExploreDashboard', [
            'groups' => $groups,
            'metrics' => $grouped,
            'currentValues' => $this->currentValues($canonical, $metrics),
        ]);
    }

    /**
     * Live current value per explore slug, resolved through the canonical reader.
     *
     * @return array<string, ?float>
     */
    private function currentValues(CanonicalReader $canonical, MetricsService $metrics): array
    {
        $allMetrics = config('scarlet.metrics.mappings.explore');

        $canonicalKeys = [];
        foreach ($allMetrics as $metric) {
            if (! empty($metric['computed'])) {
                continue;
            }
            $canonicalKey = $this->canonicalKey($metric['metric']);
            if ($canonicalKey !== null) {
                $canonicalKeys[] = $canonicalKey;
            }
        }

        $envelopes = $canonical->readMany(array_values(array_unique($canonicalKeys)));

        $values = [];
        foreach ($allMetrics as $slug => $metric) {
            if (! empty($metric['computed'])) {
                continue;
            }
            $canonicalKey = $this->canonicalKey($metric['metric']);
            $values[$slug] = $canonicalKey !== null ? ($envelopes[$canonicalKey]['value'] ?? null) : null;
        }

        $computedWind = $metrics->getLatestTrueWind();
        if ($computedWind) {
            $values['wind_speed_true'] = $computedWind['speed'] ?? null;
            $values['wind_direction_true'] = $computedWind['direction'] ?? null;
        }

        $batteryPower = $this->computeBatteryPower($canonical);
        if ($batteryPower !== null) {
            $values['battery_power'] = $batteryPower;
        }

        return $values;
    }

    private function queryMetricRange(CanonicalReader $canonical, array $metric, ?string $duration, string $step, int $start, int $end, ?MetricsService $metrics = null): array
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
                    $current = $canonical->readRange('house_battery_current', $duration, $step, $start, $end, fillGaps: true);
                    $voltage = $canonical->readRange('house_battery_voltage', $duration, $step, $start, $end, fillGaps: true);
                    $voltByTs = collect($voltage)->keyBy('t');

                    return collect($current)->map(function (array $point) use ($voltByTs): array {
                        $v = $voltByTs->get($point['t']);
                        $value = $v ? $point['v'] * $v['v'] : null;

                        return ['timestamp' => $point['t'], 'value' => $value];
                    })->all();
                }
            }

            return [];
        }

        $canonicalKey = $this->canonicalKey($metric['metric']);
        if ($canonicalKey === null) {
            return [];
        }

        return array_map(
            fn (array $p): array => ['timestamp' => $p['t'], 'value' => $p['v']],
            $canonical->readRange($canonicalKey, $duration, $step, $start, $end),
        );
    }

    private function computeBatteryPower(CanonicalReader $canonical): ?float
    {
        $envelopes = $canonical->readMany(['house_battery_current', 'house_battery_voltage']);
        $current = $envelopes['house_battery_current']['value'] ?? null;
        $voltage = $envelopes['house_battery_voltage']['value'] ?? null;

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
