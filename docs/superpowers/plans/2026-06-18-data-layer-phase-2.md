# Data Layer — Phase 2 Implementation Plan (DB Canonical Catalog)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Move the canonical catalog out of `config/scarlet.php` into a DB-backed, versioned store built from **structured source descriptors** (not raw PromQL), seeded from a code baseline, served through an Octane-safe version-cached repository, with `CanonicalReader` reading the repository instead of config — plus reset-to-baseline & rollback CLIs with an audit/version trail.

**Architecture:** Three tables — `canonical_metrics`, `canonical_metric_sources` (structured descriptors: source_metric_name + label_matchers + select_fn + ordered unit_transform + source_class + per-source staleness), and `canonical_catalog_versions` (monotonic version + action + actor + full JSON snapshot for rollback). A `CanonicalCatalog` repository **compiles** structured descriptors into the exact definition shape `CanonicalReader` already consumes (so the Phase-1 resolution logic is untouched), caches the assembled catalog under a version-keyed cache entry, and bumps an external (shared-cache) version integer on every mutation so all Octane workers converge. The reader gains ordered-transform support (backward compatible with the Phase-1 scalar keys).

**Tech Stack:** Laravel 12, PHP 8.4, PHPUnit 11 (sqlite `:memory:`, `RefreshDatabase` for DB tests), Eloquent, `Illuminate\Support\Facades\Cache`. Tests mock `PrometheusService`/`CanonicalCatalog` with `createMock` where pure-unit, and use `RefreshDatabase` for repository/model/seeder/CLI tests.

**Scope (locked with Jess):** Full foundation + reader switches to DB. **OUT:** Settings-UI live editing & a web write path (Phase 7), a catalog-mutation authorization policy (no web mutation surface yet — CLIs are operator-run), consumer cutover beyond the Phase-1 fuel/water wiring (Phase 6), additive backfill (Phase 5). The feature flag `scarlet.canonical.enabled` stays OFF by default.

**Reference docs:** `PLAN-data-layer.md` (Phase 2 + the normative "Source-descriptor & stitching model"), `docs/adr/0002-canonical-metric-catalog-and-additive-backfill.md`, `CONTEXT.md` (Data Layer Terms). Phase 1 delivered `CanonicalReader`, `PrometheusService::{queryWithTimestamp,coverageRatio,aggregateOverTime}`, and `config('scarlet.canonical')` — this phase supersedes `config('scarlet.canonical.metrics')` with the DB catalog.

---

## File Structure

- `database/migrations/2026_06_18_*_create_canonical_metrics_table.php` — NEW
- `database/migrations/2026_06_18_*_create_canonical_metric_sources_table.php` — NEW
- `database/migrations/2026_06_18_*_create_canonical_catalog_versions_table.php` — NEW
- `app/Models/CanonicalMetric.php`, `CanonicalMetricSource.php`, `CanonicalCatalogVersion.php` — NEW
- `app/Support/CanonicalBaseline.php` — NEW (code-defined structured baseline)
- `app/Services/CanonicalCatalog.php` — NEW (repository: compile + cache + version + applyBaseline + rollback + snapshot)
- `app/Services/CanonicalReader.php` — MODIFY (read repository; ordered transforms)
- `app/Console/Commands/CanonicalCatalogResetCommand.php`, `CanonicalCatalogRollbackCommand.php` — NEW
- `database/seeders/CanonicalCatalogSeeder.php` — NEW; wired into `DatabaseSeeder`
- `config/scarlet.php` — MODIFY (drop `canonical.metrics`; keep `enabled`/`overrides`; add `ecoflow_serial`)
- Tests: `tests/Unit/CanonicalBaselineTest.php`, `tests/Feature/CanonicalCatalogTest.php`, `tests/Unit/CanonicalReaderTest.php` (refactor), `tests/Feature/CanonicalCatalogSeederTest.php`, `tests/Feature/CanonicalCatalogCommandsTest.php`

---

## Task 1: Migrations — three catalog tables

**Files:**
- Create: `database/migrations/2026_06_18_000001_create_canonical_metrics_table.php`
- Create: `database/migrations/2026_06_18_000002_create_canonical_metric_sources_table.php`
- Create: `database/migrations/2026_06_18_000003_create_canonical_catalog_versions_table.php`
- Test: `tests/Feature/CanonicalCatalogTest.php` (migration smoke test)

