# Audience Dashboards Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship the four role dashboards (Main, Skipper, Ops, Tech) as hand-coded Inertia/Vue pages under `/admin`, fed by a uniform canonical read path, with an admin source-mapping UI over the existing canonical catalog, the full legacy registry migrated into that catalog, and scheduled OpenMeteo→VictoriaMetrics weather push.

**Architecture:** The canonical data layer already exists (DB tables `canonical_metrics` / `canonical_metric_sources` / `canonical_catalog_versions`, `CanonicalReader`, `CanonicalCatalog`, `CanonicalBaseline`, `MetricsUpdated` broadcast, CLI reset/rollback). We (1) build an admin UI over it with test-query validation + version history/rollback; (2) expand the baseline to cover every data-source the dashboards need and migrate all ~65 `config/scarlet.php` registry entries into it, then point reads at the canonical path; (3) schedule the existing OpenMeteo fetch to push current + marine forecast into VM every 15 min; (4) extend `Sparkline.vue` into a reusable `<TrendChart>` with the bespoke variants; (5–8) build each dashboard page from its committed mockup. Dashboards read via `CanonicalReader` (envelope `{value, raw, unit, timestamp, age, stale, resolved_source}`) and live-update over the existing Echo `metrics` channel.

**Tech Stack:** Laravel 12 (PHP 8.4, Octane), Inertia v3 + Vue 3 (`<script setup>`), Tailwind v4, Reverb/Echo, VictoriaMetrics (PromQL), PHPUnit 11, Pint. No new runtime dependencies.

## Global Constraints

- **No `confirm()`** — all confirmations use styled HTML modals (`<Teleport>` pattern from `Settings.vue`). (`feedback_no_browser_confirm`)
- **No raw PromQL in dashboard controllers** — dashboards read only through `CanonicalReader` / `MetricsService`. (ADR 0005)
- **Custom SVG only** for these dashboards' charts — no chart library. (ADR 0004)
- **Bipolar power rule** — every House/EcoFlow power trend: green ≥ 0 (charging), scarlet < 0 (discharging), split at the zero line.
- **All four dashboards under `/admin`** behind `auth` middleware; single user role (no role checks).
- **TDD** — write the failing test first, watch it fail, implement minimally, watch it pass, commit. Run `vendor/bin/pint --dirty --format agent` before each commit that touches PHP.
- **Tests:** PHPUnit feature tests with `RefreshDatabase`; mock VM via `Http::fake()` — never hit live VM in tests. No Vitest in repo; Vue is not unit-tested — cover Vue logic by extracting pure functions into testable JS modules where it matters (e.g. the bipolar path builder) and unit-test those with a tiny Node assertion script run via `node`, plus PHPUnit coverage of the controllers/props.
- **Pint** must pass on all touched PHP. **`search-docs`** before non-trivial Laravel/Inertia work.
- **Canonical flag:** `config('scarlet.canonical.enabled')` must end this plan defaulting to `true` (Phase 2), since reads move onto the canonical path.

---

## Phase 1 — Admin source-mapping UI (over existing catalog tables)

Builds the CRUD UI Jess asked for, over the existing `canonical_metrics` / `canonical_metric_sources` / `canonical_catalog_versions` tables. No new migrations for these. Adds: a public selector-compile + test-query helper, a versioned save path, a controller, routes, a nav item, and the Vue page.

### Task 1.1: Public single-source selector compilation on `CanonicalCatalog`

**Files:**
- Modify: `app/Services/CanonicalCatalog.php`
- Test: `tests/Feature/Canonical/SelectorCompileTest.php`

**Interfaces:**
- Produces: `CanonicalCatalog::compileSelector(array $source): string` — builds the PromQL selector string from a source row shape `{source_metric_name, label_matchers?: [{label, op, value}], unit_transform?}`. `op` ∈ `equals|absent`. Returns e.g. `scarlet_mqtt_percent{topic="tanklevel"}`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Canonical;

use App\Services\CanonicalCatalog;
use Tests\TestCase;

class SelectorCompileTest extends TestCase
{
    public function test_compiles_selector_with_equals_and_absent_matchers(): void
    {
        $catalog = app(CanonicalCatalog::class);

        $selector = $catalog->compileSelector([
            'source_metric_name' => 'scarlet_mqtt_percent',
            'label_matchers' => [
                ['label' => 'topic', 'op' => 'equals', 'value' => 'tanklevel'],
                ['label' => 'carrier', 'op' => 'absent', 'value' => null],
            ],
        ]);

        $this->assertSame('scarlet_mqtt_percent{topic="tanklevel",carrier=""}', $selector);
    }

