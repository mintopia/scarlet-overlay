<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\MapsLegacyMetricKeys;
use App\Http\Controllers\Controller;
use App\Services\CanonicalReader;
use App\Services\MetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminEnvironmentController extends Controller
{
    use MapsLegacyMetricKeys;

    private const RANGE_MAP = [
        '6h' => ['duration' => 21600, 'step' => '60s'],
        '24h' => ['duration' => 86400, 'step' => '300s'],
        '7d' => ['duration' => 604800, 'step' => '1800s'],
        '30d' => ['duration' => 2592000, 'step' => '7200s'],
    ];

    public function index(MetricsService $metrics)
    {
        return Inertia::render('Admin/Environment', [
            'boat' => $metrics->getBoatMetrics(),
            'weather' => $metrics->getWeatherData(),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function series(Request $request, CanonicalReader $canonical): JsonResponse
    {
        $range = $request->query('range', '24h');
        $config = self::RANGE_MAP[$range] ?? self::RANGE_MAP['24h'];

        $end = now()->timestamp;
        $start = $end - $config['duration'];
        $step = $config['step'];

        $slugs = array_map('trim', explode(',', $request->query('metrics', '')));
        $allMetrics = config('scarlet.metrics.mappings.explore');

        $results = [];
        foreach ($slugs as $slug) {
            if (! $slug || ! isset($allMetrics[$slug])) {
                continue;
            }

            $registryKey = $allMetrics[$slug]['metric'] ?? null;
            if ($registryKey === null) {
                continue;
            }

            $data = $this->legacyRange($canonical, $registryKey, $step, $start, $end);
            $values = array_column($data, 'value');

            $results[$slug] = [
                'data' => $data,
                'current' => ! empty($values) ? end($values) : null,
                'min' => ! empty($values) ? min($values) : null,
                'max' => ! empty($values) ? max($values) : null,
            ];
        }

        return response()->json($results);
    }
}