- [ ] **Step 1: Write the failing test** — Create `tests/Feature/CanonicalCatalogTest.php`:

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CanonicalCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_tables_exist_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('canonical_metrics'));
        $this->assertTrue(Schema::hasTable('canonical_metric_sources'));
        $this->assertTrue(Schema::hasTable('canonical_catalog_versions'));

        $this->assertTrue(Schema::hasColumns('canonical_metrics', [
            'key', 'label', 'group', 'storage_unit', 'display_unit', 'volatile',
            'trend_fn', 'trend_window', 'staleness_threshold_s', 'coverage_window_s',
            'coverage_min', 'enabled', 'description',
        ]));
        $this->assertTrue(Schema::hasColumns('canonical_metric_sources', [
            'canonical_metric_id', 'priority', 'source_metric_name', 'label_matchers',
            'source_class', 'source_kind', 'select_fn', 'unit_transform', 'staleness_threshold_s',
        ]));
        $this->assertTrue(Schema::hasColumns('canonical_catalog_versions', [
            'version', 'action', 'actor', 'note', 'snapshot',
        ]));
    }
}
```

- [ ] **Step 2: Run to verify it fails** — `php artisan test --compact tests/Feature/CanonicalCatalogTest.php` — expect FAIL (tables absent).

- [ ] **Step 3: Implement the migrations**

`2026_06_18_000001_create_canonical_metrics_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('canonical_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->string('group')->nullable();
            $table->string('storage_unit');
            $table->string('display_unit');
            $table->boolean('volatile')->default(false);
            $table->string('trend_fn')->default('median');
            $table->string('trend_window')->default('10m');
            $table->unsignedInteger('staleness_threshold_s');
            $table->unsignedInteger('coverage_window_s')->default(3600);
            $table->decimal('coverage_min', 3, 2)->default(0.50);
            $table->boolean('enabled')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('canonical_metrics');
    }
};
```

`2026_06_18_000002_create_canonical_metric_sources_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('canonical_metric_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('canonical_metric_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('priority');
            $table->string('source_metric_name');
            $table->json('label_matchers')->nullable();
            $table->string('source_class')->default('both'); // live|historical|both
            $table->string('source_kind')->nullable();
            $table->string('select_fn')->default('last'); // last|min|max
            $table->json('unit_transform')->nullable();    // [{op,value}]
            $table->unsignedInteger('staleness_threshold_s')->nullable();
            $table->timestamps();

            $table->unique(['canonical_metric_id', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('canonical_metric_sources');
    }
};
```

`2026_06_18_000003_create_canonical_catalog_versions_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('canonical_catalog_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('version')->unique();
            $table->string('action'); // seed|reset|rollback|edit
            $table->string('actor')->nullable();
            $table->string('note')->nullable();
            $table->json('snapshot');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('canonical_catalog_versions');
    }
};
```

- [ ] **Step 4: Run to verify it passes** — `php artisan test --compact tests/Feature/CanonicalCatalogTest.php` — expect PASS.

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations tests/Feature/CanonicalCatalogTest.php
git commit -m "feat(canonical): add canonical catalog DB schema (metrics, sources, versions)

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 2: Eloquent models

**Files:**
- Create: `app/Models/CanonicalMetric.php`, `app/Models/CanonicalMetricSource.php`, `app/Models/CanonicalCatalogVersion.php`
- Test: `tests/Feature/CanonicalCatalogTest.php` (append a model test)

- [ ] **Step 1: Write the failing test** — Append to `tests/Feature/CanonicalCatalogTest.php`:

```php
    public function test_metric_has_sources_and_casts_json_and_bool(): void
    {
        $metric = \App\Models\CanonicalMetric::create([
            'key' => 'fuel_level', 'label' => 'Diesel', 'group' => 'tank',
            'storage_unit' => 'pct', 'display_unit' => '%', 'volatile' => true,
            'trend_fn' => 'median', 'trend_window' => '10m',
            'staleness_threshold_s' => 3600, 'coverage_window_s' => 3600, 'coverage_min' => 0.5,
            'enabled' => true,
        ]);

        $metric->sources()->create([
            'priority' => 1,
            'source_metric_name' => 'scarlet_mqtt_percent',
            'label_matchers' => [['label' => 'topic', 'op' => 'equals', 'value' => 'tanklevel']],
            'source_class' => 'both',
            'select_fn' => 'last',
            'unit_transform' => [['op' => 'multiply', 'value' => 100]],
        ]);

        $fresh = \App\Models\CanonicalMetric::with('sources')->where('key', 'fuel_level')->first();

        $this->assertTrue($fresh->volatile);
        $this->assertSame(0.5, (float) $fresh->coverage_min);
        $this->assertCount(1, $fresh->sources);
        $this->assertSame('topic', $fresh->sources[0]->label_matchers[0]['label']);
        $this->assertSame('multiply', $fresh->sources[0]->unit_transform[0]['op']);
    }
