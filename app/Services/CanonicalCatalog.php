<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CanonicalCatalogVersion;
use App\Models\CanonicalMetric;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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
                'coverage_min' => $m->coverage_min, 'enabled' => $m->enabled, 'description' => $m->description,
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

            foreach ($definitions as $def) {
                $metric = CanonicalMetric::create(collect($def)->except('sources')->all());
                foreach ($def['sources'] as $source) {
                    $metric->sources()->create($source);
                }
            }

            $newVersion = (int) (CanonicalCatalogVersion::max('version') ?? 0) + 1;
            CanonicalCatalogVersion::create([
                'version' => $newVersion, 'action' => $action, 'actor' => $actor,
                'note' => $note, 'snapshot' => $this->snapshot(),
            ]);

            Cache::forever(self::VERSION_KEY, $newVersion);

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
                'sources' => $metric->sources->map(fn ($s) => array_filter([
                    'selector' => $this->compileSelector($s->source_metric_name, $s->label_matchers ?? []),
                    'transforms' => $s->unit_transform ?? [],
                    'staleness' => $s->staleness_threshold_s,
                ], fn ($v) => $v !== null))->all(),
            ];
        }

        return $out;
    }

    /**
     * @param  array<int, array<string, mixed>>  $matchers
     */
    private function compileSelector(string $name, array $matchers): string
    {
        if ($matchers === []) {
            return $name;
        }

        $parts = [];
        foreach ($matchers as $m) {
            $value = ($m['op'] ?? 'equals') === 'absent' ? '' : ($m['value'] ?? '');
            $parts[] = $m['label'].'="'.$value.'"';
        }

        return $name.'{'.implode(',', $parts).'}';
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

            return collect($def)->only([
                'key', 'label', 'group', 'storage_unit', 'display_unit', 'volatile', 'trend_fn',
                'trend_window', 'staleness_threshold_s', 'coverage_window_s', 'coverage_min',
                'enabled', 'description', 'sources',
            ])->all();
        })->sortBy('key')->values()->all();
    }
}
