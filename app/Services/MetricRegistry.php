<?php

declare(strict_types=1);

namespace App\Services;

class MetricRegistry
{
    public function __construct(protected PrometheusService $prometheus) {}

    public function definition(string $key): ?array
    {
        return config("scarlet.metrics.registry.{$key}");
    }

    public function instantQuery(string $key): string
    {
        $def = $this->definition($key);
        if ($def === null) {
            return $key;
        }

        $primary = $def['query'];

        if (isset($def['fallback'])) {
            $q = "({$primary}) or ({$def['fallback']})";
        } else {
            $q = $primary;
        }

        return $this->applyArithmetic($q, $def);
    }

    public function rangeQuery(string $key): string
    {
        $def = $this->definition($key);
        if ($def === null) {
            return $key;
        }

        $sticky = $def['sticky'] ?? false;
        $wrappedPrimary = $sticky ? $this->wrapMetricSticky($def['query']) : $this->wrapMetric($def['query']);
        $wrappedPrimary = $this->applyArithmetic($wrappedPrimary, $def);

        if (isset($def['fallback'])) {
            $wrappedFallback = $sticky ? $this->wrapMetricSticky($def['fallback']) : $this->wrapMetric($def['fallback']);
            $wrappedFallback = $this->applyArithmetic($wrappedFallback, $def);

            return "({$wrappedPrimary}) default ({$wrappedFallback})";
        }

        return $wrappedPrimary;
    }

    public function fetchInstant(array $keys, ?int $timestamp = null): array
    {
        $queries = [];
        foreach ($keys as $key) {
            $queries[$key] = $this->instantQuery($key);
        }

        return $this->prometheus->queryMultipleAt($queries, $timestamp ?? now()->timestamp, fallback: true);
    }

    public function fetchInstantWithAge(array $keys): array
    {
        $results = [];
        foreach ($keys as $key) {
            $query = $this->instantQuery($key);
            $data = $this->prometheus->queryWithTimestamp($query);
            $results[$key] = $data;
        }

        return $results;
    }

    public function fetchRange(string $key, string $step, ?int $start = null, ?int $end = null, bool $fillGaps = true): array
    {
        return $this->prometheus->queryRange($this->rangeQuery($key), null, $step, $start, $end, $fillGaps);
    }

    public function fetchRangeWithFallback(string $key, string $step, ?int $start = null, ?int $end = null): array
    {
        $def = $this->definition($key);

        if ($def !== null && isset($def['fallback'])) {
            $sticky = $def['sticky'] ?? false;
            $wrap = fn (string $q) => $sticky ? $this->wrapMetricSticky($q) : $this->wrapMetric($q);

            $primaryWrapped = $this->applyArithmetic($wrap($def['query']), $def);
            $fallbackWrapped = $this->applyArithmetic($wrap($def['fallback']), $def);

            return $this->prometheus->queryRangeWithFallback($primaryWrapped, $fallbackWrapped, null, $step, $start, $end);
        }

        return $this->fetchRange($key, $step, $start, $end);
    }

    public function groupKeys(string $group): array
    {
        return config("scarlet.metrics.groups.{$group}") ?? [];
    }

    protected function wrapMetric(string $query): string
    {
        return preg_replace_callback(
            '/\b(scarlet_[a-zA-Z0-9_:]*)(\{[^}]*\})?( != 0)?/',
            fn (array $m) => 'max('.$m[1].($m[2] ?? '').')'.($m[3] ?? ''),
            $query,
        );
    }

    protected function wrapMetricSticky(string $query): string
    {
        return preg_replace_callback(
            '/\b(scarlet_[a-zA-Z0-9_:]*)(\{[^}]*\})?( != 0)?/',
            fn (array $m) => 'max(keep_last_value('.$m[1].($m[2] ?? '').'))'.($m[3] ?? ''),
            $query,
        );
    }

    protected function applyArithmetic(string $query, array $def): string
    {
        if (isset($def['multiply'])) {
            $query = "({$query}) * {$def['multiply']}";
        }

        if (isset($def['divide'])) {
            $query = "({$query}) / {$def['divide']}";
        }

        if (isset($def['subtract'])) {
            $query = "({$query}) - {$def['subtract']}";
        }

        return $query;
    }
}