```

- [ ] **Step 2: Run to verify it fails** — `php artisan test --compact --filter=metric_has_sources tests/Feature/CanonicalCatalogTest.php` — expect FAIL (class not found).

- [ ] **Step 3: Implement the models**

`app/Models/CanonicalMetric.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CanonicalMetric extends Model
{
    protected $fillable = [
        'key', 'label', 'group', 'storage_unit', 'display_unit', 'volatile',
        'trend_fn', 'trend_window', 'staleness_threshold_s', 'coverage_window_s',
        'coverage_min', 'enabled', 'description',
    ];

    protected function casts(): array
    {
        return [
            'volatile' => 'boolean',
            'enabled' => 'boolean',
            'staleness_threshold_s' => 'integer',
            'coverage_window_s' => 'integer',
            'coverage_min' => 'float',
        ];
    }

    public function sources(): HasMany
    {
        return $this->hasMany(CanonicalMetricSource::class)->orderBy('priority');
    }
}
```

`app/Models/CanonicalMetricSource.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CanonicalMetricSource extends Model
{
    protected $fillable = [
        'canonical_metric_id', 'priority', 'source_metric_name', 'label_matchers',
        'source_class', 'source_kind', 'select_fn', 'unit_transform', 'staleness_threshold_s',
    ];

    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'label_matchers' => 'array',
            'unit_transform' => 'array',
            'staleness_threshold_s' => 'integer',
        ];
    }

    public function metric(): BelongsTo
    {
        return $this->belongsTo(CanonicalMetric::class, 'canonical_metric_id');
    }
}
```

`app/Models/CanonicalCatalogVersion.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CanonicalCatalogVersion extends Model
{
    protected $fillable = ['version', 'action', 'actor', 'note', 'snapshot'];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'snapshot' => 'array',
        ];
    }
}
```

- [ ] **Step 4: Run to verify it passes** — `php artisan test --compact tests/Feature/CanonicalCatalogTest.php` — expect PASS (both tests).

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/CanonicalMetric.php app/Models/CanonicalMetricSource.php app/Models/CanonicalCatalogVersion.php tests/Feature/CanonicalCatalogTest.php
git commit -m "feat(canonical): add catalog Eloquent models with json/bool casts

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 3: `CanonicalBaseline` — code-defined structured baseline

The single source of the seed/reset baseline. Validated tank metrics + a curated EcoFlow starter set (serial from `config('scarlet.canonical.ecoflow_serial')`). EcoFlow series verified live: `scarlet_mqtt_value{topic="ecoflow/<serial>_BMSHeartBeatReport/<field>"}`.

**Files:**
- Create: `app/Support/CanonicalBaseline.php`
- Test: `tests/Unit/CanonicalBaselineTest.php`

- [ ] **Step 1: Write the failing test** — Create `tests/Unit/CanonicalBaselineTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Support\CanonicalBaseline;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class CanonicalBaselineTest extends TestCase
{
    public function test_defines_fuel_water_and_ecoflow_with_correct_source_order(): void
    {
        Config::set('scarlet.canonical.ecoflow_serial', 'TESTSERIAL');

        $defs = collect(CanonicalBaseline::definitions())->keyBy('key');

        $this->assertTrue($defs->has('fuel_level'));
        $this->assertTrue($defs->has('water_fresh_level'));
        $this->assertTrue($defs->has('ecoflow_soc'));

        $fuel = $defs['fuel_level'];
        $this->assertSame('scarlet_signalk_tanks_fuel_0_currentLevel', $fuel['sources'][0]['source_metric_name']);
        $this->assertSame('scarlet_mqtt_percent', $fuel['sources'][1]['source_metric_name']);
        $this->assertSame('tanklevel', $fuel['sources'][1]['label_matchers'][0]['value']);
        $this->assertSame('multiply', $fuel['sources'][0]['unit_transform'][0]['op']);

        // EcoFlow serial is injected from config into the topic matcher
        $soc = $defs['ecoflow_soc'];
        $this->assertSame('scarlet_mqtt_value', $soc['sources'][0]['source_metric_name']);
        $this->assertStringContainsString('ecoflow/TESTSERIAL_', $soc['sources'][0]['label_matchers'][0]['value']);
    }
}
```

- [ ] **Step 2: Run to verify it fails** — `php artisan test --compact tests/Unit/CanonicalBaselineTest.php` — expect FAIL (class not found).

- [ ] **Step 3: Implement** — Create `app/Support/CanonicalBaseline.php`:

```php
<?php

declare(strict_types=1);

namespace App\Support;