    public function test_bare_metric_name_when_no_matchers(): void
    {
        $catalog = app(CanonicalCatalog::class);

        $this->assertSame(
            'scarlet_signalk_tanks_fuel_0_currentLevel',
            $catalog->compileSelector(['source_metric_name' => 'scarlet_signalk_tanks_fuel_0_currentLevel'])
        );
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=SelectorCompileTest`
Expected: FAIL — `compileSelector` does not exist (or is private). If the catalog already compiles selectors privately, extract/expose the existing logic rather than duplicating it.

- [ ] **Step 3: Implement `compileSelector`**

In `app/Services/CanonicalCatalog.php`, add a public method that reuses the existing internal selector logic used by `compileAll()` (refactor the private code into this method and call it from both places — DRY):

```php
/**
 * Build the PromQL selector for a single source row.
 *
 * @param array{source_metric_name:string,label_matchers?:array<int,array{label:string,op:string,value:mixed}>} $source
 */
public function compileSelector(array $source): string
{
    $name = $source['source_metric_name'];
    $matchers = $source['label_matchers'] ?? [];

    if (empty($matchers)) {
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
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=SelectorCompileTest`
Expected: PASS (both cases).

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/CanonicalCatalog.php tests/Feature/Canonical/SelectorCompileTest.php
git commit -m "feat(canonical): expose compileSelector for single source rows"
```

### Task 1.2: Versioned save + test-query service methods

**Files:**
- Modify: `app/Services/CanonicalCatalog.php`
- Test: `tests/Feature/Canonical/CatalogEditTest.php`

**Interfaces:**
- Consumes: `compileSelector()` (1.1), `PrometheusService::queryWithTimestamp()`.
- Produces:
  - `CanonicalCatalog::recordVersion(string $action, ?string $actor = null, ?string $note = null): int` — snapshots current DB state into `canonical_catalog_versions`, increments `version`, busts the catalog cache, returns the new version. (Reuse the existing internal commit/snapshot logic; expose as a public method that records a version from the *current* DB rows rather than from a passed-in baseline.)
  - `CanonicalCatalog::testSource(array $source): array{ok:bool, value:?float, age:?int, selector:string}` — compiles the selector and runs a live query (used by the UI "test" button).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Canonical;

use App\Models\CanonicalMetric;
use App\Services\CanonicalCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CatalogEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_record_version_snapshots_and_bumps(): void
    {
        $catalog = app(CanonicalCatalog::class);
        $before = $catalog->version();

        CanonicalMetric::create([
            'key' => 'test_metric', 'label' => 'Test', 'storage_unit' => 'pct', 'display_unit' => '%',
            'staleness_threshold_s' => 600,
        ]);

        $new = $catalog->recordVersion('edit', 'admin', 'added test_metric');

        $this->assertSame($before + 1, $new);
        $this->assertDatabaseHas('canonical_catalog_versions', ['version' => $new, 'action' => 'edit']);
    }

    public function test_test_source_runs_compiled_selector(): void
    {
        Http::fake(['*' => Http::response([
            'status' => 'success',
            'data' => ['resultType' => 'vector', 'result' => [[
                'metric' => [], 'value' => [now()->timestamp, '0.42'],
            ]]],
        ], 200)]);

        $result = app(CanonicalCatalog::class)->testSource([
            'source_metric_name' => 'scarlet_mqtt_percent',
            'label_matchers' => [['label' => 'topic', 'op' => 'equals', 'value' => 'tanklevel']],
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame('scarlet_mqtt_percent{topic="tanklevel"}', $result['selector']);
        $this->assertEqualsWithDelta(0.42, $result['value'], 0.001);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=CatalogEditTest`
Expected: FAIL — `recordVersion` / `testSource` not defined.

- [ ] **Step 3: Implement the two methods**

```php
public function recordVersion(string $action, ?string $actor = null, ?string $note = null): int
{
    $snapshot = $this->snapshot();              // existing: full DB state as array
    $version = (int) (\App\Models\CanonicalCatalogVersion::max('version') ?? 0) + 1;

    \App\Models\CanonicalCatalogVersion::create([
        'version' => $version,
        'action' => $action,
        'actor' => $actor,
        'note' => $note,
        'snapshot' => $snapshot,
    ]);

    $this->bustCache();                         // existing private cache-clear; extract if needed

    return $version;
}

/**
 * @param array{source_metric_name:string,label_matchers?:array} $source
 * @return array{ok:bool,value:?float,age:?int,selector:string}
 */
public function testSource(array $source): array
{
    $selector = $this->compileSelector($source);
    $hit = app(\App\Services\PrometheusService::class)->queryWithTimestamp($selector);

    return [
        'ok' => $hit !== null,
        'value' => $hit['value'] ?? null,
        'age' => $hit['age'] ?? null,
        'selector' => $selector,
    ];
}
```

If `bustCache()` is currently inlined in `commit()`, extract it to a private method and call it from both.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=CatalogEditTest`
Expected: PASS.

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/CanonicalCatalog.php tests/Feature/Canonical/CatalogEditTest.php
git commit -m "feat(canonical): recordVersion + testSource for admin editing"
```

### Task 1.3: `CanonicalCatalogController` — index/store/update/destroy/test/rollback

**Files:**
- Create: `app/Http/Controllers/Admin/CanonicalCatalogController.php`
- Modify: `routes/web.php` (admin group, ~line 80–140)
- Test: `tests/Feature/Admin/CanonicalCatalogControllerTest.php`

**Interfaces:**
- Consumes: `CanonicalMetric`, `CanonicalMetricSource`, `CanonicalCatalog::recordVersion/testSource/rollback`.
- Produces routes (names):
  - `GET  admin/metrics/catalog` → `admin.catalog` (Inertia page)
  - `POST admin/metrics/catalog` → `admin.catalog.store`
  - `PUT  admin/metrics/catalog/{metric}` → `admin.catalog.update`
  - `DELETE admin/metrics/catalog/{metric}` → `admin.catalog.destroy`
  - `POST admin/metrics/catalog/test` → `admin.catalog.test`
  - `POST admin/metrics/catalog/rollback` → `admin.catalog.rollback`

- [ ] **Step 1: Write the failing test** (auth gate, index props, store-with-sources, test endpoint)

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\CanonicalMetric;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CanonicalCatalogControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_auth(): void
    {
        $this->get(route('admin.catalog'))->assertRedirect(route('login'));
    }

    public function test_index_lists_metrics_with_sources_and_versions(): void
    {
        $m = CanonicalMetric::create(['key' => 'fuel_level', 'label' => 'Diesel', 'storage_unit' => 'pct', 'display_unit' => '%', 'staleness_threshold_s' => 3600]);
        $m->sources()->create(['priority' => 1, 'source_metric_name' => 'scarlet_signalk_tanks_fuel_0_currentLevel']);

        $this->actingAs(User::factory()->create())
            ->get(route('admin.catalog'))
            ->assertInertia(fn (Assert $p) => $p
                ->component('Admin/Catalog')
                ->has('metrics', 1)
                ->where('metrics.0.key', 'fuel_level')
                ->has('metrics.0.sources', 1)
                ->has('version'));
    }

    public function test_store_creates_metric_with_sources_and_bumps_version(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('admin.catalog.store'), [
                'key' => 'water_fresh_level', 'label' => 'Fresh Water', 'group' => 'tank',
                'storage_unit' => 'pct', 'display_unit' => '%', 'staleness_threshold_s' => 3600,
                'volatile' => true, 'trend_fn' => 'median', 'trend_window' => '10m',
                'coverage_window_s' => 3600, 'coverage_min' => 0.5, 'enabled' => true,
                'sources' => [[
                    'priority' => 1, 'source_metric_name' => 'scarlet_signalk_tanks_freshWater_0_currentLevel',
                    'unit_transform' => [['op' => 'multiply', 'value' => 100]],
                ]],
            ])->assertRedirect();

        $this->assertDatabaseHas('canonical_metrics', ['key' => 'water_fresh_level']);
        $this->assertDatabaseHas('canonical_metric_sources', ['source_metric_name' => 'scarlet_signalk_tanks_freshWater_0_currentLevel']);
        $this->assertDatabaseHas('canonical_catalog_versions', ['action' => 'edit']);
    }

    public function test_test_endpoint_returns_value(): void
    {
        Http::fake(['*' => Http::response([
            'status' => 'success',
            'data' => ['resultType' => 'vector', 'result' => [['metric' => [], 'value' => [now()->timestamp, '12.8']]]],
        ], 200)]);

        $this->actingAs(User::factory()->create())
            ->postJson(route('admin.catalog.test'), [
                'source_metric_name' => 'scarlet_signalk_electrical_batteries_house_voltage',
            ])->assertOk()->assertJson(['ok' => true, 'value' => 12.8]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=CanonicalCatalogControllerTest`
Expected: FAIL — route/controller missing.

- [ ] **Step 3: Implement controller + routes**

Create `app/Http/Controllers/Admin/CanonicalCatalogController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CanonicalMetric;
use App\Services\CanonicalCatalog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CanonicalCatalogController extends Controller
{
    public function index(CanonicalCatalog $catalog)
    {
        return Inertia::render('Admin/Catalog', [
            'metrics' => CanonicalMetric::with(['sources' => fn ($q) => $q->orderBy('priority')])
                ->orderBy('group')->orderBy('key')->get(),
            'versions' => \App\Models\CanonicalCatalogVersion::orderByDesc('version')->limit(20)->get(['version', 'action', 'actor', 'note', 'created_at']),
            'version' => $catalog->version(),
        ]);
    }

    public function store(Request $request, CanonicalCatalog $catalog)
    {
        $data = $this->validateMetric($request);
        $metric = CanonicalMetric::create(collect($data)->except('sources')->all());
        foreach ($data['sources'] as $s) {
            $metric->sources()->create($s);
        }
        $catalog->recordVersion('edit', $request->user()->email);

        return back()->with('success', 'Metric created.');
    }

    public function update(Request $request, CanonicalMetric $metric, CanonicalCatalog $catalog)
    {
        $data = $this->validateMetric($request);
        $metric->update(collect($data)->except('sources')->all());
        $metric->sources()->delete();
        foreach ($data['sources'] as $s) {
            $metric->sources()->create($s);
        }
        $catalog->recordVersion('edit', $request->user()->email);

        return back()->with('success', 'Metric updated.');
    }

    public function destroy(Request $request, CanonicalMetric $metric, CanonicalCatalog $catalog)
    {
        $metric->delete();
        $catalog->recordVersion('edit', $request->user()->email);

        return back()->with('success', 'Metric deleted.');
    }

    public function test(Request $request, CanonicalCatalog $catalog)
    {
        $validated = $request->validate([
            'source_metric_name' => ['required', 'string'],
            'label_matchers' => ['nullable', 'array'],
        ]);

        return response()->json($catalog->testSource($validated));
    }

    public function rollback(Request $request, CanonicalCatalog $catalog)
    {
        $validated = $request->validate(['version' => ['required', 'integer', 'min:1']]);
        $catalog->rollback($validated['version'], $request->user()->email);

        return back()->with('success', "Rolled back to v{$validated['version']}.");
    }

    /** @return array<string,mixed> */
    private function validateMetric(Request $request): array
    {
        return $request->validate([
            'key' => ['required', 'string', 'regex:/^[a-z0-9_]+$/'],
            'label' => ['required', 'string'],
            'group' => ['nullable', 'string'],
            'storage_unit' => ['required', 'string'],
            'display_unit' => ['required', 'string'],
            'volatile' => ['boolean'],
            'trend_fn' => ['in:median,avg,min,max'],
            'trend_window' => ['string'],
            'staleness_threshold_s' => ['required', 'integer', 'min:1'],
            'coverage_window_s' => ['integer', 'min:1'],
            'coverage_min' => ['numeric', 'between:0,1'],
            'enabled' => ['boolean'],
            'description' => ['nullable', 'string'],
            'sources' => ['required', 'array', 'min:1'],
            'sources.*.priority' => ['required', 'integer', 'min:1'],
            'sources.*.source_metric_name' => ['required', 'string'],
            'sources.*.label_matchers' => ['nullable', 'array'],
            'sources.*.source_class' => ['nullable', 'string'],
            'sources.*.source_kind' => ['nullable', 'string'],
            'sources.*.select_fn' => ['nullable', 'string'],
            'sources.*.unit_transform' => ['nullable', 'array'],
            'sources.*.staleness_threshold_s' => ['nullable', 'integer', 'min:1'],
        ]);
    }
}
```

Add to `routes/web.php` inside the existing `Route::middleware('auth')->prefix('admin')->group(...)`:

```php
Route::get('metrics/catalog', [\App\Http\Controllers\Admin\CanonicalCatalogController::class, 'index'])->name('admin.catalog');
Route::post('metrics/catalog', [\App\Http\Controllers\Admin\CanonicalCatalogController::class, 'store'])->name('admin.catalog.store');
Route::put('metrics/catalog/{metric}', [\App\Http\Controllers\Admin\CanonicalCatalogController::class, 'update'])->name('admin.catalog.update');
Route::delete('metrics/catalog/{metric}', [\App\Http\Controllers\Admin\CanonicalCatalogController::class, 'destroy'])->name('admin.catalog.destroy');
Route::post('metrics/catalog/test', [\App\Http\Controllers\Admin\CanonicalCatalogController::class, 'test'])->name('admin.catalog.test');
Route::post('metrics/catalog/rollback', [\App\Http\Controllers\Admin\CanonicalCatalogController::class, 'rollback'])->name('admin.catalog.rollback');
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=CanonicalCatalogControllerTest`
Expected: PASS (4 tests).

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Admin/CanonicalCatalogController.php routes/web.php tests/Feature/Admin/CanonicalCatalogControllerTest.php
git commit -m "feat(admin): canonical catalog mapping controller + routes"
```

### Task 1.4: `Admin/Catalog.vue` page + nav item

**Files:**
- Create: `resources/js/Pages/Admin/Catalog.vue`
- Modify: `resources/js/Layouts/AdminLayout.vue` (sidebar nav + breadcrumbMap)
- Test: `tests/Feature/Admin/CanonicalCatalogControllerTest.php` (already asserts `component('Admin/Catalog')`)

**Interfaces:**
- Consumes props: `metrics` (each `{id,key,label,group,storage_unit,display_unit,volatile,trend_fn,trend_window,staleness_threshold_s,coverage_window_s,coverage_min,enabled,description,sources:[...]}`), `versions`, `version`.
- Posts to `admin.catalog.store/update/destroy/test/rollback` via `useForm` / `router`.

- [ ] **Step 1: Build the page** (no failing test needed beyond 1.3's component assertion; this is presentational)

Create `resources/js/Pages/Admin/Catalog.vue` using the existing admin page conventions (`<script setup>`, `defineProps`, `useForm`, `AdminLayout`). Required UI:
- A list of catalog metrics grouped by `group`, each row showing `key`, `label`, `display_unit`, source count, and the resolved current value via the test endpoint on demand.
- An edit drawer/modal (styled `<Teleport>`, **not** `confirm()`) to add/edit a metric and its ordered sources (priority, `source_metric_name`, label matchers, `unit_transform`, per-source staleness).
- A **Test** button per source that `router.post(route('admin.catalog.test'), source, {preserveState:true})` (or `fetch`/`useHttp`) and shows `{ok, value, age, selector}`.
- A **version history** panel listing `versions` with a **Rollback** action (styled-modal confirm → `router.post(route('admin.catalog.rollback'), {version})`).
- Delete uses a styled-modal confirm.

Follow `Settings.vue` for the modal pattern (focus trap, Escape, disabled-while-pending). Use the existing `.btn`, `.btn--secondary`, `.btn--danger` classes.

- [ ] **Step 2: Add nav item**

In `resources/js/Layouts/AdminLayout.vue` sidebar (near the `Explore` link), add:

```vue
<NavLink href="/admin/metrics/catalog" icon="layers" :active="currentPage === 'Admin/Catalog'">Data Mapping</NavLink>
```

Add `'Admin/Catalog': 'Data Mapping'` (or equivalent) to the `breadcrumbMap` in the script block.

- [ ] **Step 3: Build the frontend + run the page test**

```bash
npm run build
php artisan test --compact --filter=CanonicalCatalogControllerTest
```
Expected: PASS (component assertion satisfied).

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/Admin/Catalog.vue resources/js/Layouts/AdminLayout.vue
git commit -m "feat(admin): data-mapping catalog page + nav"
```

---

## Phase 2 — Migrate the full registry into the canonical catalog

Expand `CanonicalBaseline` from 5 metrics to every data-source the dashboards need **and** the remaining `config/scarlet.php` registry entries (~65 total), each mapped to its existing VM series. Then move reads onto the canonical path and flip `canonical.enabled` to default `true`.

### Task 2.1: Author the full baseline definitions

**Files:**
- Modify: `app/Support/CanonicalBaseline.php`
- Reference (read-only): `config/scarlet.php` (registry, lines ~117+), `reference_signalk_unmapped_catalog` (memory) for nav/steering series names, the VM names captured for SRT/ESP32 in the design session.
- Test: `tests/Feature/Canonical/BaselineCoverageTest.php`

**Interfaces:**
- Produces: `CanonicalBaseline::definitions()` returns one entry per canonical key covering the groups below. Each entry mirrors the existing 5-metric shape (`key,label,group,storage_unit,display_unit,volatile,trend_fn,trend_window,staleness_threshold_s,coverage_window_s,coverage_min,enabled,description,sources[]`). Translate each legacy `config/scarlet.php` entry: `query` → a source's `source_metric_name` (+ `label_matchers` if the legacy query had a `{label="..."}`); `multiply`/`subtract` → `unit_transform` ops; `computed` entries become a documented derived key (keep the existing computation in `MetricsService`, but register the inputs).

**Canonical keys to define (grouped):**
- **sailing/nav:** `speed_sog, speed_stw, vmg, heading_true, heading_magnetic, cog, magnetic_variation, depth_below_surface, depth_below_transducer, rate_of_turn, heel, pitch, trip_log` plus surfaced SignalK nav: `xte, bearing_to_wp_true, track_bearing_true, wp_distance, wp_ttg, current_set_true, current_drift, rudder_angle, autopilot_state` (names from `reference_signalk_unmapped_catalog`).
- **wind:** `wind_speed_apparent, wind_angle_apparent, wind_speed_true, wind_direction_true`.
- **power:** `house_battery_soc, house_battery_voltage, house_battery_current, house_battery_power, house_battery_time_remaining, engine_battery_voltage, ecoflow_soc, ecoflow_input_watts, ecoflow_output_watts, ecoflow_remain_time, ecoflow_voltage` (extend the existing ecoflow set per `reference_signalk_unmapped_catalog`).
- **tanks:** `fuel_level, water_fresh_level` (already seeded — keep).
- **cabin/env:** `cabin_temp_forepeak, cabin_temp_quarterberth, cabin_temp_main, cabin_humidity_forepeak, cabin_humidity_quarterberth, cabin_humidity_main, cabin_pressure_forepeak, water_temp`.
- **weather/marine:** `wx_air_temp, wx_condition_code, wx_wind_speed, wx_wind_gust, wx_wind_dir, wx_pressure, sea_wave_height, sea_wave_period, sea_wave_direction` → mapped to the `scarlet_weather_*` series the weather push writes (Phase 3).
- **tracker (ESP32):** `tracker_battery_voltage, tracker_cpu, tracker_free_heap, tracker_temp, tracker_humidity, tracker_lte_connected, tracker_lte_rssi, tracker_lte_quality, tracker_lte_rat, tracker_wifi_connected, tracker_wifi_rssi, tracker_mode, tracker_uptime, tracker_usb_powered` → `scarlet_system_*` (use `label_matchers` to pin `job="boat-tracker"` and dedupe the otel-collector duplicate).
- **streaming (SRT):** `srt_up, srt_pub_connected, srt_pub_bitrate, srt_pub_rtt, srt_pub_latency, srt_pub_dropped, srt_pub_network, srt_con_bitrate, srt_con_rtt, srt_con_latency, srt_con_dropped` → `scarlet_srt_*`.

Set `volatile`/`trend_fn` per metric (tanks: median/10m as today; instantaneous instrument values: non-volatile, `last`). Set realistic `staleness_threshold_s` per domain (sailing ~120s underway, tanks ~3600s, weather ~1800s, SRT ~60s, tracker ~120s).

- [ ] **Step 1: Write the failing coverage test**

```php
<?php

namespace Tests\Feature\Canonical;

use App\Services\CanonicalCatalog;
use App\Support\CanonicalBaseline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BaselineCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_baseline_covers_every_dashboard_key(): void
    {
        $defs = collect(CanonicalBaseline::definitions())->keyBy('key');

        $required = [
            'speed_sog', 'speed_stw', 'vmg', 'heading_true', 'cog', 'depth_below_surface',
            'xte', 'wp_ttg', 'current_set_true', 'rudder_angle', 'autopilot_state',
            'wind_speed_apparent', 'wind_speed_true',
            'house_battery_soc', 'house_battery_power', 'engine_battery_voltage',
            'ecoflow_soc', 'ecoflow_input_watts', 'ecoflow_output_watts',
            'fuel_level', 'water_fresh_level',
            'cabin_temp_forepeak', 'cabin_humidity_main', 'water_temp',
            'wx_air_temp', 'sea_wave_height', 'sea_wave_period',
            'tracker_battery_voltage', 'tracker_lte_rssi', 'tracker_mode', 'tracker_uptime',
            'srt_up', 'srt_pub_bitrate', 'srt_pub_dropped', 'srt_con_rtt',
        ];

        foreach ($required as $key) {
            $this->assertTrue($defs->has($key), "Baseline missing canonical key: {$key}");
            $this->assertNotEmpty($defs[$key]['sources'], "Key {$key} has no sources");
        }
    }

    public function test_baseline_applies_cleanly(): void
    {
        $version = app(CanonicalCatalog::class)->applyBaseline(
            CanonicalBaseline::definitions(), 'reset', 'test'
        );
        $this->assertGreaterThan(0, $version);
        $this->assertDatabaseCount('canonical_metrics', count(CanonicalBaseline::definitions()));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=BaselineCoverageTest`
Expected: FAIL — missing keys.

- [ ] **Step 3: Author the definitions** in `app/Support/CanonicalBaseline.php`, following the existing entry shape, for every key above. Use `config/scarlet.php` as the source for already-known VM series + transforms; use `reference_signalk_unmapped_catalog` for the nav/steering/current/ecoflow names; use the SRT/system names confirmed in the design session.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=BaselineCoverageTest`
Expected: PASS.

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Support/CanonicalBaseline.php tests/Feature/Canonical/BaselineCoverageTest.php
git commit -m "feat(canonical): full baseline covering all dashboard data-sources"
```

### Task 2.2: Reseed the catalog + flip canonical on by default

**Files:**
- Modify: `config/scarlet.php` (`canonical.enabled` default → `true`; expand `canonical.overrides` if `MetricsService` still keys off it — otherwise add a `canonical.keys` list the broadcast/reader iterate)
- Modify: `app/Services/MetricsService.php` (`getCanonicalContracts()` to read **all** enabled catalog keys, not just `overrides`)
- Test: `tests/Feature/Canonical/ContractsBroadcastTest.php`

**Interfaces:**
- Produces: `MetricsService::getCanonicalContracts(): array{contracts: array<string,array>, version: int}` returning **every enabled** canonical key's envelope (via `CanonicalReader::readMany(array_keys($catalog->all()))`).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Canonical;

use App\Services\CanonicalCatalog;
use App\Services\MetricsService;
use App\Support\CanonicalBaseline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ContractsBroadcastTest extends TestCase
{
    use RefreshDatabase;

    public function test_contracts_include_all_enabled_keys(): void
    {
        config()->set('scarlet.canonical.enabled', true);
        Http::fake(['*' => Http::response([
            'status' => 'success',
            'data' => ['resultType' => 'vector', 'result' => [['metric' => [], 'value' => [now()->timestamp, '1']]]],
        ], 200)]);

        app(CanonicalCatalog::class)->applyBaseline(CanonicalBaseline::definitions(), 'reset', 'test');

        $result = app(MetricsService::class)->getCanonicalContracts();

        $this->assertArrayHasKey('srt_up', $result['contracts']);
        $this->assertArrayHasKey('house_battery_soc', $result['contracts']);
        $this->assertGreaterThan(0, $result['version']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=ContractsBroadcastTest`
Expected: FAIL — contracts only include `overrides`.

- [ ] **Step 3: Implement** — change `getCanonicalContracts()` to iterate all enabled catalog keys:

```php
private function getCanonicalContracts(): array
{
    if (! config('scarlet.canonical.enabled')) {
        return ['contracts' => [], 'version' => 0];
    }
    $keys = array_keys($this->catalog->all());     // inject CanonicalCatalog
    $contracts = array_filter($this->canonical->readMany($keys), fn ($c) => $c !== null);

    return ['contracts' => $contracts, 'version' => $this->canonical->catalogVersion()];
}
```

Inject `CanonicalCatalog $catalog` into `MetricsService`'s constructor. In `config/scarlet.php`, set `'enabled' => (bool) env('CANONICAL_READER_ENABLED', true)`.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=ContractsBroadcastTest`
Expected: PASS.

- [ ] **Step 5: Run the seeder + full canonical suite, then Pint + commit**

```bash
php artisan metrics:catalog:reset --force
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/Canonical
git add config/scarlet.php app/Services/MetricsService.php tests/Feature/Canonical/ContractsBroadcastTest.php
git commit -m "feat(canonical): broadcast all catalog contracts; enable by default"
```

---

## Phase 3 — Scheduled OpenMeteo → VictoriaMetrics weather push

`WeatherService` already fetches OpenMeteo forecast + marine (wave height/period/direction). `WeatherMetricsController` already exposes `scarlet_weather_*` for OTEL scrape, but the fetch is on-demand (5-min cache) and unscheduled. Add a scheduled command that warms the fetch every 15 min so the scrape always sees fresh data (and forecast/marine series land in VM reliably).

### Task 3.1: `weather:refresh` command + 15-min schedule

**Files:**
- Create: `app/Console/Commands/WeatherRefreshCommand.php`
- Modify: `routes/console.php` (schedule)
- Modify: `app/Http/Controllers/WeatherMetricsController.php` (ensure it exposes current **and** forecast/marine series the catalog maps: confirm `scarlet_weather_wave_height_m`, `_wave_period_s`, `_wave_direction_deg`, `_air_temperature_celsius`, `_wind_speed_kn`, `_wind_gust_kn`, `_wind_direction_deg`, `_pressure_hpa`, `_condition_code` are all emitted)
- Test: `tests/Feature/WeatherRefreshTest.php`

**Interfaces:**
- Consumes: `WeatherService::getWeather()` / `getWeatherForHome()`.
- Produces: artisan command `weather:refresh` that fetches + caches the latest weather (forcing a fresh OpenMeteo pull), logged.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WeatherRefreshTest extends TestCase
{
    public function test_refresh_command_pulls_and_caches_weather(): void
    {
        Http::fake([
            'api.open-meteo.com/*' => Http::response(['current' => ['temperature_2m' => 17.0, 'weather_code' => 3, 'wind_speed_10m' => 14, 'wind_gusts_10m' => 19, 'wind_direction_10m' => 225, 'surface_pressure' => 1014], 'hourly' => ['time' => [], 'temperature_2m' => []]], 200),
            'marine-api.open-meteo.com/*' => Http::response(['current' => ['wave_height' => 1.2, 'wave_period' => 6, 'wave_direction' => 240, 'sea_surface_temperature' => 15.0]], 200),
        ]);

        $this->artisan('weather:refresh')->assertExitCode(0);

        $this->assertNotNull(Cache::get('weather.latest'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=WeatherRefreshTest`
Expected: FAIL — command not found.

- [ ] **Step 3: Implement the command**

```php
<?php

namespace App\Console\Commands;

use App\Services\WeatherService;
use Illuminate\Console\Command;

class WeatherRefreshCommand extends Command
{
    protected $signature = 'weather:refresh';

    protected $description = 'Fetch the latest OpenMeteo current + marine weather and warm the cache for VM scrape.';

    public function handle(WeatherService $weather): int
    {
        $weather->getWeather(forceRefresh: true);
        $weather->getWeatherForHome(forceRefresh: true);
        $this->info('Weather refreshed.');

        return self::SUCCESS;
    }
}
```

If `WeatherService::getWeather()` has no `forceRefresh` param, add one that bypasses the cache read but still writes it (`Cache::put('weather.latest', ...)`). Confirm `WeatherMetricsController` emits the full marine + forecast series set the catalog maps; add any missing gauges.

Add to `routes/console.php`:

```php
Schedule::command('weather:refresh')->everyFifteenMinutes();
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=WeatherRefreshTest`
Expected: PASS.

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Console/Commands/WeatherRefreshCommand.php app/Services/WeatherService.php app/Http/Controllers/WeatherMetricsController.php routes/console.php tests/Feature/WeatherRefreshTest.php
git commit -m "feat(weather): scheduled 15-min OpenMeteo refresh for VM scrape"
```

---

## Phase 4 — `<TrendChart>` shared component

Extend the existing `Sparkline.vue` (which already has `zeroLine` + `colorPositive/colorNegative`) into a reusable `<TrendChart>` covering the design's variants. Extract the bipolar path math into a pure, unit-tested JS module.

### Task 4.1: Pure bipolar path builder (unit-tested)

**Files:**
- Create: `resources/js/lib/trendPath.js`
- Create: `resources/js/lib/trendPath.test.mjs` (Node assertion script — no Vitest in repo)

**Interfaces:**
- Produces:
  - `buildSegments(points, {width, height, zeroValue}) → { above: string[], below: string[], line: string }` — splits a polyline at the `zeroValue` baseline into above/below segment path-strings (for green/scarlet split). Handles zero-crossing interpolation.
  - `waterFillPath(points, {width, height}) → string` — inverted fill-from-top column path.

- [ ] **Step 1: Write the failing test** (`resources/js/lib/trendPath.test.mjs`)

```js
import assert from 'node:assert';
import { buildSegments } from './trendPath.js';

const pts = [{ x: 0, v: -10 }, { x: 100, v: 10 }]; // crosses zero at x=50
const { above, below } = buildSegments(pts, { width: 100, height: 40, zeroValue: 0 });

assert.ok(above.length === 1, 'one above-zero segment');
assert.ok(below.length === 1, 'one below-zero segment');
assert.ok(above[0].includes('50'), 'segment splits at the x=50 zero crossing');
console.log('trendPath OK');
```

- [ ] **Step 2: Run test to verify it fails**

Run: `node resources/js/lib/trendPath.test.mjs`
Expected: FAIL — module/function missing.

- [ ] **Step 3: Implement `trendPath.js`** with `buildSegments` (map values→y by min/max or fixed scale, find zero-crossings by linear interpolation, emit separate `above`/`below` polyline strings) and `waterFillPath`.

- [ ] **Step 4: Run test to verify it passes**

Run: `node resources/js/lib/trendPath.test.mjs`
Expected: `trendPath OK`.

- [ ] **Step 5: Commit**

```bash
git add resources/js/lib/trendPath.js resources/js/lib/trendPath.test.mjs
git commit -m "feat(charts): pure bipolar/water-fill path builder + node test"
```

### Task 4.2: `TrendChart.vue`

**Files:**
- Create: `resources/js/components/Admin/TrendChart.vue`
- Reference: `resources/js/components/Admin/Sparkline.vue` (extend its conventions)

**Interfaces:**
- Props: `data` (Array of `{t, v}` or numbers), `variant` (`'line'|'area'|'water'|'bipolar'`), `color`, `colorPositive`, `colorNegative`, `height`, `width`, `zeroValue` (default 0), `timeAxis` (Boolean), `markers` (Array of `{x, magnitude}` for event bars, e.g. dropped frames), `markerColor`. Uses `trendPath.js`.
- Renders SVG; honours `prefers-reduced-motion`; renders an aria summary (min/max/last).

- [ ] **Step 1: Build the component** consuming `trendPath.buildSegments` for `variant="bipolar"` (green ≥ `zeroValue`, scarlet below), `waterFillPath` for `variant="water"`, plain polyline for line/area, and overlaying `markers` as vertical bars (height ∝ `magnitude`) for the Tech bitrate trend.

- [ ] **Step 2: Verify build**

Run: `npm run build`
Expected: builds clean.

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/Admin/TrendChart.vue
git commit -m "feat(charts): TrendChart with line/area/water/bipolar + event markers"
```

---

## Phase 5 — Tech dashboard (fully unblocked first)

Source of truth for markup: committed mockup `docs/superpowers/mockups/audience-dashboards/tech-v3.html`. Absorbs the existing Broadcast monitor + Settings force-reload controls.

### Task 5.1: `TechDashboardController` + route + nav

**Files:**
- Create: `app/Http/Controllers/Admin/TechDashboardController.php`
- Modify: `routes/web.php`, `resources/js/Layouts/AdminLayout.vue`
- Test: `tests/Feature/Admin/TechDashboardTest.php`

**Interfaces:**
- Produces route `GET admin/dash/tech` → `admin.dash.tech`; Inertia `Admin/Dash/Tech` with props: `contracts` (from `MetricsService::getCanonicalContracts()['contracts']` — the SRT + tracker + power keys), `bitrateHistory` + `droppedHistory` (`MetricRegistry::fetchRange` equivalent via the canonical/Prometheus range read for `srt_pub_bitrate` and `increase(srt_pub_dropped)`), `housePowerHistory`, `ecoflowPowerHistory`, `pullEnabled` (`BoatSetting::getValue('srt_pull_enabled')`), `reverb` config for Echo.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TechDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_auth(): void
    {
        $this->get(route('admin.dash.tech'))->assertRedirect(route('login'));
    }

    public function test_renders_with_contracts(): void
    {
        config()->set('scarlet.canonical.enabled', true);
        Http::fake(['*' => Http::response(['status' => 'success', 'data' => ['resultType' => 'vector', 'result' => [['metric' => [], 'value' => [now()->timestamp, '1']]]]], 200)]);

        $this->actingAs(User::factory()->create())
            ->get(route('admin.dash.tech'))
            ->assertInertia(fn (Assert $p) => $p->component('Admin/Dash/Tech')->has('contracts')->has('pullEnabled'));
    }
}
```

- [ ] **Step 2: Run → fail** (`php artisan test --compact --filter=TechDashboardTest`).
- [ ] **Step 3: Implement** controller (read-only — **no raw PromQL**, use `MetricsService`/`CanonicalReader` + range reads), route in the admin group, nav link `Admin/Dash/Tech`.
- [ ] **Step 4: Run → pass.**
- [ ] **Step 5: Pint + commit** (`feat(dash): tech dashboard controller + route`).

### Task 5.2: Control actions reuse (Stop/Start stream, Refresh overlay)

**Files:**
- Reference: existing `StreamMonitorController@updatePull` (`POST admin.broadcast.pull`), `SettingsController@forceReload` (`POST admin.settings.force-reload`).
- Test: `tests/Feature/Admin/TechControlsTest.php`

The endpoints already exist and are tested (`BroadcastPullTest`). This task only verifies the Tech page wires to them with styled-modal confirms.

- [ ] **Step 1: Write a failing test** asserting the two routes exist + are auth-gated and that the Tech page receives `pullEnabled` (already in 5.1). Add a test that posting `admin.broadcast.pull {enabled:false}` from an authed user calls `MediaMtxService` (mirror `BroadcastPullTest`, asserting the stop path).
- [ ] **Step 2: Run → fail** if any wiring missing.
- [ ] **Step 3: Confirm/implement** route names; no new server code expected.
- [ ] **Step 4: Run → pass.**
- [ ] **Step 5: Commit** (`test(dash): tech control-action wiring`).

### Task 5.3: `Admin/Dash/Tech.vue` — port the mockup

**Files:**
- Create: `resources/js/Pages/Admin/Dash/Tech.vue`
- Create: `resources/js/components/Dash/SignalChain.vue`, `resources/js/components/Dash/TrackerPanel.vue`
- Reference (markup): `docs/superpowers/mockups/audience-dashboards/tech-v3.html`

- [ ] **Step 1: Build the page** porting `tech-v3.html` region-by-region into Vue, binding live data from `contracts` (e.g. `contracts.srt_pub_bitrate.value`, `.stale`, `.age`) and the history props:
  - **Hero** → `SignalChain.vue`: nodes + animated flow links + the two control buttons (open styled-modal confirms → `router.post(route('admin.broadcast.pull'), {enabled})` and `route('admin.settings.force-reload')`). Hero foot = `<TrendChart variant="line" :markers="droppedMarkers">` for bitrate + dropped-frame bars.
  - **Tracker** → `TrackerPanel.vue`: mode pill, battery, LTE/WiFi signal bars (computed from `tracker_lte_rssi`/`quality`), CPU/heap/humidity strip.
  - **Power** → two `<TrendChart variant="bipolar">` (House, EcoFlow).
  - Wire Echo live updates via `useScarletMetrics` (`canonical` ref) so values patch from the `metrics` channel.
- [ ] **Step 2: Build** (`npm run build`) and run `php artisan test --compact --filter=TechDashboardTest` → PASS.
- [ ] **Step 3: Commit** (`feat(dash): Tech dashboard page (signal chain + tracker + power)`).

---

## Phase 6 — Ops dashboard

Markup source: `docs/superpowers/mockups/audience-dashboards/ops-v8.html`.

### Task 6.1: `OpsDashboardController` + route + nav + test

Mirror Task 5.1. Props: `contracts` (power, tanks, cabin, weather/marine, sailing/nav subset), history props for House/EcoFlow power (`bipolar`) + Fuel/Water trends, cabin gauges data, `gpsTrack` for the position-band map, `reverb`. Test mirrors `TechDashboardTest` asserting `component('Admin/Dash/Ops')` + `has('contracts')`. **No raw PromQL.**

- [ ] Steps: failing test → fail → implement controller/route/nav → pass → Pint → commit (`feat(dash): ops dashboard controller + route`).

### Task 6.2: `Admin/Dash/Ops.vue` — port the mockup

**Files:** Create `resources/js/Pages/Admin/Dash/Ops.vue` (+ reuse `TrendChart`, `TemperatureGauge`, a small `WeatherBand.vue` + `PositionBand.vue` with Leaflet/OpenSeaMap). Port `ops-v8.html` three regions (Endurance / Climate / Conditions & Position), binding `contracts.*`. Cabin gauges via existing `TemperatureGauge.vue`. Map = Leaflet with `/openseamap/{z}/{x}/{y}` tiles. Wire Echo.

- [ ] Steps: build page → `npm run build` → run Ops test → PASS → commit (`feat(dash): Ops dashboard page`).

---

## Phase 7 — Skipper dashboard

Markup source: `docs/superpowers/mockups/audience-dashboards/skipper-v4.html`. Depends on the nav/steering/current keys seeded in Phase 2.

### Task 7.1: `SkipperDashboardController` + route + nav + test

Mirror 5.1/6.1. Props: `contracts` (full sailing/nav/wind/current/autopilot set + power/fuel footer), route-leg geometry for the contoured chart (from the surfaced SignalK route-point keys via `contracts`), depth + pressure history (`water`/`line`), `reverb`. Test asserts `component('Admin/Dash/Skipper')`.

- [ ] Steps: failing test → fail → implement → pass → Pint → commit.

### Task 7.2: `Admin/Dash/Skipper.vue` — port the mockup

**Files:** Create `resources/js/Pages/Admin/Dash/Skipper.vue` reusing `CompassRose.vue` (hero), `TrendChart` (speed/depth/pressure), a `NavPanel.vue` + `RouteChart.vue` (Leaflet contours + route legs + XTE). Port `skipper-v4.html`. Bind `contracts.*` incl. `xte`, `rudder_angle`, `autopilot_state`, `current_set_true/drift`. Wire Echo.

- [ ] Steps: build → `npm run build` → run test → PASS → commit.

---

## Phase 8 — Main dashboard

Markup source: `docs/superpowers/mockups/audience-dashboards/main-v9-polish.html`.

### Task 8.1: `MainDashboardController` + route + nav + test

Mirror prior controllers. Props: `contracts` (position/SOG/COG/HDG, weather cluster, house/fuel/water footer), `gpsTrack` for the hero map, `bipolar` house-power history + fuel/water (6-hr median) histories, `reverb`. Test asserts `component('Admin/Dash/Main')`.

- [ ] Steps: failing test → fail → implement → pass → Pint → commit.

### Task 8.2: `Admin/Dash/Main.vue` — port the mockup

**Files:** Create `resources/js/Pages/Admin/Dash/Main.vue` reusing the hero map (Leaflet + corner clusters), `CompassRose.vue` rail, `TrendChart` (SOG/STW delta, depth water-fill, footer trends). Port `main-v9-polish.html`; responsive stack at <880px (map on top). Wire Echo.

- [ ] Steps: build → `npm run build` → run test → PASS → commit.

---

## Phase 9 — Integration & cleanup

### Task 9.1: Retire legacy registry reads where superseded

**Files:** `app/Services/MetricsService.php`, `app/Services/MetricRegistry.php`, `config/scarlet.php`.

- [ ] **Step 1:** Add a feature test asserting the public dashboard + admin pages still render with `canonical.enabled=true` and no reliance on removed registry keys.
- [ ] **Step 2:** Where `MetricsService` read physical values from `MetricRegistry`, switch to `CanonicalReader` for keys now in the catalog; keep `MetricRegistry::fetchRange` for history (range) reads until a canonical range read exists. Remove now-dead registry entries only after the test passes.
- [ ] **Step 3:** Run the **full suite**: `php artisan test --compact`. Expected: PASS.
- [ ] **Step 4:** Pint + commit (`refactor(metrics): read physical values via canonical path`).

### Task 9.2: Full-suite gate + manual smoke

- [ ] **Step 1:** `php artisan test --compact` (all green).
- [ ] **Step 2:** `npm run build` (clean).
- [ ] **Step 3:** `vendor/bin/pint --test --format agent` is **not** used; run `vendor/bin/pint --dirty --format agent` to fix, confirm clean tree.
- [ ] **Step 4:** Manual smoke via the run skill / browser: each `/admin/dash/*` renders, Echo updates patch live, Tech controls open styled modals and post. Commit any fixes.

---

## Self-Review

**Spec coverage:** Mapping UI (§2.2/2.6 spec → Phase 1) ✓; read path uniform via CanonicalReader (§2.2, ADR 0005 → Phases 2, 5–8 controllers, no raw PromQL) ✓; catalog covers all data-sources incl. infra (§4 → Phase 2) ✓; weather/marine push (§4.3 → Phase 3) ✓; `<TrendChart>` variants incl. bipolar + water-fill + dropped-frame markers (§2.3 → Phase 4) ✓; four dashboards from locked mockups (§3 → Phases 5–8) ✓; control actions + styled modals + auth (§2.6 → Task 5.2/5.3) ✓; bipolar power rule (§5 → Phase 4 + every power trend) ✓; recency/staleness display (§2.2 → envelope bound in every dash) ✓; tests (§6 → every task) ✓; legacy-registry retirement (Q-answer "migrate all ~65" → Phase 9) ✓.

**Deferred (per spec §1):** alarm/threshold tier + Night-Watch contrast audit — explicitly out of this plan.

**Placeholder scan:** Dashboard-port tasks (5.3, 6.2, 7.2, 8.2) intentionally reference the committed mockup files as the markup source of truth rather than re-pasting SVG — this is a DRY decision, not a placeholder; each task names exact components, props, bindings, routes, and the test that gates it. Backend/shared tasks carry full code.

**Type consistency:** `compileSelector`/`testSource`/`recordVersion` (Phase 1) are consumed consistently in the controller (1.3) and contracts (2.2); envelope keys (`value/raw/unit/age/stale/resolved_source`) match `CanonicalReader`; route names (`admin.catalog.*`, `admin.dash.*`, `admin.broadcast.pull`, `admin.settings.force-reload`) are used consistently across controllers, tests, and Vue pages.
