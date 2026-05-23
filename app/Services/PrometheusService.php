<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PrometheusService
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('scarlet.metrics.prometheus_url');
    }

    public function query(string $promql): ?float
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/api/v1/query", [
                'query' => $promql,
            ]);

            if (!$response->ok()) {
                return null;
            }

            $result = $response->json('data.result');
            if (!empty($result)) {
                return (float) $result[0]['value'][1];
            }

            return $this->queryLastOverTime($promql);
        } catch (\Throwable $e) {
            Log::warning("Prometheus query failed [{$promql}]: {$e->getMessage()}");
            return null;
        }
    }

    protected function queryLastOverTime(string $promql, string $lookback = '24h'): ?float
    {
        $wrapped = preg_replace_callback(
            '/\b(scarlet_[a-zA-Z0-9_:]*)(\{[^}]*\})?/',
            fn ($m) => "last_over_time({$m[0]}[{$lookback}])",
            $promql,
        );

        if ($wrapped === $promql) {
            return null;
        }

        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/api/v1/query", [
                'query' => $wrapped,
            ]);

            if (!$response->ok()) {
                return null;
            }

            $result = $response->json('data.result');
            if (empty($result)) {
                return null;
            }

            return (float) $result[0]['value'][1];
        } catch (\Throwable $e) {
            Log::warning("Prometheus last_over_time query failed [{$promql}]: {$e->getMessage()}");
            return null;
        }
    }

    public function queryRange(string $promql, ?string $duration, string $step = '15s', ?int $start = null, ?int $end = null): array
    {
        try {
            $response = Http::timeout(10)->get("{$this->baseUrl}/api/v1/query_range", [
                'query' => $promql,
                'start' => $start ?? ($duration ? now()->sub(\Carbon\CarbonInterval::fromString($duration))->timestamp : now()->subDay()->timestamp),
                'end' => $end ?? now()->timestamp,
                'step' => $step,
            ]);

            if (!$response->ok()) {
                return [];
            }

            $result = $response->json('data.result');
            if (empty($result)) {
                return [];
            }

            return collect($result[0]['values'])->map(fn ($v) => [
                'timestamp' => (int) $v[0],
                'value' => (float) $v[1],
            ])->all();
        } catch (\Throwable $e) {
            Log::warning("Prometheus range query failed [{$promql}]: {$e->getMessage()}");
            return [];
        }
    }

    public function queryRangeWithFallback(string $primary, string $fallback, ?string $duration, string $step = '15s', ?int $start = null, ?int $end = null): array
    {
        $data = $this->queryRange($primary, $duration, $step, $start, $end);

        if (!empty($data)) {
            $fallbackData = $this->queryRange($fallback, $duration, $step, $start, $end);
            if (!empty($fallbackData)) {
                $primaryByTs = collect($data)->keyBy('timestamp');
                foreach ($fallbackData as $point) {
                    if (!$primaryByTs->has($point['timestamp'])) {
                        $data[] = $point;
                    }
                }
                usort($data, fn ($a, $b) => $a['timestamp'] <=> $b['timestamp']);
            }
            return $data;
        }

        return $this->queryRange($fallback, $duration, $step, $start, $end);
    }

    public function queryMultiple(array $queries): array
    {
        $results = [];
        foreach ($queries as $key => $promql) {
            $results[$key] = $this->query($promql);
        }
        return $results;
    }

    /**
     * Run multiple queries and also report whether a fetch error occurred.
     * Returns ['values' => [...], 'fetchError' => bool].
     * fetchError is true when a network/HTTP exception was thrown; it is false
     * when the fetch succeeded but Prometheus returned no data (publisher offline).
     */
    public function queryMultipleWithStatus(array $queries): array
    {
        $results = [];
        $fetchError = false;

        foreach ($queries as $key => $promql) {
            try {
                $response = Http::timeout(5)->get("{$this->baseUrl}/api/v1/query", [
                    'query' => $promql,
                ]);

                if (!$response->ok()) {
                    $fetchError = true;
                    $results[$key] = null;
                    continue;
                }

                $result = $response->json('data.result');
                if (!empty($result)) {
                    $results[$key] = (float) $result[0]['value'][1];
                } else {
                    $results[$key] = $this->queryLastOverTime($promql);
                }
            } catch (\Throwable $e) {
                Log::warning("Prometheus query failed [{$promql}]: {$e->getMessage()}");
                $fetchError = true;
                $results[$key] = null;
            }
        }

        return ['values' => $results, 'fetchError' => $fetchError];
    }
}