class CanonicalBaseline
{
    /**
     * The code-defined canonical catalog baseline (structured descriptors).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function definitions(): array
    {
        $serial = (string) config('scarlet.canonical.ecoflow_serial', 'P231ZE1APJ3P0446');
        $ecoTopic = fn (string $field): string => "ecoflow/{$serial}_BMSHeartBeatReport/{$field}";

        return [
            [
                'key' => 'fuel_level', 'label' => 'Diesel', 'group' => 'tank',
                'storage_unit' => 'pct', 'display_unit' => '%', 'volatile' => true,
                'trend_fn' => 'median', 'trend_window' => '10m',
                'staleness_threshold_s' => 3600, 'coverage_window_s' => 3600, 'coverage_min' => 0.5,
                'enabled' => true, 'description' => 'Diesel tank level (SignalK preferred, MQTT tanklevel fallback).',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_signalk_tanks_fuel_0_currentLevel', 'label_matchers' => [], 'source_class' => 'both', 'source_kind' => 'ratio', 'select_fn' => 'last', 'unit_transform' => [['op' => 'multiply', 'value' => 100]], 'staleness_threshold_s' => 1800],
                    ['priority' => 2, 'source_metric_name' => 'scarlet_mqtt_percent', 'label_matchers' => [['label' => 'topic', 'op' => 'equals', 'value' => 'tanklevel']], 'source_class' => 'both', 'source_kind' => 'percent', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'water_fresh_level', 'label' => 'Fresh Water', 'group' => 'tank',
                'storage_unit' => 'pct', 'display_unit' => '%', 'volatile' => true,
                'trend_fn' => 'median', 'trend_window' => '10m',
                'staleness_threshold_s' => 3600, 'coverage_window_s' => 3600, 'coverage_min' => 0.5,
                'enabled' => true, 'description' => 'Fresh water tank level (SignalK preferred, MQTT watertank fallback).',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_signalk_tanks_freshWater_0_currentLevel', 'label_matchers' => [], 'source_class' => 'both', 'source_kind' => 'ratio', 'select_fn' => 'last', 'unit_transform' => [['op' => 'multiply', 'value' => 100]], 'staleness_threshold_s' => 1800],
                    ['priority' => 2, 'source_metric_name' => 'scarlet_mqtt_percent', 'label_matchers' => [['label' => 'topic', 'op' => 'equals', 'value' => 'watertank']], 'source_class' => 'both', 'source_kind' => 'percent', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'ecoflow_soc', 'label' => 'EcoFlow SOC', 'group' => 'power',
                'storage_unit' => 'pct', 'display_unit' => '%', 'volatile' => false,
                'trend_fn' => 'median', 'trend_window' => '10m',
                'staleness_threshold_s' => 3600, 'coverage_window_s' => 3600, 'coverage_min' => 0.3,
                'enabled' => true, 'description' => 'EcoFlow Delta state of charge (BMS actSoc).',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_mqtt_value', 'label_matchers' => [['label' => 'topic', 'op' => 'equals', 'value' => $ecoTopic('actSoc')]], 'source_class' => 'both', 'source_kind' => 'percent', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'ecoflow_input_watts', 'label' => 'EcoFlow Input', 'group' => 'power',
                'storage_unit' => 'w', 'display_unit' => 'W', 'volatile' => false,
                'trend_fn' => 'median', 'trend_window' => '10m',
                'staleness_threshold_s' => 3600, 'coverage_window_s' => 3600, 'coverage_min' => 0.3,
                'enabled' => true, 'description' => 'EcoFlow Delta total input power.',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_mqtt_value', 'label_matchers' => [['label' => 'topic', 'op' => 'equals', 'value' => $ecoTopic('inputWatts')]], 'source_class' => 'both', 'source_kind' => 'watts', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'ecoflow_output_watts', 'label' => 'EcoFlow Output', 'group' => 'power',
                'storage_unit' => 'w', 'display_unit' => 'W', 'volatile' => false,
                'trend_fn' => 'median', 'trend_window' => '10m',
                'staleness_threshold_s' => 3600, 'coverage_window_s' => 3600, 'coverage_min' => 0.3,
                'enabled' => true, 'description' => 'EcoFlow Delta total output power.',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_mqtt_value', 'label_matchers' => [['label' => 'topic', 'op' => 'equals', 'value' => $ecoTopic('outputWatts')]], 'source_class' => 'both', 'source_kind' => 'watts', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],
        ];
    }
}
```

- [ ] **Step 4: Run to verify it passes** — `php artisan test --compact tests/Unit/CanonicalBaselineTest.php` — expect PASS.

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Support/CanonicalBaseline.php tests/Unit/CanonicalBaselineTest.php
git commit -m "feat(canonical): add structured CanonicalBaseline (tanks + curated EcoFlow set)

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 4: `CanonicalCatalog` repository — compile, cache, version, mutate

The heart of Phase 2. Compiles structured descriptors into the reader's definition shape; caches under a version-keyed entry; bumps an external (shared-cache) version on mutation; idempotent `applyBaseline`; `rollback`.

**Files:**
- Create: `app/Services/CanonicalCatalog.php`
- Test: `tests/Feature/CanonicalCatalogTest.php` (append repository tests)

- [ ] **Step 1: Write the failing tests** — Append to `tests/Feature/CanonicalCatalogTest.php` (add `use App\Services\CanonicalCatalog;` and `use App\Support\CanonicalBaseline;` at top):

```php
    public function test_apply_baseline_seeds_and_compiles_definition(): void
    {
        $catalog = app(CanonicalCatalog::class);
        $version = $catalog->applyBaseline(CanonicalBaseline::definitions(), 'seed', 'test');

        $this->assertSame(1, $version);

        $def = $catalog->definition('fuel_level');
        $this->assertSame('%', $def['unit']);
        $this->assertTrue($def['volatile']);
        $this->assertSame(3600, $def['staleness']);
        // structured descriptor compiled to a PromQL selector + ordered transforms
        $this->assertSame('scarlet_signalk_tanks_fuel_0_currentLevel', $def['sources'][0]['selector']);
        $this->assertSame(1800, $def['sources'][0]['staleness']);
        $this->assertSame([['op' => 'multiply', 'value' => 100]], $def['sources'][0]['transforms']);
        $this->assertSame('scarlet_mqtt_percent{topic="tanklevel"}', $def['sources'][1]['selector']);
    }

    public function test_definition_null_for_unknown_or_disabled(): void
    {
        $catalog = app(CanonicalCatalog::class);
        $catalog->applyBaseline(CanonicalBaseline::definitions(), 'seed', 'test');

        $this->assertNull($catalog->definition('does_not_exist'));
    }

    public function test_apply_baseline_is_idempotent_no_version_bump_when_unchanged(): void
    {
        $catalog = app(CanonicalCatalog::class);
        $v1 = $catalog->applyBaseline(CanonicalBaseline::definitions(), 'seed', 'test');
        $v2 = $catalog->applyBaseline(CanonicalBaseline::definitions(), 'seed', 'test');

        $this->assertSame($v1, $v2);
        $this->assertSame(1, \App\Models\CanonicalCatalogVersion::count());
    }

    public function test_mutation_bumps_version_and_busts_cache(): void
    {
        $catalog = app(CanonicalCatalog::class);
        $catalog->applyBaseline(CanonicalBaseline::definitions(), 'seed', 'test');
        $this->assertSame(3600, $catalog->definition('fuel_level')['staleness']);

        // mutate the baseline: change fuel staleness
        $defs = CanonicalBaseline::definitions();
        foreach ($defs as &$d) {
            if ($d['key'] === 'fuel_level') {
                $d['staleness_threshold_s'] = 1200;
            }
        }
        unset($d);
        $v2 = $catalog->applyBaseline($defs, 'edit', 'test');

        $this->assertSame(2, $v2);
        $this->assertSame(1200, $catalog->definition('fuel_level')['staleness']); // cache busted
    }

    public function test_rollback_restores_prior_snapshot_as_new_version(): void
    {
        $catalog = app(CanonicalCatalog::class);
        $catalog->applyBaseline(CanonicalBaseline::definitions(), 'seed', 'test'); // v1

        $defs = CanonicalBaseline::definitions();
        foreach ($defs as &$d) {
            if ($d['key'] === 'fuel_level') {
                $d['staleness_threshold_s'] = 1200;
            }
        }
        unset($d);
        $catalog->applyBaseline($defs, 'edit', 'test'); // v2 (1200)

        $v3 = $catalog->rollback(1, 'test');

        $this->assertSame(3, $v3);
        $this->assertSame(3600, $catalog->definition('fuel_level')['staleness']); // back to v1 value
    }
```

- [ ] **Step 2: Run to verify they fail** — `php artisan test --compact tests/Feature/CanonicalCatalogTest.php` — expect FAIL (class not found).

- [ ] **Step 3: Implement** — Create `app/Services/CanonicalCatalog.php`:

```php
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
     * The compiled definition for a metric key, in the shape CanonicalReader consumes.
     *
     * @return array<string, mixed>|null
     */
    public function definition(string $key): ?array
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * All enabled metrics, keyed by metric key, compiled to reader shape. Version-cached.
     *
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
     * Idempotently replace the catalog with the given structured definitions.
     * Records a version row + bumps the external version key, UNLESS unchanged.
     *
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
     * The current catalog as a normalized snapshot array (for diffing / version rows).
     *
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
            CanonicalMetric::query()->delete(); // cascade drops sources

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
     * Strip volatile/ordering noise so snapshots compare by value.
     *
     * @param  iterable<int, array<string, mixed>>  $definitions
     * @return array<int, array<string, mixed>>
     */
    private function normalizeForSnapshot(iterable $definitions): array
    {
        $list = collect($definitions)->map(function (array $def): array {
            $def['coverage_min'] = (float) $def['coverage_min'];
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
                    'staleness_threshold_s' => $s['staleness_threshold_s'] ?? null,
                ])->values()->all();

            return collect($def)->only([
                'key', 'label', 'group', 'storage_unit', 'display_unit', 'volatile', 'trend_fn',
                'trend_window', 'staleness_threshold_s', 'coverage_window_s', 'coverage_min',
                'enabled', 'description', 'sources',
            ])->all();
        })->sortBy('key')->values()->all();

        return $list;
    }
}
```

NOTE on Octane safety: the repository holds NO mutable catalog state — every read goes through `Cache` keyed by the shared `version()` integer, and mutations bump that shared key, so all workers converge. No binding needed (auto-resolved, stateless). Cache in tests is the array driver, which is per-process — fine for tests.

- [ ] **Step 4: Run to verify they pass** — `php artisan test --compact tests/Feature/CanonicalCatalogTest.php` — expect PASS (all). If the idempotency/`snapshot()` comparison is flaky on key casing/floats, ensure `normalizeForSnapshot` is applied to BOTH sides (it is).

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/CanonicalCatalog.php tests/Feature/CanonicalCatalogTest.php
git commit -m "feat(canonical): CanonicalCatalog repository (compile, version-cache, baseline, rollback)

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 5: Ordered transforms in `CanonicalReader` (backward compatible)

The repository emits `transforms` (ordered `[{op,value}]`). Extend the reader's `applyArithmetic` to apply an ordered list when present, while still supporting the Phase-1 scalar `multiply`/`divide`/`subtract` keys (existing tests must stay green).

**Files:**
- Modify: `app/Services/CanonicalReader.php`
- Test: `tests/Unit/CanonicalReaderTest.php` (append)

- [ ] **Step 1: Write the failing test** — Append to `tests/Unit/CanonicalReaderTest.php`:

```php
    public function test_applies_ordered_transforms_in_sequence(): void
    {
        Config::set('scarlet.canonical.metrics.demo', null); // unused; reader will use catalog mock below
        $reader = new \App\Services\CanonicalReader(
            $this->createMock(\App\Services\PrometheusService::class),
            $this->makeCatalogReturning('demo', [
                'label' => 'Demo', 'unit' => 'x', 'volatile' => false,
                'trend_fn' => 'median', 'trend_window' => '10m', 'staleness' => 3600,
                'coverage_window_seconds' => 3600, 'coverage_min' => 0.5,
                'sources' => [[
                    'selector' => 'scarlet_demo',
                    'transforms' => [['op' => 'subtract', 'value' => 273.15], ['op' => 'multiply', 'value' => 2]],
                ]],
            ]),
        );

        // (300 - 273.15) * 2 = 53.70
        $value = $this->invokeApplyArithmetic($reader, 300.0, ['transforms' => [['op' => 'subtract', 'value' => 273.15], ['op' => 'multiply', 'value' => 2]]]);
        $this->assertEqualsWithDelta(53.70, $value, 0.001);
    }

    public function test_legacy_scalar_transform_keys_still_work(): void
    {
        $reader = new \App\Services\CanonicalReader(
            $this->createMock(\App\Services\PrometheusService::class),
            $this->createMock(\App\Services\CanonicalCatalog::class),
        );

        $value = $this->invokeApplyArithmetic($reader, 0.42, ['multiply' => 100]);
        $this->assertEqualsWithDelta(42.0, $value, 0.001);
    }

    private function invokeApplyArithmetic(\App\Services\CanonicalReader $reader, float $value, array $source): float
    {
        $ref = new \ReflectionMethod($reader, 'applyArithmetic');
        $ref->setAccessible(true);

        return $ref->invoke($reader, $value, $source);
    }

    private function makeCatalogReturning(string $key, array $def): \App\Services\CanonicalCatalog
    {
        $catalog = $this->createMock(\App\Services\CanonicalCatalog::class);
        $catalog->method('definition')->willReturnMap([[$key, $def]]);

        return $catalog;
    }
