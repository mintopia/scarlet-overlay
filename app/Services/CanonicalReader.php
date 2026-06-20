<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\DerivedMetrics;

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

        // Derived metric: computed at read time from other canonical metrics
        // (ADR 0007), not resolved from a VM source series.
        if (! empty($def['derived_fn'])) {
            return $this->readDerived($def);
        }

        // Availability gate: the metric only resolves while its gate series is fresh
        // (e.g. waypoint metrics gated on an active-route signal). Gate closed → null.
        if (! empty($def['gate']['selector'])) {
            $gate = $this->prometheus->queryWithTimestamp($def['gate']['selector']);
            $gateMax = $def['gate']['max'] ?? null;
            if ($gate === null
                || $gate['age'] > $def['staleness']
                || ($gateMax !== null && $gate['value'] > $gateMax)) {
                return null;
            }
        }

        // Pass 1: first fresh + healthy source.
        foreach ($def['sources'] as $source) {
            $staleness = $source['staleness'] ?? $def['staleness'];
            $raw = $this->prometheus->queryWithTimestamp($source['selector']);
            if ($raw === null || $raw['age'] > $staleness) {
                continue;
            }

            // Reject inactive-source sentinels (e.g. SignalK course/waypoint cluster when no route is active).
            if (! $this->isValidReading($def, $this->applyArithmetic($raw['value'], $source))) {
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

            if (! $this->isValidReading($def, $this->applyArithmetic($raw['value'], $source))) {
                continue;
            }

            return $this->build($def, $source, $raw, stale: true);
        }

        return null;
    }

    /**
     * Resolve a derived metric by reading each declared input through the normal
     * priority chain and applying the derived function (ADR 0007). The result is
     * stale if any input is stale, and null if any input is unavailable, so a
     * derived value is never fresher than its least-fresh input.
     *
     * @param  array<string, mixed>  $def
     * @return array{value: float, raw: float, unit: string, timestamp: int, age: int, stale: bool, resolved_source: string}|null
     */
    private function readDerived(array $def): ?array
    {
        $values = [];
        $age = 0;
        $stale = false;
        $timestamp = null;

        foreach (($def['derived_inputs'] ?? []) as $role => $inputKey) {
            $input = $this->read($inputKey);
            if ($input === null) {
                return null;
            }
            $values[$role] = $input['value'];
            $age = max($age, $input['age']);
            $stale = $stale || $input['stale'];
            $timestamp = $timestamp === null ? $input['timestamp'] : min($timestamp, $input['timestamp']);
        }

        $value = DerivedMetrics::compute($def['derived_fn'], $values);
        if ($value === null) {
            return null;
        }

        return [
            'value' => $value,
            'raw' => $value,
            'unit' => $def['unit'],
            'timestamp' => $timestamp ?? now()->timestamp,
            'age' => $age,
            'stale' => $stale,
            'resolved_source' => 'derived:'.$def['derived_fn'],
        ];
    }

    /**
     * Resolve a derived metric's history by reading each input's range over the same
     * window and combining them point-by-point with the derived function (ADR 0007).
     * Only timestamps where every input has a value contribute a point — a missing
     * input drops that timestamp rather than feeding the function a 0. The result is
     * never fresher or denser than its least-covered input.
     *
     * @param  array<string, mixed>  $def
     * @return array<int, array{t: int, v: float}>
     */
    private function readDerivedRange(array $def, ?string $duration, string $step, ?int $start, ?int $end, bool $fillGaps): array
    {
        $inputs = [];
        foreach (($def['derived_inputs'] ?? []) as $role => $inputKey) {
            $byTimestamp = [];
            foreach ($this->readRange($inputKey, $duration, $step, $start, $end, $fillGaps) as $point) {
                $byTimestamp[$point['t']] = $point['v'];
            }

            if ($byTimestamp === []) {
                return [];
            }

            $inputs[$role] = $byTimestamp;
        }

        if ($inputs === []) {
            return [];
        }

        $roles = array_keys($inputs);
        $points = [];
        foreach ($inputs[$roles[0]] as $t => $value) {
            $values = [];
            foreach ($roles as $role) {
                if (! array_key_exists($t, $inputs[$role])) {
                    continue 2;
                }
                $values[$role] = $inputs[$role][$t];
            }

            $computed = DerivedMetrics::compute($def['derived_fn'], $values);
            if ($computed === null || ! $this->isValidReading($def, $computed)) {
                continue;
            }

            $points[(int) $t] = ['t' => (int) $t, 'v' => $computed];
        }

        ksort($points);

        return array_values($points);
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
     * Resolve the canonical key at a past instant (e.g. ship-log generation at an hour
     * boundary). Walks the priority chain using last-known value at/before the timestamp,
     * applies the gate + validity, and returns the first valid source. No trend/median.
     *
     * @return array{value: float, raw: float, unit: string, timestamp: int, age: int, stale: bool, resolved_source: string}|null
     */
    public function readAt(string $key, int $timestamp): ?array
    {
        $def = $this->catalog->definition($key);
        if ($def === null) {
            return null;
        }

        if (! empty($def['gate']['selector'])) {
            $gate = $this->prometheus->queryLastOverTimeAt($def['gate']['selector'], $timestamp);
            $gateMax = $def['gate']['max'] ?? null;
            if ($gate === null || ($gateMax !== null && $gate > $gateMax)) {
                return null;
            }
        }

        foreach ($def['sources'] as $source) {
            $raw = $this->prometheus->queryLastOverTimeAt($source['selector'], $timestamp);
            if ($raw === null) {
                continue;
            }

            $value = $this->applyArithmetic($raw, $source);
            if (! $this->isValidReading($def, $value)) {
                continue;
            }

            return [
                'value' => $value,
                'raw' => $value,
                'unit' => $def['unit'],
                'timestamp' => $timestamp,
                'age' => 0,
                'stale' => false,
                'resolved_source' => $source['selector'],
            ];
        }

        return null;
    }

    /**
     * Return a time-ordered series of transformed values, stitched left-to-right across
     * the priority chain: at each timestamp the highest-priority source with a valid value
     * wins (matching the legacy `default` fallback). Validity bounds filter per point.
     *
     * @return array<int, array{t: int, v: float}>
     */
    public function readRange(string $key, ?string $duration = null, string $step = '300s', ?int $start = null, ?int $end = null, bool $fillGaps = false): array
    {
        $def = $this->catalog->definition($key);
        if ($def === null) {
            return [];
        }

        // Derived metric: history is computed point-by-point over the inputs' series
        // (ADR 0007), not resolved from a VM source series.
        if (! empty($def['derived_fn'])) {
            return $this->readDerivedRange($def, $duration, $step, $start, $end, $fillGaps);
        }

        // Fill lowest priority first so higher-priority sources overwrite per timestamp.
        $byTimestamp = [];
        foreach (array_reverse($def['sources']) as $source) {
            $raw = $this->prometheus->queryRange($source['selector'], $duration, $step, $start, $end, $fillGaps);
            foreach ($raw as $point) {
                if (($point['value'] ?? null) === null) {
                    continue;
                }

                $value = $this->applyArithmetic((float) $point['value'], $source);
                if (! $this->isValidReading($def, $value)) {
                    continue;
                }

                $byTimestamp[(int) $point['timestamp']] = $value;
            }
        }

        if ($byTimestamp === []) {
            return [];
        }

        ksort($byTimestamp);

        $points = [];
        foreach ($byTimestamp as $t => $v) {
            $points[] = ['t' => $t, 'v' => $v];
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
     * Null Island threshold — lat/long magnitudes below this are treated as a
     * no-GPS-fix reading (0,0), matching the app-wide convention.
     */
    private const NULL_ISLAND_EPSILON = 0.1;

    /**
     * Reject readings that are not real values: outside the metric's declared
     * plausible range (display units) — discarding fixed sentinels that inactive
     * sources emit (e.g. SignalK course/waypoint values when no route is active) —
     * or, for lat/long metrics, a Null Island (0,0 / no-fix) reading.
     *
     * @param  array<string, mixed>  $def
     */
    private function isValidReading(array $def, float $value): bool
    {
        $min = $def['valid_min'] ?? null;
        $max = $def['valid_max'] ?? null;

        if ($min !== null && $value < (float) $min) {
            return false;
        }

        if ($max !== null && $value > (float) $max) {
            return false;
        }

        if (($def['reject_null_island'] ?? false) && abs($value) < self::NULL_ISLAND_EPSILON) {
            return false;
        }

        return true;
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
