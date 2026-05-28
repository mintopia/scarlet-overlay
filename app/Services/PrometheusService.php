<?php

namespace App\Services;

use Carbon\CarbonInterval;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PrometheusService
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('scarlet.metrics.prometheus_url');
    }

    public function queryAt(string $promql, int $timestamp): ?float
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/api/v1/query", [
                'query' => $promql,
                'time' => $timestamp,
            ]);

            if (! $response->ok()) {
                return null;
            }

            $result = $response->json('data.result');

            return ! empty($result) ? (float) $result[0]['value'][1] : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function queryLastOverTimeAt(string $promql, int $timestamp, string $lookback = '7d'): ?float
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
            $response = Http::timeout(30)->get("{$this->baseUrl}/api/v1/query", [
                'query' => $wrapped,
                'time' => $timestamp,
            ]);

            if (! $response->ok()) {
                return null;
            }

            $result = $response->json('data.result');

            return ! empty($result) ? (float) $result[0]['value'][1] : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function queryMultipleAt(array $queries, int $timestamp, bool $fallback = false, string $fallbackLookback = '7d'): array
    {
        $responses = Http::pool(function ($pool) use ($queries, $timestamp) {
            foreach ($queries as $key => $promql) {
                $pool->as($key)->timeout(5)->get("{$this->baseUrl}/api/v1/query", [
                    'query' => $promql,
                    'time' => $timestamp,
                ]);
            }
        });

        $results = [];
        $missingKeys = [];
        foreach ($queries as $key => $promql) {
            try {
                $response = $responses[$key] ?? null;
                if ($response && $response->ok()) {
                    $result = $response->json('data.result');
                    $results[$key] = ! empty($result) ? (float) $result[0]['value'][1] : null;
                } else {
                    $results[$key] = null;
                }
            } catch (\Throwable $e) {
                $results[$key] = null;
            }

            if ($results[$key] === null && $fallback) {
                $missingKeys[$key] = $promql;
            }
        }

        if (! empty($missingKeys)) {
            foreach ($missingKeys as $key => $promql) {
                $results[$key] = $this->queryLastOverTimeAt($promql, $timestamp, $fallbackLookback);
            }
        }

        return $results;
    }

    public function query(string $promql): ?float
    {
        $result = $this->queryWithTimestamp($promql, '7d');

        return $result['value'] ?? null;
    }

    public function queryFresh(string $promql, int $maxAge = 120): ?float
    {
        $result = $this->queryWithTimestamp($promql, '7d');
        if ($result === null) {
            return null;
        }

        return $result['age'] <= $maxAge ? $result['value'] : null;
    }

    public function queryTimestamp(string $promql): ?int
    {
        $result = $this->queryWithTimestamp($promql, '7d');

        return $result['timestamp'] ?? null;
    }

    protected function queryWithTimestamp(string $promql, string $lookback = '24h'): ?array
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

            if (! $response->ok()) {
                return null;
            }

            $result = $response->json('data.result');
            if (empty($result)) {
                return null;
            }

            $timestamp = (int) $result[0]['value'][0];

            return [
                'value' => (float) $result[0]['value'][1],
                'timestamp' => $timestamp,
                'age' => now()->timestamp - $timestamp,
            ];
        } catch (\Throwable $e) {
            Log::warning("Prometheus query failed [{$promql}]: {$e->getMessage()}");

            return null;
        }
    }

    public function resolveLogQueries(): array
    {
        $log = config('scarlet.metrics.mappings.log');
        $resolved = [];
        foreach ($log as $key => $ref) {
            if (str_contains($ref, ':')) {
                [$group, $metricKey] = explode(':', $ref, 2);
                $resolved[$key] = config("scarlet.metrics.mappings.{$group}.{$metricKey}");
            } else {
                $resolved[$key] = $ref;
            }
        }

        return $resolved;
    }

    public function wrapForRange(string $query): string
    {
        return preg_replace_callback(
            '/\b(scarlet_[a-zA-Z0-9_:]*)(\{[^}]*\})?/',
            fn ($m) => "max(keep_last_value({$m[0]}))",
            $query,
        );
    }

    public function stripRangeWrapping(string $query): string
    {
        $query = preg_replace('/\bmax\(/', '(', $query);

        return str_replace('keep_last_value(', '(', $query);
    }

    public function queryRange(string $promql, ?string $duration, string $step = '15s', ?int $start = null, ?int $end = null, bool $fillGaps = true): array
    {
        try {
            $stepSeconds = (int) $step;
            $rangeStart = $start ?? ($duration ? now()->sub(CarbonInterval::fromString($duration))->timestamp : now()->subDay()->timestamp);
            $rangeEnd = $end ?? now()->timestamp;

            // VictoriaMetrics aligns range query timestamps to epoch step boundaries
            $rangeStart = (int) floor($rangeStart / $stepSeconds) * $stepSeconds;
            $rangeEnd = (int) ceil($rangeEnd / $stepSeconds) * $stepSeconds;

            $response = Http::timeout(10)->get("{$this->baseUrl}/api/v1/query_range", [
                'query' => $promql,
                'start' => $rangeStart,
                'end' => $rangeEnd,
                'step' => $step,
            ]);

            if (! $response->ok()) {
                return [];
            }

            $result = $response->json('data.result');
            if (empty($result)) {
                return [];
            }

            if (! $fillGaps) {
                return collect($result[0]['values'])->map(fn ($v) => [
                    'timestamp' => (int) $v[0],
                    'value' => (float) $v[1],
                ])->all();
            }

            $byTimestamp = collect($result[0]['values'])->keyBy(fn ($v) => (int) $v[0]);

            $filled = [];
            for ($ts = $rangeStart; $ts <= $rangeEnd; $ts += $stepSeconds) {
                $point = $byTimestamp->get($ts);
                $filled[] = [
                    'timestamp' => $ts,
                    'value' => $point !== null ? (float) $point[1] : null,
                ];
            }

            return $filled;
        } catch (\Throwable $e) {
            Log::warning("Prometheus range query failed [{$promql}]: {$e->getMessage()}");

            return [];
        }
    }

    public function queryRangeWithFallback(string $primary, string $fallback, ?string $duration, string $step = '15s', ?int $start = null, ?int $end = null): array
    {
        $data = $this->queryRange($primary, $duration, $step, $start, $end);

        if (! empty($data)) {
            $fallbackData = $this->queryRange($fallback, $duration, $step, $start, $end);
            if (! empty($fallbackData)) {
                $primaryByTs = collect($data)->keyBy('timestamp');
                foreach ($fallbackData as $point) {
                    $existing = $primaryByTs->get($point['timestamp']);
                    if (! $existing || $existing['value'] === null) {
                        $primaryByTs[$point['timestamp']] = $point;
                    }
                }
                $data = $primaryByTs->sortKeys()->values()->all();
            }

            return $data;
        }

        return $this->queryRange($fallback, $duration, $step, $start, $end);
    }

    public function queryMultiple(array $queries, string $fallbackLookback = '7d'): array
    {
        $responses = Http::pool(function ($pool) use ($queries) {
            foreach ($queries as $key => $promql) {
                $pool->as($key)->timeout(5)->get("{$this->baseUrl}/api/v1/query", [
                    'query' => $promql,
                ]);
            }
        });

        $results = [];
        $missingKeys = [];
        foreach ($queries as $key => $promql) {
            try {
                $response = $responses[$key] ?? null;
                if ($response && $response->ok()) {
                    $result = $response->json('data.result');
                    $results[$key] = ! empty($result) ? (float) $result[0]['value'][1] : null;
                } else {
                    $results[$key] = null;
                }
            } catch (\Throwable $e) {
                Log::warning("Prometheus query failed [{$promql}]: {$e->getMessage()}");
                $results[$key] = null;
            }

            if ($results[$key] === null) {
                $missingKeys[$key] = $promql;
            }
        }

        if (! empty($missingKeys)) {
            foreach ($missingKeys as $key => $promql) {
                $results[$key] = $this->queryLastOverTimeAt($promql, now()->timestamp, $fallbackLookback);
            }
        }

        return $results;
    }

    /**
     * Run multiple queries and also report whether a fetch error occurred.
     * Returns ['values' => [...], 'fetchError' => bool].
     * fetchError is true when a network/HTTP exception was thrown; it is false
     * when the fetch succeeded but Prometheus returned no data (publisher offline).
     */
    public function queryMultipleWithStatus(array $queries, int $maxAge = 120): array
    {
        $results = [];
        $fetchError = false;

        foreach ($queries as $key => $promql) {
            try {
                $fresh = $this->queryFresh($promql, $maxAge);
                $results[$key] = $fresh;
            } catch (\Throwable $e) {
                Log::warning("Prometheus query failed [{$promql}]: {$e->getMessage()}");
                $fetchError = true;
                $results[$key] = null;
            }
        }

        return ['values' => $results, 'fetchError' => $fetchError];
    }
}