```

NOTE: This task also introduces the reader's new 2nd constructor arg (`CanonicalCatalog`). The full reader-reads-catalog refactor is Task 6; here just make the constructor accept it and `applyArithmetic` handle ordered transforms. The existing Phase-1 tests construct `new CanonicalReader($prometheus)` with ONE arg — Task 6 updates them. To keep THIS task green in isolation, give the new 2nd constructor param a default of `null` temporarily IS NOT allowed (DI needs it); instead, Task 5 and Task 6 are committed together is messy. Simpler: in Task 5, add the `CanonicalCatalog` param WITHOUT default and update ALL existing `new CanonicalReader(...)` call sites in the test file to pass `$this->createMock(CanonicalCatalog::class)` as the 2nd arg (the Phase-1 tests still set `Config::set('scarlet.canonical.metrics...')` but the reader will read config until Task 6 — so ALSO keep config reads in `read()` for now). To avoid that tangle, **merge Task 5 and Task 6**: do the constructor change, ordered-transforms, AND the config→catalog switch together, updating all reader tests in one commit. Proceed to Task 6 which now subsumes Task 5.

- [ ] **This task is merged into Task 6.** Do not commit separately. Continue to Task 6.

---

## Task 6: `CanonicalReader` reads the catalog + ordered transforms (single commit)

Switch the reader from `config('scarlet.canonical.metrics.*')` to `CanonicalCatalog::definition()`, add the `CanonicalCatalog` constructor dependency, implement ordered-transform support (keeping legacy scalar keys), and refactor all reader tests to inject a mocked `CanonicalCatalog`.

**Files:**
- Modify: `app/Services/CanonicalReader.php`
- Modify: `tests/Unit/CanonicalReaderTest.php` (refactor existing tests + add the ordered-transform tests from Task 5)

- [ ] **Step 1: Refactor the tests (red)** — In `tests/Unit/CanonicalReaderTest.php`:
  - DELETE `test_baseline_defines_fuel_and_water_chains` (config baseline is gone — replaced by `CanonicalBaselineTest`).
  - Change the `reader()` helper to inject a mocked catalog instead of `Config::set`:

```php
    private function reader(PrometheusService $prometheus): CanonicalReader
    {
        $catalog = $this->createMock(CanonicalCatalog::class);
        $catalog->method('definition')->willReturnMap([
            ['fuel_level', [
                'label' => 'Diesel', 'unit' => '%', 'volatile' => false,
                'trend_fn' => 'median', 'trend_window' => '10m',
                'staleness' => 3600, 'coverage_window_seconds' => 3600, 'coverage_min' => 0.5,
                'sources' => [
                    ['selector' => 'scarlet_signalk_tanks_fuel_0_currentLevel', 'transforms' => [['op' => 'multiply', 'value' => 100]], 'staleness' => 1800],
                    ['selector' => 'scarlet_mqtt_percent{topic="tanklevel"}'],
                ],
            ]],
        ]);

        return new CanonicalReader($prometheus, $catalog);
    }
