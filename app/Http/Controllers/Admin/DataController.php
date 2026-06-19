<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CanonicalMetric;
use App\Services\CanonicalReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DataController extends Controller
{
    private const RANGE_STEPS = [
        '6h' => '120s',
        '24h' => '300s',
        '7d' => '1800s',
        '30d' => '7200s',
    ];

    public function current(CanonicalReader $reader): JsonResponse
    {
        $metrics = CanonicalMetric::where('enabled', true)->get(['key', 'display_unit']);
        $envelopes = $reader->readMany($metrics->pluck('key')->all());

        $out = [];
        foreach ($metrics as $metric) {
            $envelope = $envelopes[$metric->key] ?? null;
            $out[$metric->key] = $envelope === null ? null : [
                'value' => $envelope['value'],
                'unit' => $metric->display_unit,
                'age_s' => $envelope['age'],
                'stale' => $envelope['stale'],
            ];
        }

        return response()->json($out);
    }

    public function show(string $metric): Response
    {
        $target = CanonicalMetric::where('key', $metric)->firstOrFail();

        return Inertia::render('Admin/MetricExplorer', [
            'metricKey' => $target->key,
            'metricLabel' => $target->label,
            'metricUnit' => $target->display_unit,
            'catalog' => CanonicalMetric::where('enabled', true)
                ->orderBy('group')->orderBy('label')
                ->get(['key', 'label', 'group', 'display_unit']),
        ]);
    }

    public function series(Request $request, CanonicalReader $reader): JsonResponse
    {
        $validated = $request->validate([
            'metrics' => ['required', 'string'],
            'range' => ['nullable', 'string'],
        ]);

        $range = $validated['range'] ?? '24h';
        if (! array_key_exists($range, self::RANGE_STEPS)) {
            $range = '24h';
        }
        $step = self::RANGE_STEPS[$range];

        $keys = array_values(array_filter(array_map('trim', explode(',', $validated['metrics']))));
        $metrics = CanonicalMetric::whereIn('key', $keys)->get()->keyBy('key');

        $out = [];
        foreach ($keys as $key) {
            $metric = $metrics->get($key);
            if ($metric === null) {
                continue;
            }
            $points = $reader->readRange($key, $range, $step);
            $current = $reader->read($key);
            $out[$key] = [
                'key' => $key,
                'label' => $metric->label,
                'unit' => $metric->display_unit,
                'data' => array_map(fn (array $point): array => ['t' => $point['t'], 'value' => $point['v']], $points),
                'current' => $current['value'] ?? null,
            ];
        }

        return response()->json($out);
    }
}
