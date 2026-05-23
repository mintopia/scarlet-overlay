<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Journey;
use App\Services\MetricsService;
use App\Services\PrometheusService;
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

    public function index(Request $request, PrometheusService $prometheus, MetricsService $metrics)
    {
        $slug = $request->query('metric');
        $allMetrics = config('scarlet.metrics.mappings.explore');

        if (!$slug || !isset($allMetrics[$slug])) {
            return redirect()->route('admin.metrics');
        }

        $metric = $allMetrics[$slug];
        $metric['slug'] = $slug;

        $range = $request->query('range', '24h');
        [$start, $end, $step] = $this->resolveTimeRange($request, $range);

        $data = $this->queryMetricRange($prometheus, $metric, null, $step, $start, $end, $metrics);

        $overlays = [];
        $overlayParam = $request->query('overlay', '');
        if ($overlayParam) {
            foreach (explode(',', $overlayParam) as $overlaySlugs) {
                $os = trim($overlaySlugs);
                if (isset($allMetrics[$os]) && $os !== $slug) {
                    $overlays[] = [
                        'metric' => array_merge($allMetrics[$os], ['slug' => $os]),
                        'data' => $this->queryMetricRange($prometheus, $allMetrics[$os], null, $step, $start, $end, $metrics),
                    ];
                }
            }
        }

        $grouped = [];
        foreach ($allMetrics as $s => $m) {
            $grouped[$m['group']][$s] = $m;
        }

        $passage = $this->getPassageData();

        $refresh = (int) $request->query('refresh', self::DEFAULT_REFRESH[$range] ?? 0);

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
        ]);
    }

    public function series(Request $request, PrometheusService $prometheus, MetricsService $metrics): JsonResponse
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
            if (!isset($allMetrics[$slug])) {
                return response()->json(['error' => "Unknown metric: {$slug}"], 422);
            }
            $data = $this->queryMetricRange($prometheus, $allMetrics[$slug], null, $step, $start, $end, $metrics);
            $values = array_column($data, 'value');

            $results[] = [
                'metric' => $slug,
                'data' => $data,
                'stats' => [
                    'current' => !empty($values) ? end($values) : null,
                    'min' => !empty($values) ? min($values) : null,
                    'max' => !empty($values) ? max($values) : null,
                    'avg' => !empty($values) ? round(array_sum($values) / count($values), 2) : null,
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
        return $step . 's';
    }

    private function queryMetricRange(PrometheusService $prometheus, array $metric, ?string $duration, string $step, int $start, int $end, ?MetricsService $metrics = null): array
    {
        if (!empty($metric['computed']) && $metrics) {
            $field = match ($metric['computed']) {
                'true_wind_speed' => 'speed',
                'true_wind_direction' => 'direction',
                default => null,
            };
            if ($field) {
                return $metrics->getTrueWindSeries($field, $duration, $step, $start, $end);
            }
        }
        if (!empty($metric['fallback'])) {
            return $prometheus->queryRangeWithFallback($metric['query'], $metric['fallback'], $duration, $step, $start, $end);
        }
        return $prometheus->queryRange($metric['query'] ?? '', $duration, $step, $start, $end);
    }

    private function getPassageData(): array
    {
        $journey = Journey::latest('started_at')->first();

        if (!$journey || !$journey->started_at) {
            return ['available' => false, 'start' => null, 'end' => null];
        }

        return [
            'available' => true,
            'start' => $journey->started_at->timestamp,
            'end' => $journey->ended_at?->timestamp ?? now()->timestamp,
        ];
    }
}