```
  - The volatile test (`test_volatile_value_is_median_but_age_is_from_raw_sample`) and unknown-key test must also inject a catalog mock. For volatile, return a `water_fresh_level` def via a catalog mock with `volatile => true` and the single source. For unknown-key, the catalog mock returns `null` for `definition('does_not_exist')` (the default for an unmapped `willReturnMap`/`willReturn(null)`).
  - Add `use App\Services\CanonicalCatalog;` to the imports.
  - Add the two ordered-transform tests + the `invokeApplyArithmetic` helper from Task 5 (drop the unused `makeCatalogReturning`/`Config::set` lines; use a simple catalog mock).

Run: `php artisan test --compact tests/Unit/CanonicalReaderTest.php` — expect FAIL (constructor arity / `applyArithmetic` ordered transforms not handled).

- [ ] **Step 2: Implement the reader changes**

In `app/Services/CanonicalReader.php`:
  - Constructor:
```php
    public function __construct(
        protected PrometheusService $prometheus,
        protected CanonicalCatalog $catalog,
    ) {}
```
  - In `read()`, replace `$def = config("scarlet.canonical.metrics.{$key}");` with:
```php
        $def = $this->catalog->definition($key);
```
  - Replace `applyArithmetic` with ordered-transform support + legacy fallback:
```php
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

        // Legacy scalar keys (Phase 1 compatibility).
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
```

- [ ] **Step 3: Run to verify** — `php artisan test --compact tests/Unit/CanonicalReaderTest.php` — expect PASS (all).

- [ ] **Step 4: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/CanonicalReader.php tests/Unit/CanonicalReaderTest.php
git commit -m "feat(canonical): reader reads DB catalog + ordered unit transforms

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 7: Config cleanup + seeder

Remove the Phase-1 `canonical.metrics` config (superseded by DB), keep `enabled`/`overrides`, add `ecoflow_serial`. Add a seeder that idempotently applies the baseline, wired into `DatabaseSeeder`.

**Files:**
- Modify: `config/scarlet.php`
- Create: `database/seeders/CanonicalCatalogSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/CanonicalCatalogSeederTest.php`

- [ ] **Step 1: Write the failing test** — Create `tests/Feature/CanonicalCatalogSeederTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Services\CanonicalCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CanonicalCatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_populates_catalog_idempotently(): void
    {
        $this->seed(\Database\Seeders\CanonicalCatalogSeeder::class);

        $catalog = app(CanonicalCatalog::class);
        $this->assertNotNull($catalog->definition('fuel_level'));
        $this->assertNotNull($catalog->definition('ecoflow_soc'));
        $this->assertSame(1, $catalog->version());

        // Re-seed: idempotent, no new version.
        $this->seed(\Database\Seeders\CanonicalCatalogSeeder::class);
        $this->assertSame(1, $catalog->version());
    }
}
```

- [ ] **Step 2: Run to verify it fails** — `php artisan test --compact tests/Feature/CanonicalCatalogSeederTest.php` — expect FAIL (seeder class not found).

- [ ] **Step 3: Implement**

`config/scarlet.php` — replace the entire `'canonical' => [...]` block (added in Phase 1) with:

```php
    'canonical' => [
        'enabled' => (bool) env('CANONICAL_READER_ENABLED', false),

        // EcoFlow Delta device serial — typed param substituted into canonical EcoFlow source topics.
        'ecoflow_serial' => env('CANONICAL_ECOFLOW_SERIAL', 'P231ZE1APJ3P0446'),

        // Maps a canonical key onto the legacy boat-metric key it overrides when the reader is enabled.
        'overrides' => [
            'fuel_level' => 'fuel_level',
            'water_fresh_level' => 'water_level',
        ],
    ],
