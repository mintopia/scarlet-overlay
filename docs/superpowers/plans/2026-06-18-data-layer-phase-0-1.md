# Data Layer — Phase 0 + 1 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the read-only inventory + restore-verified backup tooling (Phase 0) and a feature-flagged `CanonicalReader` that resolves a metric from an ordered, staleness-bounded Source Priority Chain — fixing today's blank fuel reading via the SignalK→MQTT `tanklevel` fallback (Phase 1).

**Architecture:** Phase 0 adds VM introspection methods + two artisan commands (`metrics:inventory`, `metrics:backup`) that write artifacts under `storage/app/metrics/`. Phase 1 adds a code-defined canonical baseline in `config/scarlet.php`, two new `PrometheusService` aggregation helpers, a `CanonicalReader` service implementing fresh-pass / stale-fallback resolution + median Trend Value, and a flag-gated override of `fuel_level`/`water_level` in `MetricsService::getBoatMetrics()`.

**Tech Stack:** Laravel 12, PHP 8.4, PHPUnit 11, VictoriaMetrics (Prometheus API), `Illuminate\Support\Facades\Http`. Tests fake VM with `Http::fake()` and mock `PrometheusService` with `createMock()` (see existing `tests/Feature/MetricRegistryTest.php`, `tests/Unit/PrometheusServiceTest.php`).

**Scope guardrails (from PLAN-data-layer.md / ADR 0002):** No DB catalog yet (that is Phase 2). No backfill/prune (Phases 5/8). No free-form PromQL surface. The canonical baseline lives in `config/scarlet.php` as the seed that Phase 2 will migrate to DB. The reader is OFF by default behind `scarlet.canonical.enabled`.

**Reference docs (do not duplicate — read for context):** `PLAN-data-layer.md`, `CONTEXT.md` (Data Layer Terms), `docs/adr/0002-canonical-metric-catalog-and-additive-backfill.md`.

---

## File Structure

- `app/Services/PrometheusService.php` — add `labelValues()`, `series()`, `aggregateOverTime()`, `coverageRatio()`.
- `app/Services/CanonicalReader.php` — NEW. Resolves a canonical key to the read contract.
- `app/Console/Commands/MetricsInventoryCommand.php` — NEW. `metrics:inventory`.
- `app/Console/Commands/MetricsBackupCommand.php` — NEW. `metrics:backup`.
- `config/scarlet.php` — add `canonical` section (feature flag + baseline metrics).
- `docs/data-layer/naming-convention.md`, `docs/data-layer/backup-runbook.md` — NEW docs.
- Tests: `tests/Unit/PrometheusServiceTest.php` (extend), `tests/Unit/CanonicalReaderTest.php` (new), `tests/Feature/MetricsInventoryCommandTest.php` (new), `tests/Feature/MetricsBackupCommandTest.php` (new), `tests/Feature/CanonicalFuelWaterTest.php` (new).

---

# PHASE 0 — Inventory & safety net

## Task 1: VM introspection methods on PrometheusService

**Files:**
- Modify: `app/Services/PrometheusService.php`
- Test: `tests/Unit/PrometheusServiceTest.php`

- [ ] **Step 1: Write the failing tests**

Append to `tests/Unit/PrometheusServiceTest.php`:

```php
    public function test_label_values_returns_data_array(): void
    {
        Http::fake([
            '*/api/v1/label/__name__/values*' => Http::response([
                'status' => 'success',
                'data' => ['scarlet_gps_latitude_deg', 'scarlet_mqtt_percent'],
            ]),
        ]);

        $service = new PrometheusService;
        $result = $service->labelValues('__name__');

        $this->assertSame(['scarlet_gps_latitude_deg', 'scarlet_mqtt_percent'], $result);
    }

    public function test_label_values_returns_empty_on_failure(): void
    {
        Http::fake(['*/api/v1/label/*' => Http::response('', 500)]);

        $service = new PrometheusService;
        $this->assertSame([], $service->labelValues('job'));
    }

    public function test_series_returns_label_sets(): void
    {
        Http::fake([
            '*/api/v1/series*' => Http::response([
                'status' => 'success',
                'data' => [
                    ['__name__' => 'scarlet_gps_latitude_deg', 'job' => 'boat-tracker', 'gps_source' => 'onboard'],
                ],
            ]),
        ]);

        $service = new PrometheusService;
        $result = $service->series('scarlet_gps_latitude_deg');

        $this->assertCount(1, $result);
        $this->assertSame('boat-tracker', $result[0]['job']);
    }
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --compact --filter='label_values|series_returns' tests/Unit/PrometheusServiceTest.php`
Expected: FAIL with "Call to undefined method ... labelValues()/series()".

- [ ] **Step 3: Implement the methods**

Add to `app/Services/PrometheusService.php` (before the closing brace):

