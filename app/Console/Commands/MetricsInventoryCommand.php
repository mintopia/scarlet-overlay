<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\CanonicalCatalog;
use App\Services\PrometheusService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MetricsInventoryCommand extends Command
{
    protected $signature = 'metrics:inventory {--prefix=scarlet}';

    protected $description = 'Inventory all live VictoriaMetrics series and report config drift';

    public function __construct(private CanonicalCatalog $catalog)
    {
        parent::__construct();
    }

    public function handle(PrometheusService $prometheus): int
    {
        $prefix = (string) $this->option('prefix');
        $date = now()->format('Y-m-d');

        $end = now()->timestamp;
        $start = $end - (int) config('scarlet.metrics.inventory_lookback_days', 30) * 86400;
        $allNames = $prometheus->labelValues('__name__', $start, $end);
        $names = array_values(array_filter($allNames, fn (string $n) => str_starts_with($n, $prefix)));
        sort($names);

        $seriesByName = [];
        foreach ($names as $name) {
            $seriesByName[$name] = $prometheus->series($name);
        }

        $inventory = [
            'generated_at' => now()->toIso8601String(),
            'metric_names' => $names,
            'series' => $seriesByName,
        ];

        Storage::disk('local')->put("metrics/inventory-{$date}.json", json_encode($inventory, JSON_PRETTY_PRINT));

        $drift = $this->buildDriftReport($names);
        Storage::disk('local')->put("metrics/drift-{$date}.md", $drift);

        $matrix = $this->buildInclusionMatrix($names);
        Storage::disk('local')->put("metrics/inclusion-matrix-{$date}.csv", $matrix);

        $this->info(count($names).' metrics inventoried for '.$date);

        return self::SUCCESS;
    }

    /**
     * @param  array<int, string>  $liveNames
     */
    private function buildDriftReport(array $liveNames): string
    {
        $catalog = $this->catalog->all();
        $lines = ['# Drift report '.now()->format('Y-m-d'), '', 'Canonical source selectors whose base metric is absent from live VM:', ''];

        foreach ($catalog as $key => $def) {
            foreach (($def['sources'] ?? []) as $i => $source) {
                $selector = $source['selector'] ?? '';
                if (preg_match('/\b(scarlet_[a-zA-Z0-9_:]*)/', $selector, $m) !== 1) {
                    continue;
                }
                if (! in_array($m[1], $liveNames, true)) {
                    $priority = $i + 1;
                    $lines[] = "- `{$key}` (source #{$priority}) → `{$m[1]}` NOT FOUND live";
                }
            }
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * @param  array<int, string>  $liveNames
     */
    private function buildInclusionMatrix(array $liveNames): string
    {
        $rows = ['metric_name,decision,canonical_key,notes'];
        foreach ($liveNames as $name) {
            $rows[] = "{$name},,,";
        }

        return implode("\n", $rows)."\n";
    }
}
