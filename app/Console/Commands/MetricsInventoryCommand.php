<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\PrometheusService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MetricsInventoryCommand extends Command
{
    protected $signature = 'metrics:inventory {--prefix=scarlet}';

    protected $description = 'Inventory all live VictoriaMetrics series and report config drift';

    public function handle(PrometheusService $prometheus): int
    {
        $prefix = (string) $this->option('prefix');
        $date = now()->format('Y-m-d');

        $allNames = $prometheus->labelValues('__name__');
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
        $registry = config('scarlet.metrics.registry', []);
        $lines = ['# Drift report '.now()->format('Y-m-d'), '', 'Registry queries whose base metric is absent from live VM:', ''];

        foreach ($registry as $key => $def) {
            foreach (['query', 'fallback'] as $field) {
                if (! isset($def[$field])) {
                    continue;
                }
                if (preg_match('/\b(scarlet_[a-zA-Z0-9_:]*)/', (string) $def[$field], $m) !== 1) {
                    continue;
                }
                if (! in_array($m[1], $liveNames, true)) {
                    $lines[] = "- `{$key}` ({$field}) → `{$m[1]}` NOT FOUND live";
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
