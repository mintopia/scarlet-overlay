<?php

namespace App\Services;

use Carbon\CarbonInterval;
use Illuminate\Http\Client\Pool;
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
            Log::warning("Prometheus query failed [{$promql}]: {$e->getMessage()}");

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
            Log::warning("Prometheus query failed [{$promql}]: {$e->getMessage()}");

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
                Log::warning("Prometheus query failed [{$promql}]: {$e->getMessage()}");
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
        $wrapped = preg_replace_callback(
            '/\b(scarlet_[a-zA-Z0-9_:]*)(\{[^}]*\})?/',
            fn ($m) => "timestamp(last_over_time({$m[0]}[7d]))",
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

            return ! empty($result) ? (int) $result[0]['value'][1] : null;
        } catch (\Throwable $e) {
            Log::warning("Prometheus timestamp query failed [{$promql}]: {$e->getMessage()}");

            return null;
        }
    }

    public function queryWithTimestamp(string $promql, string $lookback = '7d'): ?array
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

    public function queryRange(string $promql, ?string $duration, string $step = '15s', ?int $start = null, ?int $end = null, bool $fillGaps = true): array
    {
        try {
            $stepSeconds = (int) $step;
            $rangeStart = $start ?? ($duration ? now()->sub(CarbonInterval::fromString($duration))->timestamp : now()->subDay()->timestamp);
            $rangeEnd = $end ?? now()->timestamp;

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

    public function queryMultipleWithStatus(array $queries, int $maxAge = 120): array
    {
        $results = [];
        $fetchError = false;

        $wrappedQueries = [];
        foreach ($queries as $key => $promql) {
            $wrapped = preg_replace_callback(
                '/\b(scarlet_[a-zA-Z0-9_:]*)(\{[^}]*\})?/',
                fn ($m) => "last_over_time({$m[0]}[7d])",
                $promql,
            );

            if ($wrapped === $promql) {
                $results[$key] = null;
            } else {
                $wrappedQueries[$key] = $wrapped;
            }
        }

        if (empty($wrappedQueries)) {
            return ['values' => $results, 'fetchError' => $fetchError];
        }

        $responses = Http::pool(function (Pool $pool) use ($wrappedQueries) {
            foreach ($wrappedQueries as $key => $wrapped) {
                $pool->as($key)->timeout(5)->get("{$this->baseUrl}/api/v1/query", [
                    'query' => $wrapped,
                ]);
            }
        });

        foreach ($wrappedQueries as $key => $wrapped) {
            try {
                $response = $responses[$key] ?? null;

                if ($response instanceof \Throwable) {
                    throw $response;
                }

                if (! $response || ! $response->ok()) {
                    $results[$key] = null;

                    continue;
                }

                $result = $response->json('data.result');
                if (empty($result)) {
                    $results[$key] = null;

                    continue;
                }

                $timestamp = (int) $result[0]['value'][0];
                $age = now()->timestamp - $timestamp;
                $results[$key] = $age <= $maxAge ? (float) $result[0]['value'][1] : null;
            } catch (\Throwable $e) {
                Log::warning("Prometheus query failed [{$queries[$key]}]: {$e->getMessage()}");
                $fetchError = true;
                $results[$key] = null;
            }
        }

        return ['values' => $results, 'fetchError' => $fetchError];
    }
}