```php
    /**
     * @return array<int, string>
     */
    public function labelValues(string $label): array
    {
        try {
            $response = Http::timeout(15)->get("{$this->baseUrl}/api/v1/label/{$label}/values");

            if (! $response->ok()) {
                return [];
            }

            return $response->json('data') ?? [];
        } catch (\Throwable $e) {
            Log::warning("Prometheus labelValues failed [{$label}]: {$e->getMessage()}");

            return [];
        }
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function series(string $match): array
    {
        try {
            $response = Http::timeout(30)->get("{$this->baseUrl}/api/v1/series", [
                'match[]' => $match,
            ]);

            if (! $response->ok()) {
                return [];
            }

            return $response->json('data') ?? [];
        } catch (\Throwable $e) {
            Log::warning("Prometheus series failed [{$match}]: {$e->getMessage()}");

            return [];
        }
    }
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test --compact --filter='label_values|series_returns' tests/Unit/PrometheusServiceTest.php`
Expected: PASS (3 tests).

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/PrometheusService.php tests/Unit/PrometheusServiceTest.php
git commit -m "feat(metrics): add VM label/series introspection to PrometheusService"
```

---

## Task 2: `metrics:inventory` command

Writes `storage/app/metrics/inventory-{Y-m-d}.json` (every `scarlet_*` series + label set), a drift report flagging config-registry queries whose base metric name is absent from the live `__name__` list, and an inclusion-matrix skeleton (`inclusion-matrix-{Y-m-d}.csv`, one row per live metric with a blank `decision` column).

**Files:**
- Create: `app/Console/Commands/MetricsInventoryCommand.php`
- Test: `tests/Feature/MetricsInventoryCommandTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/MetricsInventoryCommandTest.php`:

```php
<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MetricsInventoryCommandTest extends TestCase
{
    public function test_inventory_writes_json_and_drift_report(): void
    {
        Storage::fake('local');

        Config::set('scarlet.metrics.registry', [
            'fuel_level' => ['query' => 'scarlet_signalk_tanks_fuel_currentLevel', 'multiply' => 100],
            'water_temp' => ['query' => 'scarlet_signalk_environment_water_temperature', 'subtract' => 273.15],
        ]);

        Http::fake([
            '*/api/v1/label/__name__/values*' => Http::response([
                'status' => 'success',
                // note: fuel_currentLevel is ABSENT (drifted to _0_); water_temperature present
                'data' => ['scarlet_signalk_tanks_fuel_0_currentLevel', 'scarlet_signalk_environment_water_temperature'],
            ]),
            '*/api/v1/series*' => Http::response([
                'status' => 'success',
                'data' => [
                    ['__name__' => 'scarlet_signalk_tanks_fuel_0_currentLevel', 'job' => 'boat-tracker'],
                    ['__name__' => 'scarlet_signalk_environment_water_temperature', 'job' => 'boat-tracker'],
                ],
            ]),
        ]);

        $this->artisan('metrics:inventory')->assertSuccessful();

        $files = Storage::disk('local')->allFiles('metrics');
        $json = collect($files)->first(fn ($f) => str_contains($f, 'inventory-') && str_ends_with($f, '.json'));
        $this->assertNotNull($json, 'inventory json should be written');

        $payload = json_decode(Storage::disk('local')->get($json), true);
        $this->assertContains('scarlet_signalk_tanks_fuel_0_currentLevel', $payload['metric_names']);

        $drift = collect($files)->first(fn ($f) => str_contains($f, 'drift-'));
        $this->assertNotNull($drift);
        // The fuel mapping points at a name not in the live list -> flagged
        $this->assertStringContainsString('scarlet_signalk_tanks_fuel_currentLevel', Storage::disk('local')->get($drift));
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact tests/Feature/MetricsInventoryCommandTest.php`
Expected: FAIL with "command metrics:inventory not found".

- [ ] **Step 3: Implement the command**

Create `app/Console/Commands/MetricsInventoryCommand.php`:

```php
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
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --compact tests/Feature/MetricsInventoryCommandTest.php`
Expected: PASS.

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Console/Commands/MetricsInventoryCommand.php tests/Feature/MetricsInventoryCommandTest.php
git commit -m "feat(metrics): add metrics:inventory command with drift report + inclusion matrix"
```

---

## Task 3: `metrics:backup` command (VM snapshot + export manifest)

Creates a VictoriaMetrics snapshot via `/snapshot/create`, records the snapshot name + an export of all `scarlet_*` data (`/api/v1/export`) to `storage/app/metrics/backup-{timestamp}/`, and writes a manifest. (Restore-verify into a disposable VM is the runbook in Task 4 — this command produces the artifacts the runbook verifies.)

**Files:**
- Create: `app/Console/Commands/MetricsBackupCommand.php`
- Test: `tests/Feature/MetricsBackupCommandTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/MetricsBackupCommandTest.php`:

```php
<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MetricsBackupCommandTest extends TestCase
{
    public function test_backup_creates_snapshot_and_manifest(): void
    {
        Storage::fake('local');

        Http::fake([
            '*/snapshot/create*' => Http::response(['status' => 'ok', 'snapshot' => '20260618-AABBCC']),
            '*/api/v1/export*' => Http::response("{\"metric\":{\"__name__\":\"scarlet_gps_latitude_deg\"},\"values\":[1],\"timestamps\":[1]}\n"),
        ]);

        $this->artisan('metrics:backup')->assertSuccessful();

        $manifest = collect(Storage::disk('local')->allFiles('metrics'))
            ->first(fn ($f) => str_contains($f, 'manifest'));
        $this->assertNotNull($manifest);
        $this->assertStringContainsString('20260618-AABBCC', Storage::disk('local')->get($manifest));
    }

    public function test_backup_fails_when_snapshot_rejected(): void
    {
        Storage::fake('local');
        Http::fake(['*/snapshot/create*' => Http::response('', 500)]);

        $this->artisan('metrics:backup')->assertFailed();
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact tests/Feature/MetricsBackupCommandTest.php`
Expected: FAIL with "command metrics:backup not found".

- [ ] **Step 3: Implement the command**

Create `app/Console/Commands/MetricsBackupCommand.php`:

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class MetricsBackupCommand extends Command
{
    protected $signature = 'metrics:backup {--prefix=scarlet} {--match=}';

    protected $description = 'Create a VictoriaMetrics snapshot and export scarlet_* series before any normalisation';

    public function handle(): int
    {
        $baseUrl = config('scarlet.metrics.prometheus_url');
        $match = (string) ($this->option('match') ?: '{__name__=~"'.$this->option('prefix').'_.*"}');
        $stamp = now()->format('Ymd-His');

        $snapshot = Http::timeout(60)->post("{$baseUrl}/snapshot/create");
        if (! $snapshot->ok() || $snapshot->json('status') !== 'ok') {
            $this->error('Snapshot creation failed.');

            return self::FAILURE;
        }
        $snapshotName = (string) $snapshot->json('snapshot');

        $export = Http::timeout(300)->get("{$baseUrl}/api/v1/export", ['match[]' => $match]);
        if (! $export->ok()) {
            $this->error('Export failed.');

            return self::FAILURE;
        }
        $body = $export->body();
        $lineCount = substr_count($body, "\n");

        Storage::disk('local')->put("metrics/backup-{$stamp}/export.jsonl", $body);

        $manifest = [
            'created_at' => now()->toIso8601String(),
            'snapshot' => $snapshotName,
            'match' => $match,
            'export_series_lines' => $lineCount,
            'export_bytes' => strlen($body),
        ];
        Storage::disk('local')->put("metrics/backup-{$stamp}/manifest.json", json_encode($manifest, JSON_PRETTY_PRINT));

        $this->info("Backup complete: snapshot {$snapshotName}, {$lineCount} series exported.");
        $this->warn('Restore-verify into a disposable VM per docs/data-layer/backup-runbook.md before any prune.');

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --compact tests/Feature/MetricsBackupCommandTest.php`
Expected: PASS (2 tests).

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Console/Commands/MetricsBackupCommand.php tests/Feature/MetricsBackupCommandTest.php
git commit -m "feat(metrics): add metrics:backup command (VM snapshot + export manifest)"
```

---

## Task 4: Naming convention + backup/restore runbook docs (no code)

**Files:**
- Create: `docs/data-layer/naming-convention.md`
- Create: `docs/data-layer/backup-runbook.md`

- [ ] **Step 1: Write the naming-convention doc**

Create `docs/data-layer/naming-convention.md`:

```markdown
# Canonical metric naming convention

Format: `scarlet_<domain>_<quantity>_<storage-unit>`

- `<domain>`: navigation | wind | power | tank | environment | cabin | tracker | ecoflow | stream
- `<quantity>`: the physical quantity (e.g. `fuel_level`, `house_battery_soc`, `speed_sog`)
- `<storage-unit>`: the STORED unit, encoded so it can never silently change behind a key:
  `pct` (0–100), `ratio` (0–1), `kn`, `deg`, `rad`, `m`, `nm`, `c`, `k`, `hpa`, `v`, `a`, `w`, `s`.

Rules:
- The storage unit (and thus meaning) behind an existing key is **immutable**. A new unit = a new key.
- Canonical output names MUST NOT collide with raw source names (`scarlet_signalk_*`, `scarlet_mqtt_*`, `scarlet_gps_*`).
- `display_unit` (UI) is separate from `storage-unit` and may differ via an approved conversion.

Examples: `scarlet_tank_fuel_level_pct`, `scarlet_tank_water_fresh_level_pct`, `scarlet_power_house_soc_pct`.
```

- [ ] **Step 2: Write the backup runbook**

Create `docs/data-layer/backup-runbook.md`:

```markdown
# Backup & restore-verify runbook (data-layer normalisation)

A backup does not count until it has been restored into a disposable VM and verified.

## Create
1. `php artisan metrics:backup` → snapshot name + `storage/app/metrics/backup-<ts>/{export.jsonl,manifest.json}`.

## Restore-verify (mandatory before any prune)
1. Start a throwaway VictoriaMetrics container (same version) on a scratch volume.
2. Import: `curl -X POST 'http://<disposable>:8428/api/v1/import' --data-binary @export.jsonl`.
3. For 5+ representative series (incl. `scarlet_mqtt_percent{topic="tanklevel"}`, a GPS metric, an EcoFlow field):
   compare `count_over_time(<series>[<full-window>])` and a checksum of values between production and disposable.
4. Record pass/fail against `manifest.json`. Prune (Phase 8) is BLOCKED until this passes.

## Notes
- `/snapshot/create` is instant + cheap (hard links); keep snapshots until canonical data is verified.
- This runbook is the gate referenced by ADR 0002 and PLAN-data-layer.md Phase 0/8.
```

- [ ] **Step 3: Commit**

```bash
git add docs/data-layer/naming-convention.md docs/data-layer/backup-runbook.md
git commit -m "docs(data-layer): canonical naming convention + backup restore-verify runbook"
```

---

# PHASE 1 — Quick-win canonical adapter

## Task 5: Canonical baseline config + feature flag

**Files:**
- Modify: `config/scarlet.php`
- Test: `tests/Unit/CanonicalReaderTest.php` (config-shape assertion only in this task)

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/CanonicalReaderTest.php`:

```php
<?php

namespace Tests\Unit;

use Tests\TestCase;

class CanonicalReaderTest extends TestCase
{
    public function test_baseline_defines_fuel_and_water_chains(): void
    {
        $metrics = config('scarlet.canonical.metrics');

        $this->assertArrayHasKey('fuel_level', $metrics);
        $this->assertArrayHasKey('water_fresh_level', $metrics);

        // fuel chain: SignalK preferred, MQTT tanklevel fallback (the verified order)
        $fuelSources = array_column($metrics['fuel_level']['sources'], 'selector');
        $this->assertSame('scarlet_signalk_tanks_fuel_0_currentLevel', $fuelSources[0]);
        $this->assertSame('scarlet_mqtt_percent{topic="tanklevel"}', $fuelSources[1]);

        $this->assertTrue($metrics['fuel_level']['volatile']);
        $this->assertFalse(config('scarlet.canonical.enabled'));
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact tests/Unit/CanonicalReaderTest.php`
Expected: FAIL (config key `scarlet.canonical` is null).

- [ ] **Step 3: Add the config section**

In `config/scarlet.php`, inside the `'metrics' => [ ... ]` array's sibling level (top-level return array), add a new top-level `'canonical'` key. Insert immediately before the final closing `];`:

```php
    'canonical' => [
        'enabled' => (bool) env('CANONICAL_READER_ENABLED', false),

        'metrics' => [
            'fuel_level' => [
                'label' => 'Diesel',
                'unit' => '%',
                'volatile' => true,
                'trend_fn' => 'median',
                'trend_window' => '10m',
                'staleness' => 3600,
                'coverage_window_seconds' => 3600,
                'coverage_min' => 0.5,
                'sources' => [
                    ['selector' => 'scarlet_signalk_tanks_fuel_0_currentLevel', 'multiply' => 100, 'class' => 'both', 'staleness' => 1800],
                    ['selector' => 'scarlet_mqtt_percent{topic="tanklevel"}', 'class' => 'both'],
                ],
            ],
            'water_fresh_level' => [
                'label' => 'Fresh Water',
                'unit' => '%',
                'volatile' => true,
                'trend_fn' => 'median',
                'trend_window' => '10m',
                'staleness' => 3600,
                'coverage_window_seconds' => 3600,
                'coverage_min' => 0.5,
                'sources' => [
                    ['selector' => 'scarlet_signalk_tanks_freshWater_0_currentLevel', 'multiply' => 100, 'class' => 'both', 'staleness' => 1800],
                    ['selector' => 'scarlet_mqtt_percent{topic="watertank"}', 'class' => 'both'],
                ],
            ],
        ],

        // Maps a canonical key onto the legacy boat-metric key it overrides when the reader is enabled.
        'overrides' => [
            'fuel_level' => 'fuel_level',
            'water_fresh_level' => 'water_level',
        ],
    ],
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --compact tests/Unit/CanonicalReaderTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add config/scarlet.php tests/Unit/CanonicalReaderTest.php
git commit -m "feat(canonical): add code-defined canonical baseline + feature flag"
```

---

## Task 6: `aggregateOverTime()` + `coverageRatio()` on PrometheusService

**Files:**
- Modify: `app/Services/PrometheusService.php`
- Test: `tests/Unit/PrometheusServiceTest.php`

- [ ] **Step 1: Write the failing tests**

Append to `tests/Unit/PrometheusServiceTest.php`:

```php
    public function test_aggregate_over_time_returns_value(): void
    {
        Http::fake([
            '*/api/v1/query*' => Http::response([
                'status' => 'success',
                'data' => ['resultType' => 'vector', 'result' => [['value' => [1716000000, '42.5']]]],
            ]),
        ]);

        $service = new PrometheusService;
        $result = $service->aggregateOverTime('scarlet_mqtt_percent{topic="watertank"}', 'median', '10m');

        $this->assertEquals(42.5, $result);
    }

    public function test_coverage_ratio_is_count_over_expected_capped_at_one(): void
    {
        // 120 samples observed; window 3600s / step 15s = 240 expected -> 0.5
        Http::fake([
            '*/api/v1/query*' => Http::response([
                'status' => 'success',
                'data' => ['resultType' => 'vector', 'result' => [['value' => [1716000000, '120']]]],
            ]),
        ]);

        $service = new PrometheusService;
        $ratio = $service->coverageRatio('scarlet_mqtt_percent{topic="watertank"}', 3600, 15);

        $this->assertEqualsWithDelta(0.5, $ratio, 0.001);
    }

    public function test_coverage_ratio_null_when_no_data(): void
    {
        Http::fake([
            '*/api/v1/query*' => Http::response([
                'status' => 'success',
                'data' => ['resultType' => 'vector', 'result' => []],
            ]),
        ]);

        $service = new PrometheusService;
        $this->assertNull($service->coverageRatio('scarlet_absent', 3600, 15));
    }
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --compact --filter='aggregate_over_time|coverage_ratio' tests/Unit/PrometheusServiceTest.php`
Expected: FAIL with "undefined method aggregateOverTime()/coverageRatio()".

- [ ] **Step 3: Implement the methods**

Add to `app/Services/PrometheusService.php`:

```php
    public function aggregateOverTime(string $selector, string $fn, string $window): ?float
    {
        $allowed = ['median', 'avg', 'min', 'max'];
        if (! in_array($fn, $allowed, true)) {
            return null;
        }

        return $this->instantScalar("{$fn}_over_time({$selector}[{$window}])");
    }

    public function coverageRatio(string $selector, int $windowSeconds, int $stepSeconds = 15): ?float
    {
        if ($stepSeconds <= 0 || $windowSeconds <= 0) {
            return null;
        }

        $count = $this->instantScalar("count_over_time({$selector}[{$windowSeconds}s])");
        if ($count === null) {
            return null;
        }

        $expected = $windowSeconds / $stepSeconds;

        return min(1.0, $count / $expected);
    }

    private function instantScalar(string $promql): ?float
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/api/v1/query", ['query' => $promql]);

            if (! $response->ok()) {
                return null;
            }

            $result = $response->json('data.result');

            return ! empty($result) ? (float) $result[0]['value'][1] : null;
        } catch (\Throwable $e) {
            Log::warning("Prometheus instantScalar failed [{$promql}]: {$e->getMessage()}");

            return null;
        }
    }
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test --compact --filter='aggregate_over_time|coverage_ratio' tests/Unit/PrometheusServiceTest.php`
Expected: PASS (3 tests).

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/PrometheusService.php tests/Unit/PrometheusServiceTest.php
git commit -m "feat(metrics): add aggregateOverTime + coverageRatio to PrometheusService"
```

---

## Task 7: `CanonicalReader` — fresh-pass / stale-fallback resolution

The reader returns `['value','raw','unit','timestamp','age','stale','resolved_source']` or `null`.
Pass 1: first source that is fresh (`age <= staleness`) AND healthy (`coverage >= coverage_min`).
Pass 2 (only if no source is fresh): the highest-priority source that has *any* last value (last-known), marked `stale=true`. This satisfies both "never hold stale preferred over a live fallback" and "always show last value + age".

**Files:**
- Create: `app/Services/CanonicalReader.php`
- Test: `tests/Unit/CanonicalReaderTest.php`

- [ ] **Step 1: Write the failing tests**

Append to `tests/Unit/CanonicalReaderTest.php` (add imports at top: `use App\Services\CanonicalReader; use App\Services\PrometheusService; use Illuminate\Support\Facades\Config; use PHPUnit\Framework\MockObject\MockObject;`):

```php
    private function reader(PrometheusService $prometheus): CanonicalReader
    {
        Config::set('scarlet.canonical.metrics.fuel_level', [
            'label' => 'Diesel', 'unit' => '%', 'volatile' => false,
            'trend_fn' => 'median', 'trend_window' => '10m',
            'staleness' => 3600, 'coverage_window_seconds' => 3600, 'coverage_min' => 0.5,
            'sources' => [
                ['selector' => 'scarlet_signalk_tanks_fuel_0_currentLevel', 'multiply' => 100, 'staleness' => 1800],
                ['selector' => 'scarlet_mqtt_percent{topic="tanklevel"}'],
            ],
        ]);

        return new CanonicalReader($prometheus);
    }

    public function test_uses_preferred_source_when_fresh_and_healthy(): void
    {
        /** @var PrometheusService&MockObject $p */
        $p = $this->createMock(PrometheusService::class);
        $p->method('queryWithTimestamp')->willReturnMap([
            ['scarlet_signalk_tanks_fuel_0_currentLevel', null, ['value' => 0.42, 'timestamp' => 1716000000, 'age' => 30]],
        ]);
        $p->method('coverageRatio')->willReturn(0.9);

        $result = $this->reader($p)->read('fuel_level');

        $this->assertEqualsWithDelta(42.0, $result['value'], 0.001); // 0.42 * 100
        $this->assertSame('scarlet_signalk_tanks_fuel_0_currentLevel', $result['resolved_source']);
        $this->assertFalse($result['stale']);
    }

    public function test_falls_through_to_mqtt_when_signalk_absent(): void
    {
        // The verified 2026-06-17 case: SignalK fuel empty, MQTT tanklevel has data.
        /** @var PrometheusService&MockObject $p */
        $p = $this->createMock(PrometheusService::class);
        $p->method('queryWithTimestamp')->willReturnMap([
            ['scarlet_signalk_tanks_fuel_0_currentLevel', null, null],
            ['scarlet_mqtt_percent{topic="tanklevel"}', null, ['value' => 63.0, 'timestamp' => 1716000000, 'age' => 20]],
        ]);
        $p->method('coverageRatio')->willReturn(0.9);

        $result = $this->reader($p)->read('fuel_level');

        $this->assertEqualsWithDelta(63.0, $result['value'], 0.001);
        $this->assertSame('scarlet_mqtt_percent{topic="tanklevel"}', $result['resolved_source']);
        $this->assertFalse($result['stale']);
    }

    public function test_does_not_hold_stale_preferred_over_live_fallback(): void
    {
        // SignalK present but STALE (age 9000 > 1800); MQTT fresh -> must pick MQTT.
        /** @var PrometheusService&MockObject $p */
        $p = $this->createMock(PrometheusService::class);
        $p->method('queryWithTimestamp')->willReturnMap([
            ['scarlet_signalk_tanks_fuel_0_currentLevel', null, ['value' => 0.99, 'timestamp' => 1715990000, 'age' => 9000]],
            ['scarlet_mqtt_percent{topic="tanklevel"}', null, ['value' => 63.0, 'timestamp' => 1716000000, 'age' => 20]],
        ]);
        $p->method('coverageRatio')->willReturn(0.9);

        $result = $this->reader($p)->read('fuel_level');

        $this->assertSame('scarlet_mqtt_percent{topic="tanklevel"}', $result['resolved_source']);
        $this->assertEqualsWithDelta(63.0, $result['value'], 0.001);
    }

    public function test_stale_fallback_when_no_source_fresh(): void
    {
        // All stale -> highest-priority with any data, marked stale.
        /** @var PrometheusService&MockObject $p */
        $p = $this->createMock(PrometheusService::class);
        $p->method('queryWithTimestamp')->willReturnMap([
            ['scarlet_signalk_tanks_fuel_0_currentLevel', null, ['value' => 0.50, 'timestamp' => 1715000000, 'age' => 99999]],
            ['scarlet_mqtt_percent{topic="tanklevel"}', null, ['value' => 60.0, 'timestamp' => 1715000050, 'age' => 99000]],
        ]);
        $p->method('coverageRatio')->willReturn(0.9);

        $result = $this->reader($p)->read('fuel_level');

        $this->assertTrue($result['stale']);
        $this->assertSame('scarlet_signalk_tanks_fuel_0_currentLevel', $result['resolved_source']);
        $this->assertEqualsWithDelta(50.0, $result['value'], 0.001); // 0.50 * 100
    }

    public function test_returns_null_when_no_source_has_data(): void
    {
        /** @var PrometheusService&MockObject $p */
        $p = $this->createMock(PrometheusService::class);
        $p->method('queryWithTimestamp')->willReturn(null);
        $p->method('coverageRatio')->willReturn(null);

        $this->assertNull($this->reader($p)->read('fuel_level'));
    }

    public function test_returns_null_for_unknown_key(): void
    {
        /** @var PrometheusService&MockObject $p */
        $p = $this->createMock(PrometheusService::class);

        $this->assertNull((new CanonicalReader($p))->read('does_not_exist'));
    }
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --compact tests/Unit/CanonicalReaderTest.php`
Expected: FAIL ("Class CanonicalReader not found").

- [ ] **Step 3: Implement the reader**

Create `app/Services/CanonicalReader.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services;

class CanonicalReader
{
    public function __construct(protected PrometheusService $prometheus) {}

    /**
     * @return array{value: ?float, raw: ?float, unit: string, timestamp: int, age: int, stale: bool, resolved_source: string}|null
     */
    public function read(string $key): ?array
    {
        $def = config("scarlet.canonical.metrics.{$key}");
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
     * @param  array<string, mixed>  $def
     * @param  array<string, mixed>  $source
     * @param  array{value: float, timestamp: int, age: int}  $raw
     * @return array{value: ?float, raw: ?float, unit: string, timestamp: int, age: int, stale: bool, resolved_source: string}
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
        if (isset($source['multiply'])) {
            $value *= $source['multiply'];
        }
        if (isset($source['divide'])) {
            $value /= $source['divide'];
        }
        if (isset($source['subtract'])) {
            $value -= $source['subtract'];
        }

        return $value;
    }
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test --compact tests/Unit/CanonicalReaderTest.php`
Expected: PASS (all tests).

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/CanonicalReader.php tests/Unit/CanonicalReaderTest.php
git commit -m "feat(canonical): CanonicalReader with staleness-bounded fall-through + stale fallback"
```

---

## Task 8: Volatile Trend Value (median for display, raw timestamp/age)

Verifies that for a volatile metric, `value` is the median over the window while `timestamp`/`age` come from the latest real raw sample (so a fresh-looking median over stale data is impossible).

**Files:**
- Test: `tests/Unit/CanonicalReaderTest.php`
- (No new implementation expected — Task 7 already does this; this task is the proving test. If it fails, fix `build()`.)

- [ ] **Step 1: Write the failing test**

Append to `tests/Unit/CanonicalReaderTest.php`:

```php
    public function test_volatile_value_is_median_but_age_is_from_raw_sample(): void
    {
        Config::set('scarlet.canonical.metrics.water_fresh_level', [
            'label' => 'Fresh Water', 'unit' => '%', 'volatile' => true,
            'trend_fn' => 'median', 'trend_window' => '10m',
            'staleness' => 3600, 'coverage_window_seconds' => 3600, 'coverage_min' => 0.5,
            'sources' => [
                ['selector' => 'scarlet_mqtt_percent{topic="watertank"}'],
            ],
        ]);

        /** @var PrometheusService&MockObject $p */
        $p = $this->createMock(PrometheusService::class);
        // Latest raw sample is a slosh spike (95) at age 12; median over window is the real level (61).
        $p->method('queryWithTimestamp')->willReturn(['value' => 95.0, 'timestamp' => 1716000000, 'age' => 12]);
        $p->method('coverageRatio')->willReturn(0.9);
        $p->method('aggregateOverTime')->willReturn(61.0);

        $result = (new CanonicalReader($p))->read('water_fresh_level');

        $this->assertEqualsWithDelta(61.0, $result['value'], 0.001); // smoothed
        $this->assertEqualsWithDelta(95.0, $result['raw'], 0.001);   // raw last sample
        $this->assertSame(12, $result['age']);                       // age from raw sample
        $this->assertFalse($result['stale']);
    }
```

- [ ] **Step 2: Run the test**

Run: `php artisan test --compact --filter=volatile_value_is_median tests/Unit/CanonicalReaderTest.php`
Expected: PASS (Task 7's `build()` already satisfies this). If FAIL, correct `build()` so `value` uses the median and `timestamp`/`age` come from `$raw`.

- [ ] **Step 3: Commit (test-only)**

```bash
git add tests/Unit/CanonicalReaderTest.php
git commit -m "test(canonical): prove volatile value uses median with raw-sample age"
```

---

## Task 9: Wire CanonicalReader into MetricsService (flag-gated) — fixes blank fuel

When `scarlet.canonical.enabled` is true, `getBoatMetrics()` overrides the legacy `fuel_level`/`water_level` values with `CanonicalReader` results (per `scarlet.canonical.overrides`). When the flag is false, behaviour is byte-for-byte unchanged.

**Files:**
- Modify: `app/Services/MetricsService.php` (constructor + `getBoatMetrics()`)
- Test: `tests/Feature/CanonicalFuelWaterTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/CanonicalFuelWaterTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Services\CanonicalReader;
use App\Services\MetricsService;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class CanonicalFuelWaterTest extends TestCase
{
    public function test_enabled_flag_overrides_fuel_from_canonical_reader(): void
    {
        Config::set('scarlet.canonical.enabled', true);
        Config::set('scarlet.canonical.overrides', [
            'fuel_level' => 'fuel_level',
            'water_fresh_level' => 'water_level',
        ]);

        /** @var CanonicalReader&MockObject $reader */
        $reader = $this->createMock(CanonicalReader::class);
        $reader->method('read')->willReturnMap([
            ['fuel_level', ['value' => 63.0, 'raw' => 63.0, 'unit' => '%', 'timestamp' => 1, 'age' => 20, 'stale' => false, 'resolved_source' => 'mqtt']],
            ['water_fresh_level', ['value' => 41.0, 'raw' => 41.0, 'unit' => '%', 'timestamp' => 1, 'age' => 20, 'stale' => false, 'resolved_source' => 'mqtt']],
        ]);
        $this->app->instance(CanonicalReader::class, $reader);

        $service = $this->app->make(MetricsService::class);
        $boat = $service->getBoatMetrics();

        $this->assertEqualsWithDelta(63.0, $boat['fuel_level'], 0.001);
        $this->assertEqualsWithDelta(41.0, $boat['water_level'], 0.001);
    }

    public function test_disabled_flag_does_not_call_reader(): void
    {
        Config::set('scarlet.canonical.enabled', false);

        /** @var CanonicalReader&MockObject $reader */
        $reader = $this->createMock(CanonicalReader::class);
        $reader->expects($this->never())->method('read');
        $this->app->instance(CanonicalReader::class, $reader);

        // Avoid hitting VM: the boat group resolves via the registry/Prometheus pool,
        // which returns nulls under the default test HTTP fake.
        \Illuminate\Support\Facades\Http::fake([
            '*' => \Illuminate\Support\Facades\Http::response(['status' => 'success', 'data' => ['result' => []]]),
        ]);

        $service = $this->app->make(MetricsService::class);
        $service->getBoatMetrics();
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact tests/Feature/CanonicalFuelWaterTest.php`
Expected: FAIL (reader not injected / no override applied).

- [ ] **Step 3: Implement the wiring**

In `app/Services/MetricsService.php`, add `CanonicalReader` to the constructor:

```php
    public function __construct(
        protected PrometheusService $prometheus,
        protected WeatherService $weather,
        protected MetricRegistry $registry,
        protected CanonicalReader $canonical,
    ) {}
```

Add the import near the top:

```php
use App\Services\CanonicalReader;
```

(If same-namespace, the `use` is unnecessary — `CanonicalReader` is in `App\Services`. Skip the import; reference it directly.)

Then, in `getBoatMetrics()`, immediately before `return $metrics;`, insert:

```php
        if (config('scarlet.canonical.enabled')) {
            foreach (config('scarlet.canonical.overrides', []) as $canonicalKey => $boatKey) {
                $resolved = $this->canonical->read($canonicalKey);
                if ($resolved !== null) {
                    $metrics[$boatKey] = $resolved['value'];
                }
            }
        }
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --compact tests/Feature/CanonicalFuelWaterTest.php`
Expected: PASS (2 tests).

- [ ] **Step 5: Run the related suites to confirm no regression**

Run: `php artisan test --compact tests/Feature/MetricRegistryTest.php tests/Unit/PrometheusServiceTest.php tests/Unit/CanonicalReaderTest.php tests/Feature/ApiMetricsTest.php`
Expected: PASS.

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/MetricsService.php tests/Feature/CanonicalFuelWaterTest.php
git commit -m "feat(canonical): flag-gated fuel/water override in MetricsService (fixes blank fuel)"
```

---

## Task 10: Enablement docs (.env flag)

**Files:**
- Modify: `.env.example`
- Create: `docs/data-layer/phase-1-enablement.md`

- [ ] **Step 1: Add the flag to `.env.example`**

Append to `.env.example`:

```
# Phase 1 canonical reader (fuel/water source-priority resolution). Off by default.
CANONICAL_READER_ENABLED=false
```

- [ ] **Step 2: Write the enablement doc**

Create `docs/data-layer/phase-1-enablement.md`:

```markdown
# Phase 1 enablement — canonical reader

The `CanonicalReader` resolves `fuel_level` and `water_fresh_level` from an ordered, staleness-bounded
source chain (SignalK preferred → MQTT fallback). It is OFF by default.

## Enable
1. Set `CANONICAL_READER_ENABLED=true`.
2. `php artisan config:clear` (and restart Octane workers).
3. Verify on the admin dashboard that fuel reads from MQTT `tanklevel` when SignalK is absent.

## Behaviour
- Prefers the first source that is fresh (`age <= staleness`) AND healthy (coverage ratio over the window
  ≥ `coverage_min`). Otherwise falls through; if nothing is fresh, shows the last-known value flagged stale.
- Volatile metrics (fuel/water) display a 10-minute median; `age`/`timestamp` come from the latest raw sample.
- This is the seed for the Phase 2 DB catalog; the config shape mirrors the planned schema.
```

- [ ] **Step 3: Commit**

```bash
git add .env.example docs/data-layer/phase-1-enablement.md
git commit -m "docs(data-layer): document Phase 1 canonical reader enablement flag"
```

---

## Final verification

- [ ] **Run the full affected suite**

Run: `php artisan test --compact tests/Unit/PrometheusServiceTest.php tests/Unit/CanonicalReaderTest.php tests/Feature/MetricsInventoryCommandTest.php tests/Feature/MetricsBackupCommandTest.php tests/Feature/CanonicalFuelWaterTest.php tests/Feature/MetricRegistryTest.php`
Expected: PASS (all).

- [ ] **Pint clean**

Run: `vendor/bin/pint --dirty --format agent`
Expected: no outstanding style issues.

- [ ] **Ask the user** whether to run the entire suite (`php artisan test --compact`) before considering Phase 0/1 complete.

---

## Self-review notes (coverage vs PLAN-data-layer.md Phase 0 + 1)

- Phase 0.1 inventory → Task 2. Phase 0.2 inclusion matrix → Task 2 (skeleton CSV). Phase 0.3 naming convention → Task 4. Phase 0.4 restore-verified backups → Task 3 (artifacts) + Task 4 (runbook gate; physical disposable-VM restore is operational, documented, not automated here — flagged).
- Phase 1.5 corrected mappings + freshness behind an adapter → Tasks 5–9; preferred-source health by coverage ratio → Tasks 6–7; exact selectors → Task 5.
- Deferred to later phases (NOT in this plan): DB catalog/schema/Settings UI (Phase 2/7), otel-collector transforms (Phase 3), backfill/ledger (Phase 5), consumer-wide cutover beyond fuel/water (Phase 6), prune (Phase 8), full data-layer observability (cross-cutting). The `metrics:inventory` drift heuristic matches only the first `scarlet_*` token per query — label-level drift (e.g. `topic` value changes) is a Phase 2 concern, noted here so it is not assumed covered.
```
