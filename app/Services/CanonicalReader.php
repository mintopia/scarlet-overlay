<?php

declare(strict_types=1);

namespace App\Services;

class CanonicalReader
{
    public function __construct(
        protected PrometheusService $prometheus,
        protected CanonicalCatalog $catalog,
    ) {}

    /**
     * @return array{value: float, raw: float, unit: string, timestamp: int, age: int, stale: bool, resolved_source: string}|null
     */
    public function read(string $key): ?array
    {
        $def = $this->catalog->definition($key);
        if ($def === null) {
            return null;
        }

        // Pass 1: first fresh + healthy source.
        foreach ($def['sources'] as $source) {
            $staleness = $source['staleness'] ?? $def['staleness'];
            $raw = $this->prometheus->queryWithTimestamp($source['selector']);
            if ($raw === null || $raw['age'] > $staleness) {
                continue;
            }

            $coverage = $this->prometheus->coverageRatio($source['selector'], (int) $def['coverage_window_seconds']);
            if ($coverage === null || $coverage < $def['coverage_min']) {
                continue;
            }

            return $this->build($def, $source, $raw, stale: false);
        }

        // Pass 2: highest-priority source with any last-known value, marked stale.
        foreach ($def['sources'] as $source) {
            $raw = $this->prometheus->queryWithTimestamp($source['selector']);
            if ($raw === null) {
                continue;
            }

            return $this->build($def, $source, $raw, stale: true);
        }

        return null;
    }

    /**
     * @param  array<int, string>  $keys
     * @return array<string, array<string, mixed>|null>
     */
    public function readMany(array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = $this->read($key);
        }

        return $out;
    }

    public function catalogVersion(): int
    {
        return $this->catalog->version();
    }

    /**
     * Return a time-ordered series of transformed values for the canonical key.
     *
     * @return array<int, array{t: int, v: float}>
     */
    public function readRange(string $key, string $duration, string $step = '300s'): array
    {
        $def = $this->catalog->definition($key);
        if ($def === null) {
            return [];
        }

        $source = $def['sources'][0] ?? null;
        if ($source === null) {
            return [];
        }

        $raw = $this->prometheus->queryRange($source['selector'], $duration, $step, fillGaps: false);
        if (empty($raw)) {
            return [];
        }

        $points = [];
        foreach ($raw as $point) {
            if ($point['value'] === null) {
                continue;
            }

            $points[] = [
                't' => (int) $point['timestamp'],
                'v' => $this->applyArithmetic((float) $point['value'], $source),
            ];
        }

        return $points;
    }

    /**
     * @param  array<string, mixed>  $def
     * @param  array<string, mixed>  $source
     * @param  array{value: float, timestamp: int, age: int}  $raw
     * @return array{value: float, raw: float, unit: string, timestamp: int, age: int, stale: bool, resolved_source: string}
     */
    private function build(array $def, array $source, array $raw, bool $stale): array
    {
        $rawValue = $this->applyArithmetic($raw['value'], $source);

        if (! $stale && ($def['volatile'] ?? false)) {
            $median = $this->prometheus->aggregateOverTime($source['selector'], $def['trend_fn'], $def['trend_window']);
            $value = $median !== null ? $this->applyArithmetic($median, $source) : $rawValue;
        } else {
            $value = $rawValue;
        }

        return [
            'value' => $value,
            'raw' => $rawValue,
            'unit' => $def['unit'],
            'timestamp' => $raw['timestamp'],
            'age' => $raw['age'],
            'stale' => $stale,
            'resolved_source' => $source['selector'],
        ];
    }

    /**
     * @param  array<string, mixed>  $source
     */
    private function applyArithmetic(float $value, array $source): float
    {
        if (! empty($source['transforms'])) {
            foreach ($source['transforms'] as $t) {
                $value = $this->applyOp($value, (string) $t['op'], (float) $t['value']);
            }

            return $value;
        }

        if (isset($source['multiply'])) {
            $value *= $source['multiply'];
        }
        if (isset($source['divide']) && (float) $source['divide'] !== 0.0) {
            $value /= (float) $source['divide'];
        }
        if (isset($source['subtract'])) {
            $value -= $source['subtract'];
        }

        return $value;
    }

    private function applyOp(float $value, string $op, float $operand): float
    {
        return match ($op) {
            'multiply' => $value * $operand,
            'divide' => $operand !== 0.0 ? $value / $operand : $value,
            'subtract' => $value - $operand,
            'add' => $value + $operand,
            default => $value,
        };
    }
}