```

(The `metrics` sub-array is REMOVED — the catalog now lives in the DB, seeded from `CanonicalBaseline`.)

`database/seeders/CanonicalCatalogSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Services\CanonicalCatalog;
use App\Support\CanonicalBaseline;
use Illuminate\Database\Seeder;

class CanonicalCatalogSeeder extends Seeder
{
    public function run(): void
    {
        app(CanonicalCatalog::class)->applyBaseline(
            CanonicalBaseline::definitions(),
            action: 'seed',
            actor: 'seeder',
        );
    }
}
```

In `database/seeders/DatabaseSeeder.php`, add to the end of `run()`:

```php
        $this->call(CanonicalCatalogSeeder::class);
```

- [ ] **Step 4: Run to verify it passes** — `php artisan test --compact tests/Feature/CanonicalCatalogSeederTest.php` — expect PASS.

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add config/scarlet.php database/seeders/CanonicalCatalogSeeder.php database/seeders/DatabaseSeeder.php tests/Feature/CanonicalCatalogSeederTest.php
git commit -m "feat(canonical): seed catalog from baseline; drop config.canonical.metrics

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 8: Reset & rollback CLIs (audit trail)

**Files:**
- Create: `app/Console/Commands/CanonicalCatalogResetCommand.php`, `app/Console/Commands/CanonicalCatalogRollbackCommand.php`
- Test: `tests/Feature/CanonicalCatalogCommandsTest.php`

- [ ] **Step 1: Write the failing test** — Create `tests/Feature/CanonicalCatalogCommandsTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\CanonicalMetric;
use App\Services\CanonicalCatalog;
use App\Support\CanonicalBaseline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CanonicalCatalogCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_reapplies_baseline(): void
    {
        $catalog = app(CanonicalCatalog::class);
        $catalog->applyBaseline(CanonicalBaseline::definitions(), 'seed', 'test');

        // drift: delete a metric, then reset should restore it
        CanonicalMetric::where('key', 'ecoflow_soc')->delete();
        $this->assertNull($catalog->definition('ecoflow_soc'));

        $this->artisan('metrics:catalog:reset --force')->assertSuccessful();

        $this->assertNotNull(app(CanonicalCatalog::class)->definition('ecoflow_soc'));
    }

    public function test_rollback_restores_prior_version(): void
    {
        $catalog = app(CanonicalCatalog::class);
        $catalog->applyBaseline(CanonicalBaseline::definitions(), 'seed', 'test'); // v1

        $defs = CanonicalBaseline::definitions();
        foreach ($defs as &$d) {
            if ($d['key'] === 'fuel_level') {
                $d['staleness_threshold_s'] = 1200;
            }
        }
        unset($d);
        $catalog->applyBaseline($defs, 'edit', 'test'); // v2

        $this->artisan('metrics:catalog:rollback 1 --force')->assertSuccessful();

        $this->assertSame(3600, app(CanonicalCatalog::class)->definition('fuel_level')['staleness']);
    }
}
```

- [ ] **Step 2: Run to verify it fails** — `php artisan test --compact tests/Feature/CanonicalCatalogCommandsTest.php` — expect FAIL (commands not found).

- [ ] **Step 3: Implement**

`app/Console/Commands/CanonicalCatalogResetCommand.php`:

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\CanonicalCatalog;
use App\Support\CanonicalBaseline;
use Illuminate\Console\Command;

class CanonicalCatalogResetCommand extends Command
{
    protected $signature = 'metrics:catalog:reset {--force : Skip confirmation}';

    protected $description = 'Reset the canonical catalog to the code-defined baseline';

    public function handle(CanonicalCatalog $catalog): int
    {
        if (! $this->option('force') && ! $this->confirm('Reset the canonical catalog to baseline? Current edits will be replaced.')) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        $version = $catalog->applyBaseline(
            CanonicalBaseline::definitions(),
            action: 'reset',
            actor: 'cli',
        );

        $this->info("Catalog reset to baseline. Active version: {$version}.");

        return self::SUCCESS;
    }
}
```

