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
            if (empty($result)) {
                return null;
            }

            return (float) $result[0]['value'][1];
        } catch (\Throwable $e) {
            Log::warning("Prometheus query failed [{$promql}]: {$e->getMessage()}");
            return null;
        }
    }

    public function queryRange(string $promql, string $duration, string $step = '15s', ?int $start = null, ?int $end = null): array
    {
        try {
            $response = Http::timeout(10)->get("{$this->baseUrl}/api/v1/query_range", [
                'query' => $promql,
                'start' => $start ?? now()->sub(\Carbon\CarbonInterval::fromString($duration))->timestamp,
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

    public function queryMultiple(array $queries): array
    {
        $results = [];
        foreach ($queries as $key => $promql) {
            $results[$key] = $this->query($promql);
        }
        return $results;
    }
}
