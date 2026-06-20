<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CanonicalCatalogVersion;
use App\Models\CanonicalMetric;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CanonicalCatalog
{
    private const VERSION_KEY = 'canonical.catalog.version';

    /**
     * @return array<string, mixed>|null
     */
    public function definition(string $key): ?array
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return Cache::rememberForever($this->cacheKey(), fn () => $this->compileAll());
    }

    public function version(): int
    {
        $cached = Cache::get(self::VERSION_KEY);
        if ($cached !== null) {
            return (int) $cached;
        }

        $version = (int) (CanonicalCatalogVersion::max('version') ?? 0);
        Cache::forever(self::VERSION_KEY, $version);

        return $version;
    }

    /**
     * @param  array<int, array<string, mixed>>  $definitions
     */
    public function applyBaseline(array $definitions, string $action, ?string $actor = null, ?string $note = null): int
    {
        $normalized = $this->normalizeForSnapshot($definitions);

        if ($normalized === $this->snapshot()) {
            return $this->version();
        }

        return $this->commit($definitions, $action, $actor, $note);
    }

    public function rollback(int $toVersion, ?string $actor = null): int
    {
        $target = CanonicalCatalogVersion::where('version', $toVersion)->firstOrFail();

        return $this->commit($target->snapshot, 'rollback', $actor, "rollback to v{$toVersion}");
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function snapshot(): array
    {
        $metrics = CanonicalMetric::with('sources')->orderBy('key')->get();

        return $this->normalizeForSnapshot($metrics->map(function (CanonicalMetric $m): array {
            return [
                'key' => $m->key, 'label' => $m->label, 'group' => $m->group,
                'storage_unit' => $m->storage_unit, 'display_unit' => $m->display_unit,
                'volatile' => $m->volatile, 'trend_fn' => $m->trend_fn, 'trend_window' => $m->trend_window,
                'staleness_threshold_s' => $m->staleness_threshold_s, 'coverage_window_s' => $m->coverage_window_s,
                'coverage_min' => $m->coverage_min, 'valid_min' => $m->valid_min, 'valid_max' => $m->valid_max,
                'reject_null_island' => $m->reject_null_island,
                'gate_metric_name' => $m->gate_metric_name, 'gate_label_matchers' => $m->gate_label_matchers ?? [],
                'gate_max_value' => $m->gate_max_value,
                'enabled' => $m->enabled, 'description' => $m->description,
                'derived_fn' => $m->derived_fn, 'derived_inputs' => $m->derived_inputs,
                'sources' => $m->sources->map(fn ($s) => [
                    'priority' => $s->priority, 'source_metric_name' => $s->source_metric_name,
                    'label_matchers' => $s->label_matchers ?? [], 'source_class' => $s->source_class,
                    'source_kind' => $s->source_kind, 'select_fn' => $s->select_fn,
                    'unit_transform' => $s->unit_transform ?? [], 'staleness_threshold_s' => $s->staleness_threshold_s,
                ])->all(),
            ];
        })->all());
    }

    /**
     * @param  array<int, array<string, mixed>>  $definitions
     */
    private function commit(array $definitions, string $action, ?string $actor, ?string $note): int
    {
        return DB::transaction(function () use ($definitions, $action, $actor, $note): int {
            CanonicalMetric::query()->delete();

            // Only write columns that exist now: reseed migrations run the current baseline
            // against whatever schema existed at that migration's point in time.
            $columns = Schema::getColumnListing((new CanonicalMetric)->getTable());

            foreach ($definitions as $def) {
                $metric = CanonicalMetric::create(collect($def)->except('sources')->only($columns)->all());
                foreach ($def['sources'] as $source) {
                    $metric->sources()->create($source);
                }
            }

            $newVersion = (int) (CanonicalCatalogVersion::max('version') ?? 0) + 1;
            CanonicalCatalogVersion::create([
                'version' => $newVersion, 'action' => $action, 'actor' => $actor,
                'note' => $note, 'snapshot' => $this->snapshot(),
            ]);

            $this->bustCache($newVersion);

            return $newVersion;
        });
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function compileAll(): array
    {
        $out = [];
        foreach (CanonicalMetric::with('sources')->where('enabled', true)->get() as $metric) {
            $out[$metric->key] = [
                'label' => $metric->label,
                'unit' => $metric->display_unit,
                'volatile' => $metric->volatile,
                'trend_fn' => $metric->trend_fn,
                'trend_window' => $metric->trend_window,
                'staleness' => $metric->staleness_threshold_s,
                'coverage_window_seconds' => $metric->coverage_window_s,
                'coverage_min' => $metric->coverage_min,
                'valid_min' => $metric->valid_min,
                'valid_max' => $metric->valid_max,
                'reject_null_island' => $metric->reject_null_island,
                'derived_fn' => $metric->derived_fn,
                'derived_inputs' => $metric->derived_inputs ?? [],
                'gate' => $metric->gate_metric_name ? array_filter([
                    'selector' => $this->compileSelector([
                        'source_metric_name' => $metric->gate_metric_name,
                        'label_matchers' => $metric->gate_label_matchers ?? [],
                    ]),
                    'max' => $metric->gate_max_value,
                ], fn ($v) => $v !== null) : null,
                'sources' => $metric->sources->map(fn ($s) => array_filter([
                    'selector' => $this->compileSelector([
                        'source_metric_name' => $s->source_metric_name,
                        'label_matchers' => $s->label_matchers ?? [],
                    ]),
                    'transforms' => $s->unit_transform ?? [],
                    'staleness' => $s->staleness_threshold_s,
                ], fn ($v) => $v !== null))->all(),
            ];
        }

        return $out;
    }

    /**
     * Build the PromQL selector for a single source row.
     *
     * @param  array{source_metric_name:string,label_matchers?:array<int,array{label:string,op:string,value:mixed}>}  $source
     */
    public function compileSelector(array $source): string
    {
        $name = $source['source_metric_name'];
        $matchers = $source['label_matchers'] ?? [];

        if ($matchers === []) {
            return $name;
        }

        $parts = [];
        foreach ($matchers as $m) {
            $label = $m['label'];
            $op = $m['op'] ?? 'equals';
            $value = $op === 'absent' ? '' : (string) ($m['value'] ?? '');
            $parts[] = $label.'="'.$value.'"';
        }

        return $name.'{'.implode(',', $parts).'}';
    }

    public function recordVersion(string $action, ?string $actor = null, ?string $note = null): int
    {
        $snapshot = $this->snapshot();
        $newVersion = (int) (CanonicalCatalogVersion::max('version') ?? 0) + 1;

        CanonicalCatalogVersion::create([
            'version' => $newVersion,
            'action' => $action,
            'actor' => $actor,
            'note' => $note,
            'snapshot' => $snapshot,
        ]);

        $this->bustCache($newVersion);

        return $newVersion;
    }

    /**
     * @param  array{source_metric_name:string,label_matchers?:array<int,array{label:string,op:string,value:mixed}>}  $source
     * @return array{ok:bool,value:?float,age:?int,selector:string}
     */
    public function testSource(array $source): array
    {
        $selector = $this->compileSelector($source);
        $hit = app(PrometheusService::class)->queryWithTimestamp($selector);

        return [
            'ok' => $hit !== null,
            'value' => $hit['value'] ?? null,
            'age' => $hit['age'] ?? null,
            'selector' => $selector,
        ];
    }

    private function bustCache(int $newVersion): void
    {
        $oldKey = $this->cacheKey();
        Cache::forget($oldKey);
        Cache::forever(self::VERSION_KEY, $newVersion);
    }

    private function cacheKey(): string
    {
        return 'canonical.catalog.v'.$this->version();
    }

    /**
     * @param  iterable<int, array<string, mixed>>  $definitions
     * @return array<int, array<string, mixed>>
     */
    private function normalizeForSnapshot(iterable $definitions): array
    {
        return collect($definitions)->map(function (array $def): array {
            $def['coverage_min'] = (float) $def['coverage_min'];
            $def['valid_min'] = isset($def['valid_min']) ? (float) $def['valid_min'] : null;
            $def['valid_max'] = isset($def['valid_max']) ? (float) $def['valid_max'] : null;
            $def['reject_null_island'] = (bool) ($def['reject_null_island'] ?? false);
            $def['gate_metric_name'] = $def['gate_metric_name'] ?? null;
            $def['gate_label_matchers'] = $def['gate_label_matchers'] ?? [];
            $def['gate_max_value'] = isset($def['gate_max_value']) ? (float) $def['gate_max_value'] : null;
            $def['volatile'] = (bool) $def['volatile'];
            $def['enabled'] = (bool) $def['enabled'];
            $def['staleness_threshold_s'] = (int) $def['staleness_threshold_s'];
            $def['coverage_window_s'] = (int) $def['coverage_window_s'];
            $def['sources'] = collect($def['sources'])
                ->sortBy('priority')
                ->map(fn ($s) => [
                    'priority' => (int) $s['priority'],
                    'source_metric_name' => $s['source_metric_name'],
                    'label_matchers' => $s['label_matchers'] ?? [],
                    'source_class' => $s['source_class'] ?? 'both',
                    'source_kind' => $s['source_kind'] ?? null,
                    'select_fn' => $s['select_fn'] ?? 'last',
                    'unit_transform' => $s['unit_transform'] ?? [],
                    'staleness_threshold_s' => isset($s['staleness_threshold_s']) ? (int) $s['staleness_threshold_s'] : null,
                ])->values()->all();

            // Build in a fixed key order: array === comparison (idempotency check) is order-sensitive.
            $ordered = [];
            foreach ([
                'key', 'label', 'group', 'storage_unit', 'display_unit', 'volatile', 'trend_fn',
                'trend_window', 'staleness_threshold_s', 'coverage_window_s', 'coverage_min',
                'valid_min', 'valid_max', 'reject_null_island', 'gate_metric_name', 'gate_label_matchers',
                'gate_max_value', 'enabled', 'description', 'derived_fn', 'derived_inputs', 'sources',
            ] as $field) {
                $ordered[$field] = $def[$field] ?? null;
            }

            return $ordered;
        })->sortBy('key')->values()->all();
    }
}