`app/Console/Commands/CanonicalCatalogRollbackCommand.php`:

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\CanonicalCatalog;
use Illuminate\Console\Command;

class CanonicalCatalogRollbackCommand extends Command
{
    protected $signature = 'metrics:catalog:rollback {version : Target version to restore} {--force : Skip confirmation}';

    protected $description = 'Roll the canonical catalog back to a prior version';

    public function handle(CanonicalCatalog $catalog): int
    {
        $target = (int) $this->argument('version');

        if (! $this->option('force') && ! $this->confirm("Roll the canonical catalog back to v{$target}?")) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        $version = $catalog->rollback($target, actor: 'cli');

        $this->info("Rolled back to v{$target}. New active version: {$version}.");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Run to verify it passes** — `php artisan test --compact tests/Feature/CanonicalCatalogCommandsTest.php` — expect PASS (2 tests).

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Console/Commands/CanonicalCatalogResetCommand.php app/Console/Commands/CanonicalCatalogRollbackCommand.php tests/Feature/CanonicalCatalogCommandsTest.php
git commit -m "feat(canonical): add catalog reset + rollback CLIs with version audit

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 9: Docs update

**Files:**
- Modify: `docs/data-layer/phase-1-enablement.md` (note the catalog now lives in DB)
- Create: `docs/data-layer/phase-2-catalog.md`
- Modify: `.env.example` (add `CANONICAL_ECOFLOW_SERIAL`)

- [ ] **Step 1: Append to `.env.example`**:

```
# EcoFlow Delta serial for canonical EcoFlow metric topics.
CANONICAL_ECOFLOW_SERIAL=P231ZE1APJ3P0446
```

- [ ] **Step 2: Create `docs/data-layer/phase-2-catalog.md`**:

```markdown
# Phase 2 — DB canonical catalog

The canonical catalog now lives in the database (`canonical_metrics`, `canonical_metric_sources`),
seeded from the code baseline in `App\Support\CanonicalBaseline`. `CanonicalReader` reads it via the
`CanonicalCatalog` repository (structured descriptors compiled to PromQL selectors + ordered transforms).

## Operate
- Seed/refresh from baseline: `php artisan db:seed --class=Database\\Seeders\\CanonicalCatalogSeeder` (idempotent).
- Reset to baseline (drops live edits): `php artisan metrics:catalog:reset` (`--force` to skip prompt).
- Roll back: `php artisan metrics:catalog:rollback {version}`.
- Every mutation writes a `canonical_catalog_versions` row (monotonic version + action + actor + full snapshot)
  and bumps the shared cache version key, so all Octane workers converge on the new catalog.

## Notes
- Live editing via Settings UI + authorization is Phase 7. CLIs are operator-run for now.
- The reader still only takes effect when `CANONICAL_READER_ENABLED=true` (default off).
- EcoFlow device serial is `CANONICAL_ECOFLOW_SERIAL` (typed param substituted into EcoFlow source topics).
```

- [ ] **Step 3: Update `docs/data-layer/phase-1-enablement.md`** — append a line under "Behaviour":

```markdown
- As of Phase 2, the canonical definitions live in the DB catalog (seeded from `CanonicalBaseline`), not
  `config/scarlet.php`. See `docs/data-layer/phase-2-catalog.md`.
```

- [ ] **Step 4: Commit**

```bash
git add .env.example docs/data-layer/phase-2-catalog.md docs/data-layer/phase-1-enablement.md
git commit -m "docs(data-layer): document Phase 2 DB catalog + EcoFlow serial env

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Final verification

- [ ] **Run the full suite** — `php artisan test --compact` — expect all PASS. Watch specifically for:
  - Any test that constructs `new CanonicalReader(...)` with the old arity (must pass the catalog mock).
  - `CanonicalFuelWaterTest` (Phase 1): it mocks `CanonicalReader` directly and instances it in the container, so it is unaffected by the reader's new constructor dependency — confirm it still passes.
  - `SunTimesTest`, `MetricRegistryTest`, `ApiMetricsTest` — unaffected, confirm green.
- [ ] **Pint clean** — `vendor/bin/pint --dirty --format agent`.
- [ ] Confirm the feature flag remains OFF by default (`config('scarlet.canonical.enabled')` false) so production is unchanged.

## Self-review notes (coverage vs PLAN-data-layer.md Phase 2)

- Schema + constraints (unique key, unique `(metric_id, priority)`) → Task 1; app-level "≥1 enabled source" and richer validation arrive with the Settings write path (Phase 7).
- Structured descriptors + compile-to-PromQL (no stored free-form PromQL) → Tasks 3–4.
- Octane-safe version-cached repository + external version key → Task 4.
- Versioned baselines + reset + rollback + audit snapshot rows → Tasks 4, 8.
- Reader reads catalog; ordered transforms → Task 6.
- Curated EcoFlow set with serial as typed param → Task 3 (verified live selectors).
- DEFERRED (later phases, intentionally not here): Settings UI + live edit + authz policy (Phase 7), `display_unit` compatibility validation on edit (Phase 7), per-source `source_class` freshness validation on edit (Phase 7), consumer cutover beyond fuel/water (Phase 6), backfill/ledger (Phase 5). `source_class`/`source_kind`/`select_fn` columns are persisted now but only consumed by later phases — noted so they are not assumed wired.
- Known limitation: EcoFlow `scarlet_mqtt_value{topic=...}` can return 2 series (dup path); the reader's instant read takes the first (values identical). True single-series enforcement is Phase 7 validation.
```
