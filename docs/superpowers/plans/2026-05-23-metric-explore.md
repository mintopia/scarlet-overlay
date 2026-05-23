# Metric Explore Page Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add an interactive metric exploration page to the admin dashboard where clicking any chart opens a Grafana-like explore view with configurable time ranges, auto-refresh, tooltips, drag-to-zoom, and multi-series overlays.

**Architecture:** New `ExploreController` serves an Inertia page with initial Prometheus data. A separate JSON endpoint (`/admin/explore/series`) handles overlay and auto-refresh fetches without full page reloads. The Vue component uses uPlot for interactive charting. Metric metadata lives in a new `explore` key in `config/scarlet.php`.

**Tech Stack:** Laravel, Inertia.js/Vue 3, uPlot, Prometheus, Tailwind CSS 4

---

### Task 1: Install uPlot

**Files:**
- Modify: `package.json`

- [ ] **Step 1: Install uPlot via npm**

Run: `npm install uplot`

- [ ] **Step 2: Verify installation**

Run: `npm ls uplot`
Expected: `uplot@1.x.x` listed

- [ ] **Step 3: Commit**

```bash
git add package.json package-lock.json
git commit -m "chore: install uPlot charting library"
```

---

### Task 2: Add Metric Registry Config

**Files:**
- Modify: `config/scarlet.php:107-124`

Add an `explore` key under `metrics.mappings` containing the full metric registry with display metadata. Each entry has slug, label, unit, color, PromQL query, group, and optional `signed` flag.

- [ ] **Step 1: Write the test**

Create `tests/Feature/ExploreMetricRegistryTest.php`:

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExploreMetricRegistryTest extends TestCase
{
    public function test_explore_config_has_all_required_fields(): void
    {
        $metrics = config('scarlet.metrics.mappings.explore');

        $this->assertNotEmpty($metrics);

        foreach ($metrics as $slug => $entry) {
            $this->assertArrayHasKey('label', $entry, "Metric '{$slug}' missing label");
            $this->assertArrayHasKey('unit', $entry, "Metric '{$slug}' missing unit");
            $this->assertArrayHasKey('color', $entry, "Metric '{$slug}' missing color");
            $this->assertArrayHasKey('query', $entry, "Metric '{$slug}' missing query");
            $this->assertArrayHasKey('group', $entry, "Metric '{$slug}' missing group");
        }
    }

    public function test_explore_config_has_battery_power_as_signed(): void
    {
        $metrics = config('scarlet.metrics.mappings.explore');

        $this->assertTrue($metrics['battery_power']['signed'] ?? false);
    }

    public function test_explore_config_groups_are_valid(): void
    {
        $validGroups = ['navigation', 'wind', 'power', 'cabin', 'tanks', 'tracker'];
        $metrics = config('scarlet.metrics.mappings.explore');

        foreach ($metrics as $slug => $entry) {
            $this->assertContains($entry['group'], $validGroups, "Metric '{$slug}' has invalid group '{$entry['group']}'");
        }
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ExploreMetricRegistryTest`
Expected: FAIL — `explore` config key doesn't exist yet

- [ ] **Step 3: Add explore metric registry to config**

In `config/scarlet.php`, add the `explore` key inside `mappings` after the `history` block (after line 124, before the closing `],` of `mappings`):

```php
'explore' => [
    // Navigation
    'speed' => [
        'label' => 'Speed (SOG)',
        'unit' => 'kn',
        'color' => 'oklch(0.54 0.22 27)',
        'query' => 'scarlet_signalk_navigation_speedOverGround * 1.94384',
        'group' => 'navigation',
    ],
    'depth' => [
        'label' => 'Depth',
        'unit' => 'm',
        'color' => 'oklch(0.55 0.15 240)',
        'query' => 'scarlet_boat_depth_meters',
        'group' => 'navigation',
    ],
    'heading' => [
        'label' => 'Heading',
        'unit' => '°',
        'color' => 'oklch(0.45 0.005 40)',
        'query' => 'scarlet_boat_heading_deg',
        'group' => 'navigation',
    ],
    'cog' => [
        'label' => 'Course Over Ground',
        'unit' => '°',
        'color' => 'oklch(0.55 0.15 240)',
        'query' => 'scarlet_signalk_navigation_courseOverGroundTrue * 180 / 3.14159265359',
        'group' => 'navigation',
    ],

    // Wind
    'wind_speed_true' => [
        'label' => 'True Wind Speed',
        'unit' => 'kn',
        'color' => 'oklch(0.54 0.22 27)',
        'query' => 'scarlet_boat_wind_speed_kn',
        'group' => 'wind',
    ],
    'wind_direction_true' => [
        'label' => 'True Wind Direction',
        'unit' => '°',
        'color' => 'oklch(0.65 0.18 40)',
        'query' => 'scarlet_boat_wind_direction_deg',
        'group' => 'wind',
    ],
    'wind_speed_apparent' => [
        'label' => 'Apparent Wind Speed',
        'unit' => 'kn',
        'color' => 'oklch(0.60 0.16 330)',
        'query' => 'scarlet_signalk_environment_wind_speedApparent * 1.94384',
        'group' => 'wind',
    ],
    'wind_angle_apparent' => [
        'label' => 'Apparent Wind Angle',
        'unit' => '°',
        'color' => 'oklch(0.60 0.16 330)',
        'query' => 'scarlet_signalk_environment_wind_angleApparent * 180 / 3.14159265359',
        'group' => 'wind',
    ],

    // Power
    'battery_voltage' => [
        'label' => 'Battery Voltage',
        'unit' => 'V',
        'color' => 'oklch(0.62 0.15 155)',
        'query' => 'scarlet_signalk_electrical_batteries_0_voltage',
        'group' => 'power',
    ],
    'battery_current' => [
        'label' => 'Battery Current',
        'unit' => 'A',
        'color' => 'oklch(0.65 0.18 40)',
        'query' => 'scarlet_signalk_electrical_batteries_0_current',
        'group' => 'power',
    ],
    'battery_power' => [
        'label' => 'Battery Power',
        'unit' => 'W',
        'color' => 'oklch(0.62 0.15 155)',
        'query' => 'scarlet_signalk_electrical_batteries_0_current * scarlet_signalk_electrical_batteries_0_voltage',
        'group' => 'power',
        'signed' => true,
    ],
    'battery_soc' => [
        'label' => 'Battery SOC',
        'unit' => '%',
        'color' => 'oklch(0.62 0.15 155)',
        'query' => 'scarlet_signalk_electrical_batteries_0_capacity_stateOfCharge * 100',
        'group' => 'power',
    ],
    'engine_battery_voltage' => [
        'label' => 'Engine Battery',
        'unit' => 'V',
        'color' => 'oklch(0.65 0.18 40)',
        'query' => 'scarlet_signalk_electrical_batteries_1_voltage',
        'group' => 'power',
    ],

    // Cabin
    'temp_forepeak' => [
        'label' => 'Temp: Forepeak',
        'unit' => '°C',
        'color' => 'oklch(0.70 0.14 70)',
        'query' => 'scarlet_environment_temperature_celsius',
        'group' => 'cabin',
    ],
    'temp_quarterberth' => [
        'label' => 'Temp: Quarterberth',
        'unit' => '°C',
        'color' => 'oklch(0.60 0.16 240)',
        'query' => 'scarlet_mqtt_temperature{topic="zigbee2mqtt/Quarterberth"}',
        'group' => 'cabin',
    ],
    'temp_main_cabin' => [
        'label' => 'Temp: Main Cabin',
        'unit' => '°C',
        'color' => 'oklch(0.65 0.18 330)',
        'query' => 'scarlet_mqtt_temperature{topic="zigbee2mqtt/Main Cabin"}',
        'group' => 'cabin',
    ],
    'humidity_forepeak' => [
        'label' => 'Humidity: Forepeak',
        'unit' => '%',
        'color' => 'oklch(0.70 0.14 70)',
        'query' => 'scarlet_environment_humidity_percent',
        'group' => 'cabin',
    ],
    'humidity_quarterberth' => [
        'label' => 'Humidity: Quarterberth',
        'unit' => '%',
        'color' => 'oklch(0.60 0.16 240)',
        'query' => 'scarlet_mqtt_humidity{topic="zigbee2mqtt/Quarterberth"}',
        'group' => 'cabin',
    ],
    'humidity_main_cabin' => [
        'label' => 'Humidity: Main Cabin',
        'unit' => '%',
        'color' => 'oklch(0.65 0.18 330)',
        'query' => 'scarlet_mqtt_humidity{topic="zigbee2mqtt/Main Cabin"}',
        'group' => 'cabin',
    ],

    // Tanks
    'fuel_level' => [
        'label' => 'Diesel Level',
        'unit' => '%',
        'color' => 'oklch(0.70 0.14 70)',
        'query' => 'scarlet_signalk_tanks_diesel_currentLevel * 100',
        'group' => 'tanks',
    ],
    'water_level' => [
        'label' => 'Fresh Water Level',
        'unit' => '%',
        'color' => 'oklch(0.55 0.15 240)',
        'query' => 'scarlet_mqtt_percent{topic="watertank"}',
        'group' => 'tanks',
    ],

    // Tracker
    'lte_rssi' => [
        'label' => 'LTE Signal',
        'unit' => 'dBm',
        'color' => 'oklch(0.54 0.22 27)',
        'query' => 'scarlet_system_lte_rssi_dBm',
        'group' => 'tracker',
    ],
    'wifi_rssi' => [
        'label' => 'WiFi Signal',
        'unit' => 'dBm',
        'color' => 'oklch(0.55 0.15 240)',
        'query' => 'scarlet_system_wifi_rssi_dBm',
        'group' => 'tracker',
    ],
    'gps_satellites' => [
        'label' => 'GPS Satellites',
        'unit' => '',
        'color' => 'oklch(0.62 0.15 155)',
        'query' => 'scarlet_gps_satellites',
        'group' => 'tracker',
    ],
    'cpu_usage' => [
        'label' => 'CPU Usage',
        'unit' => '%',
        'color' => 'oklch(0.60 0.16 330)',
        'query' => 'scarlet_system_cpu_usage_percent',
        'group' => 'tracker',
    ],
],
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=ExploreMetricRegistryTest`
Expected: 3 tests, all PASS

- [ ] **Step 5: Commit**

```bash
git add config/scarlet.php tests/Feature/ExploreMetricRegistryTest.php
git commit -m "feat: add explore metric registry config with display metadata"
```

---

### Task 3: Create ExploreController with Page and Series Endpoints

**Files:**
- Create: `app/Http/Controllers/Admin/ExploreController.php`
- Modify: `routes/web.php:54-85`
- Create: `tests/Feature/ExploreControllerTest.php`

- [ ] **Step 1: Write the tests**

Create `tests/Feature/ExploreControllerTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Journey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExploreControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->owner()->create();
    }

    public function test_explore_page_requires_auth(): void
    {
        $response = $this->get('/admin/explore?metric=battery_voltage');
        $response->assertRedirect('/login');
    }

    public function test_explore_page_requires_metric_param(): void
    {
        $response = $this->actingAs($this->user)->get('/admin/explore');
        $response->assertRedirect('/admin/metrics');
    }

    public function test_explore_page_rejects_invalid_metric(): void
    {
        $response = $this->actingAs($this->user)->get('/admin/explore?metric=nonexistent');
        $response->assertRedirect('/admin/metrics');
    }

    public function test_explore_page_renders_with_valid_metric(): void
    {
        $response = $this->actingAs($this->user)->get('/admin/explore?metric=battery_voltage');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Explore')
            ->has('metric')
            ->has('metrics')
            ->has('data')
            ->has('range')
            ->has('start')
            ->has('end')
            ->has('step')
            ->has('refresh')
            ->has('passage')
        );
    }

    public function test_explore_page_accepts_range_param(): void
    {
        $response = $this->actingAs($this->user)->get('/admin/explore?metric=battery_voltage&range=1h');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('range', '1h')
        );
    }

    public function test_series_endpoint_requires_auth(): void
    {
        $response = $this->getJson('/admin/explore/series?metric=battery_voltage&start=1716400000&end=1716486400&step=300s');
        $response->assertUnauthorized();
    }

    public function test_series_endpoint_returns_json(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/admin/explore/series?metric=battery_voltage&start=1716400000&end=1716486400&step=300s');

        $response->assertOk();
        $response->assertJsonStructure([
            ['metric', 'data', 'stats' => ['current', 'min', 'max', 'avg']],
        ]);
    }

    public function test_series_endpoint_supports_batch_metrics(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/admin/explore/series?metrics=battery_voltage,battery_current&start=1716400000&end=1716486400&step=300s');

        $response->assertOk();
        $response->assertJsonCount(2);
    }

    public function test_series_endpoint_rejects_invalid_metric(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/admin/explore/series?metric=nonexistent&start=1716400000&end=1716486400&step=300s');

        $response->assertUnprocessable();
    }

    public function test_passage_data_included_when_journey_exists(): void
    {
        Journey::create([
            'from_port' => 'Lymington',
            'to_port' => 'Yarmouth',
            'status' => 'active',
            'started_at' => now()->subHours(3),
        ]);

        $response = $this->actingAs($this->user)->get('/admin/explore?metric=battery_voltage');
        $response->assertInertia(fn ($page) => $page
            ->where('passage.available', true)
            ->has('passage.start')
            ->has('passage.end')
        );
    }

    public function test_passage_unavailable_when_no_journey(): void
    {
        $response = $this->actingAs($this->user)->get('/admin/explore?metric=battery_voltage');
        $response->assertInertia(fn ($page) => $page
            ->where('passage.available', false)
        );
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=ExploreControllerTest`
Expected: FAIL — route and controller don't exist yet

- [ ] **Step 3: Create the ExploreController**

Create `app/Http/Controllers/Admin/ExploreController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Journey;
use App\Services\PrometheusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ExploreController extends Controller
{
    private const STEP_MAP = [
        '1h' => '15s',
        '6h' => '60s',
        '12h' => '120s',
        '24h' => '300s',
        '3d' => '900s',
        '7d' => '1800s',
        '30d' => '7200s',
    ];

    private const DEFAULT_REFRESH = [
        '1h' => 15, '6h' => 15, '12h' => 15, '24h' => 15,
        '3d' => 0, '7d' => 0, '30d' => 0,
    ];

    public function index(Request $request, PrometheusService $prometheus)
    {
        $slug = $request->query('metric');
        $allMetrics = config('scarlet.metrics.mappings.explore');

        if (!$slug || !isset($allMetrics[$slug])) {
            return redirect()->route('admin.metrics');
        }

        $metric = $allMetrics[$slug];
        $metric['slug'] = $slug;

        $range = $request->query('range', '24h');
        [$start, $end, $step] = $this->resolveTimeRange($request, $range);

        $data = $prometheus->queryRange($metric['query'], null, $step, $start, $end);

        $overlays = [];
        $overlayParam = $request->query('overlay', '');
        if ($overlayParam) {
            foreach (explode(',', $overlayParam) as $overlaySlugs) {
                $os = trim($overlaySlugs);
                if (isset($allMetrics[$os]) && $os !== $slug) {
                    $overlays[] = [
                        'metric' => array_merge($allMetrics[$os], ['slug' => $os]),
                        'data' => $prometheus->queryRange($allMetrics[$os]['query'], null, $step, $start, $end),
                    ];
                }
            }
        }

        $grouped = [];
        foreach ($allMetrics as $s => $m) {
            $grouped[$m['group']][$s] = $m;
        }

        $passage = $this->getPassageData();

        $refresh = (int) $request->query('refresh', self::DEFAULT_REFRESH[$range] ?? 0);

        return Inertia::render('Admin/Explore', [
            'metric' => $metric,
            'metrics' => $grouped,
            'data' => $data,
            'overlays' => $overlays,
            'range' => $range,
            'start' => $start,
            'end' => $end,
            'step' => $step,
            'refresh' => $refresh,
            'passage' => $passage,
        ]);
    }

    public function series(Request $request, PrometheusService $prometheus): JsonResponse
    {
        $allMetrics = config('scarlet.metrics.mappings.explore');
        $start = (int) $request->query('start');
        $end = (int) $request->query('end');
        $step = $request->query('step', '300s');

        $slugs = [];
        if ($request->query('metrics')) {
            $slugs = array_map('trim', explode(',', $request->query('metrics')));
        } elseif ($request->query('metric')) {
            $slugs = [trim($request->query('metric'))];
        }

        $results = [];
        foreach ($slugs as $slug) {
            if (!isset($allMetrics[$slug])) {
                return response()->json(['error' => "Unknown metric: {$slug}"], 422);
            }
            $data = $prometheus->queryRange($allMetrics[$slug]['query'], null, $step, $start, $end);
            $values = array_column($data, 'value');

            $results[] = [
                'metric' => $slug,
                'data' => $data,
                'stats' => [
                    'current' => !empty($values) ? end($values) : null,
                    'min' => !empty($values) ? min($values) : null,
                    'max' => !empty($values) ? max($values) : null,
                    'avg' => !empty($values) ? round(array_sum($values) / count($values), 2) : null,
                ],
            ];
        }

        return response()->json($results);
    }

    private function resolveTimeRange(Request $request, string $range): array
    {
        $end = (int) $request->query('end', now()->timestamp);
        $start = (int) $request->query('start', 0);

        if ($start > 0) {
            $step = $this->calculateStep($end - $start);
            return [$start, $end, $step];
        }

        if ($range === 'passage') {
            $passage = $this->getPassageData();
            if ($passage['available']) {
                $step = $this->calculateStep($passage['end'] - $passage['start']);
                return [$passage['start'], $passage['end'], $step];
            }
            $range = '24h';
        }

        $step = self::STEP_MAP[$range] ?? '300s';
        $duration = $this->rangeToDuration($range);
        $start = now()->subSeconds($duration)->timestamp;

        return [$start, $end, $step];
    }

    private function rangeToDuration(string $range): int
    {
        return match ($range) {
            '1h' => 3600,
            '6h' => 21600,
            '12h' => 43200,
            '24h' => 86400,
            '3d' => 259200,
            '7d' => 604800,
            '30d' => 2592000,
            default => 86400,
        };
    }

    private function calculateStep(int $durationSeconds): string
    {
        $step = max(15, (int) floor($durationSeconds / 300));
        return $step . 's';
    }

    private function getPassageData(): array
    {
        $journey = Journey::latest('started_at')->first();

        if (!$journey || !$journey->started_at) {
            return ['available' => false, 'start' => null, 'end' => null];
        }

        return [
            'available' => true,
            'start' => $journey->started_at->timestamp,
            'end' => $journey->ended_at?->timestamp ?? now()->timestamp,
        ];
    }
}
```

- [ ] **Step 4: Update PrometheusService::queryRange to support null duration with explicit start/end**

The current `queryRange` method requires a `$duration` string. The explore controller needs to pass explicit `start`/`end` without a duration. Modify `app/Services/PrometheusService.php:73` to make `$duration` nullable:

Change the method signature from:

```php
public function queryRange(string $promql, string $duration, string $step = '15s', ?int $start = null, ?int $end = null): array
```

To:

```php
public function queryRange(string $promql, ?string $duration, string $step = '15s', ?int $start = null, ?int $end = null): array
```

And update the body — change line 78:

```php
'start' => $start ?? now()->sub(\Carbon\CarbonInterval::fromString($duration))->timestamp,
```

To:

```php
'start' => $start ?? ($duration ? now()->sub(\Carbon\CarbonInterval::fromString($duration))->timestamp : now()->subDay()->timestamp),
```

- [ ] **Step 5: Add routes to web.php**

In `routes/web.php`, add two routes inside the `auth` + `admin` middleware group, after the metrics route (after line 62):

```php
Route::get('/explore', [App\Http\Controllers\Admin\ExploreController::class, 'index'])->name('admin.explore');
Route::get('/explore/series', [App\Http\Controllers\Admin\ExploreController::class, 'series'])->name('admin.explore.series');
```

And add the use statement at the top of the file (after line 7):

```php
use App\Http\Controllers\Admin\ExploreController;
```

Then update the route definitions to use the imported class:

```php
Route::get('/explore', [ExploreController::class, 'index'])->name('admin.explore');
Route::get('/explore/series', [ExploreController::class, 'series'])->name('admin.explore.series');
```

- [ ] **Step 6: Create a minimal Explore.vue placeholder so Inertia can render**

Create `resources/js/Pages/Admin/Explore.vue`:

```vue
<template>
    <AdminLayout>
        <Head title="Explore" />
        <div>
            <h1 class="text-[22px] font-bold">{{ metric.label }}</h1>
            <p class="text-text-dim text-[13px]">Explore placeholder</p>
        </div>
    </AdminLayout>
</template>

<script setup>
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps({
    metric: Object,
    metrics: Object,
    data: Array,
    overlays: Array,
    range: String,
    start: Number,
    end: Number,
    step: String,
    refresh: Number,
    passage: Object,
});
</script>
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `php artisan test --filter=ExploreControllerTest`
Expected: All tests PASS

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Admin/ExploreController.php app/Services/PrometheusService.php routes/web.php resources/js/Pages/Admin/Explore.vue tests/Feature/ExploreControllerTest.php
git commit -m "feat: add ExploreController with page and series endpoints"
```

---

### Task 4: Build the Explore Vue Component — Toolbar & Time Presets

**Files:**
- Modify: `resources/js/Pages/Admin/Explore.vue`

Replace the placeholder with the full toolbar layout. This task builds the page header (back link, metric dropdown, time presets, refresh selector) and wires up navigation via Inertia visits.

- [ ] **Step 1: Build the toolbar with metric dropdown and time presets**

Replace the entire content of `resources/js/Pages/Admin/Explore.vue` with:

```vue
<template>
    <AdminLayout>
        <Head :title="metric.label" />

        <!-- Page header / toolbar -->
        <div class="toolbar">
            <div class="toolbar-left">
                <Link href="/admin/metrics" class="back-link">&larr; Metrics</Link>
                <span class="toolbar-sep">/</span>
                <div class="metric-select-wrap">
                    <select
                        :value="metric.slug"
                        class="metric-select"
                        @change="switchMetric($event.target.value)"
                    >
                        <optgroup v-for="(groupMetrics, group) in metrics" :key="group" :label="groupLabel(group)">
                            <option v-for="(m, slug) in groupMetrics" :key="slug" :value="slug">
                                {{ m.label }}{{ m.unit ? ` (${m.unit})` : '' }}
                            </option>
                        </optgroup>
                    </select>
                </div>
            </div>

            <div class="toolbar-right">
                <!-- Recent presets -->
                <div class="preset-cluster">
                    <button
                        v-for="p in recentPresets"
                        :key="p"
                        class="preset-btn"
                        :class="{ 'preset-btn--active': range === p && !zoomed }"
                        @click="switchRange(p)"
                    >{{ p }}</button>
                </div>
                <!-- Extended presets -->
                <div class="preset-cluster">
                    <button
                        v-for="p in extendedPresets"
                        :key="p"
                        class="preset-btn"
                        :class="{ 'preset-btn--active': range === p && !zoomed }"
                        @click="switchRange(p)"
                    >{{ p }}</button>
                </div>
                <!-- Special presets -->
                <div class="preset-cluster">
                    <button
                        class="preset-btn preset-btn--passage"
                        :class="{ 'preset-btn--active': range === 'passage' && !zoomed, 'preset-btn--disabled': !passage.available }"
                        :disabled="!passage.available"
                        :title="passage.available ? '' : 'No journeys'"
                        @click="switchRange('passage')"
                    >&#9875; Passage</button>
                    <button
                        class="preset-btn"
                        :class="{ 'preset-btn--active': range === 'custom' || zoomed }"
                        @click="showCustomPicker = !showCustomPicker"
                    >Custom</button>
                </div>

                <span class="toolbar-sep">|</span>

                <!-- Refresh interval -->
                <select v-model.number="refreshInterval" class="refresh-select" @change="restartRefresh">
                    <option :value="0">Off</option>
                    <option :value="15">15s</option>
                    <option :value="30">30s</option>
                    <option :value="60">1m</option>
                    <option :value="300">5m</option>
                </select>

                <!-- Reset zoom button -->
                <button v-if="zoomed" class="preset-btn preset-btn--reset" @click="resetZoom">Reset zoom</button>
            </div>
        </div>

        <!-- Custom date picker (inline dropdown) -->
        <div v-if="showCustomPicker" class="custom-picker">
            <label class="custom-picker-label">
                From
                <input type="datetime-local" v-model="customStart" class="custom-picker-input" />
            </label>
            <label class="custom-picker-label">
                To
                <input type="datetime-local" v-model="customEnd" class="custom-picker-input" />
            </label>
            <button class="custom-picker-apply" @click="applyCustomRange">Apply</button>
        </div>

        <!-- Chart panel (placeholder for Task 5) -->
        <div class="panel mt-4">
            <div class="panel-head">
                <span class="panel-title">{{ metric.label }}</span>
                <span class="text-[12px] text-text-dim tabular-nums">{{ data?.length ?? 0 }} data points</span>
            </div>
            <div class="text-center text-text-dim py-20 text-[13px]">
                Chart renders in Task 5
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { ref, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    metric: Object,
    metrics: Object,
    data: Array,
    overlays: Array,
    range: String,
    start: Number,
    end: Number,
    step: String,
    refresh: Number,
    passage: Object,
});

const recentPresets = ['1h', '6h', '24h'];
const extendedPresets = ['3d', '7d', '30d'];

const zoomed = ref(false);
const showCustomPicker = ref(false);
const customStart = ref('');
const customEnd = ref('');
const refreshInterval = ref(props.refresh);
let refreshTimer = null;

const groupLabels = {
    navigation: 'Navigation',
    wind: 'Wind',
    power: 'Power',
    cabin: 'Cabin',
    tanks: 'Tanks',
    tracker: 'Tracker',
};

function groupLabel(group) {
    return groupLabels[group] || group;
}

function buildUrl(params) {
    const base = '/admin/explore';
    const query = new URLSearchParams();
    for (const [k, v] of Object.entries(params)) {
        if (v != null && v !== '') query.set(k, v);
    }
    return `${base}?${query.toString()}`;
}

function switchMetric(slug) {
    router.get(buildUrl({ metric: slug, range: props.range }));
}

function switchRange(range) {
    zoomed.value = false;
    showCustomPicker.value = false;
    router.get(buildUrl({ metric: props.metric.slug, range }));
}

function applyCustomRange() {
    if (!customStart.value || !customEnd.value) return;
    const start = Math.floor(new Date(customStart.value).getTime() / 1000);
    const end = Math.floor(new Date(customEnd.value).getTime() / 1000);
    showCustomPicker.value = false;
    router.get(buildUrl({ metric: props.metric.slug, range: 'custom', start, end }));
}

function resetZoom() {
    zoomed.value = false;
    switchRange(props.range === 'custom' ? '24h' : props.range);
}

function restartRefresh() {
    clearInterval(refreshTimer);
    if (refreshInterval.value > 0) {
        refreshTimer = setInterval(() => doRefresh(), refreshInterval.value * 1000);
    }
}

async function doRefresh() {
    if (zoomed.value) return;
    const end = Math.floor(Date.now() / 1000);
    const start = end - (props.end - props.start);
    const url = `/admin/explore/series?metric=${props.metric.slug}&start=${start}&end=${end}&step=${props.step}`;
    // Overlay refresh will be added in Task 6
    try {
        const res = await fetch(url);
        if (!res.ok) return;
        // Data update handled after uPlot integration in Task 5
    } catch {
        // Silent failure — last data stays rendered
    }
}

onMounted(() => {
    restartRefresh();
});

onUnmounted(() => {
    clearInterval(refreshTimer);
});
</script>

<style scoped>
.toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 8px;
}

.toolbar-left {
    display: flex;
    align-items: center;
    gap: 8px;
}

.toolbar-right {
    display: flex;
    align-items: center;
    gap: 4px;
    flex-wrap: wrap;
}

.toolbar-sep {
    color: var(--color-border);
}

.back-link {
    font-size: 13px;
    color: var(--color-text-secondary);
    text-decoration: none;
}

.back-link:hover {
    color: var(--color-text-primary);
}

.metric-select-wrap {
    position: relative;
}

.metric-select {
    font-weight: 600;
    font-size: 15px;
    border: none;
    background: transparent;
    color: var(--color-text-primary);
    cursor: pointer;
    padding: 2px 4px;
    -webkit-appearance: none;
    appearance: none;
}

.metric-select:focus-visible {
    outline: 2px solid var(--color-scarlet);
    outline-offset: 2px;
    border-radius: 4px;
}

.preset-cluster {
    display: flex;
    gap: 3px;
}

.preset-btn {
    padding: 4px 8px;
    background: var(--color-bg);
    border: 1px solid var(--color-border);
    border-radius: 5px;
    font-size: 11px;
    color: var(--color-text-secondary);
    cursor: pointer;
    font-weight: 500;
    transition: background 0.1s ease-out, color 0.1s ease-out;
}

.preset-btn:hover {
    border-color: var(--color-text-dim);
    color: var(--color-text-primary);
}

.preset-btn--active {
    background: var(--color-scarlet);
    border-color: var(--color-scarlet);
    color: white;
}

.preset-btn--passage {
    color: var(--color-scarlet);
    font-weight: 600;
}

.preset-btn--passage.preset-btn--active {
    background: var(--color-scarlet);
    color: white;
}

.preset-btn--disabled {
    opacity: 0.4;
    cursor: default;
}

.preset-btn--reset {
    color: var(--color-scarlet);
    border-color: var(--color-scarlet);
    background: transparent;
}

.refresh-select {
    font-size: 11px;
    border: 1px solid var(--color-border);
    border-radius: 5px;
    padding: 4px 6px;
    background: var(--color-bg);
    color: var(--color-text-secondary);
    cursor: pointer;
}

.custom-picker {
    display: flex;
    gap: 12px;
    align-items: flex-end;
    padding: 12px 16px;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: 10px;
    margin-bottom: 8px;
    flex-wrap: wrap;
}

.custom-picker-label {
    font-size: 11px;
    font-weight: 600;
    color: var(--color-text-dim);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.custom-picker-input {
    font-size: 13px;
    border: 1px solid var(--color-border);
    border-radius: 6px;
    padding: 6px 10px;
    background: var(--color-bg);
    color: var(--color-text-primary);
}

.custom-picker-input:focus {
    outline: 2px solid var(--color-scarlet);
    outline-offset: -1px;
}

.custom-picker-apply {
    padding: 6px 16px;
    background: var(--color-scarlet);
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
}

.custom-picker-apply:hover {
    background: var(--color-scarlet-hover);
}

.panel {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: 10px;
    padding: 16px;
}

.panel-head {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    margin-bottom: 8px;
}

.panel-title {
    font-size: 15px;
    font-weight: 600;
}

/* Mobile: stack toolbar rows */
@media (max-width: 767px) {
    .toolbar {
        flex-direction: column;
        align-items: stretch;
    }
    .toolbar-right {
        overflow-x: auto;
        flex-wrap: nowrap;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
    }
    .toolbar-right::-webkit-scrollbar { display: none; }
    .custom-picker { flex-direction: column; }
}
</style>
```

- [ ] **Step 2: Verify the page renders in the browser**

Run: `npm run dev` (if not already running)
Navigate to: `/admin/explore?metric=battery_voltage`
Expected: toolbar visible with metric dropdown, time preset buttons in 3 clusters, refresh selector, back link. Chart panel shows placeholder text.

- [ ] **Step 3: Test switching metrics and ranges**

Click the metric dropdown — options grouped by category. Selecting one navigates to the new metric. Click different time preset buttons — URL updates with the range parameter. Click "Custom" — inline date picker appears.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/Admin/Explore.vue
git commit -m "feat: build explore page toolbar with metric selector and time presets"
```

---

### Task 5: Integrate uPlot Chart with Tooltips and Drag-to-Zoom

**Files:**
- Modify: `resources/js/Pages/Admin/Explore.vue`

Replace the chart placeholder with a fully functional uPlot instance. This includes: area fill, crosshair tooltip, Y-axis labels, grid lines, drag-to-zoom, and signed metric support (charge/discharge).

- [ ] **Step 1: Add uPlot chart rendering**

In `resources/js/Pages/Admin/Explore.vue`, add to the `<script setup>` section, after the existing imports:

```js
import { watch, nextTick } from 'vue';
import uPlot from 'uplot';
import 'uplot/dist/uPlot.min.css';
```

Add refs and chart logic after the existing reactive state:

```js
const chartEl = ref(null);
const tooltipEl = ref(null);
let chart = null;
const tooltipData = ref(null);
const chartData = ref(props.data);

function parseColor(oklchStr) {
    return oklchStr;
}

function buildChartOpts(width) {
    const isSigned = props.metric.signed;

    return {
        width,
        height: window.innerWidth < 768 ? 280 : 400,
        cursor: {
            drag: { x: true, y: false, setScale: false },
            sync: { key: 'explore' },
        },
        select: {
            show: true,
            over: true,
        },
        hooks: {
            setSelect: [
                (u) => {
                    const min = u.posToVal(u.select.left, 'x');
                    const max = u.posToVal(u.select.left + u.select.width, 'x');
                    if (max - min < 60) return;
                    zoomed.value = true;
                    const start = Math.floor(min);
                    const end = Math.floor(max);
                    router.get(buildUrl({
                        metric: props.metric.slug,
                        range: 'custom',
                        start,
                        end,
                    }), {}, { preserveState: false });
                },
            ],
            setCursor: [
                (u) => {
                    const idx = u.cursor.idx;
                    if (idx == null) {
                        tooltipData.value = null;
                        return;
                    }
                    const ts = u.data[0][idx];
                    const entries = [];
                    for (let i = 1; i < u.data.length; i++) {
                        const val = u.data[i][idx];
                        if (val != null) {
                            entries.push({
                                label: u.series[i].label,
                                value: val,
                                color: u.series[i].stroke,
                                unit: u.series[i]._unit || '',
                            });
                        }
                    }
                    tooltipData.value = {
                        ts,
                        x: u.cursor.left,
                        entries,
                    };
                },
            ],
        },
        axes: [
            {
                stroke: 'oklch(0.65 0.005 40)',
                grid: { stroke: 'oklch(0.94 0.003 70)', width: 1 },
                ticks: { stroke: 'oklch(0.90 0.005 70)', width: 1 },
                font: '10px system-ui',
                values: (u, vals) => vals.map(v => formatTimestamp(v)),
            },
            {
                stroke: 'oklch(0.65 0.005 40)',
                grid: { stroke: 'oklch(0.94 0.003 70)', width: 1 },
                ticks: { stroke: 'oklch(0.90 0.005 70)', width: 1 },
                font: '10px system-ui',
                size: 50,
                values: (u, vals) => vals.map(v => {
                    if (v == null) return '';
                    return isSigned && v > 0 ? `+${fmtVal(v)}` : fmtVal(v);
                }),
            },
        ],
        series: [
            {},
            {
                label: props.metric.label,
                stroke: parseColor(props.metric.color),
                fill: parseColor(props.metric.color).replace(')', ' / 0.08)').replace('oklch(', 'oklch('),
                width: 1.5,
                _unit: props.metric.unit,
            },
        ],
        scales: {
            x: { time: true },
            y: isSigned ? {} : { range: (u, min, max) => {
                const pad = (max - min) * 0.1 || 1;
                return [min - pad, max + pad];
            }},
        },
    };
}

function fmtVal(v) {
    if (v == null) return '—';
    if (Math.abs(v) >= 100) return v.toFixed(0);
    if (Math.abs(v) >= 10) return v.toFixed(1);
    return v.toFixed(2);
}

function formatTimestamp(ts) {
    const d = new Date(ts * 1000);
    const range = props.end - props.start;
    if (range <= 86400) {
        return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }
    if (range <= 604800) {
        return d.toLocaleDateString([], { day: 'numeric', month: 'short' }) + ' ' +
            d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }
    return d.toLocaleDateString([], { day: 'numeric', month: 'short' });
}

function formatTooltipTime(ts) {
    const d = new Date(ts * 1000);
    return d.toLocaleDateString([], { day: 'numeric', month: 'short', year: 'numeric' }) + ', ' +
        d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

function prepareData() {
    const timestamps = chartData.value.map(d => d.timestamp);
    const values = chartData.value.map(d => d.value);
    const series = [timestamps, values];

    if (props.overlays?.length) {
        for (const ov of props.overlays) {
            series.push(ov.data.map(d => d.value));
        }
    }
    return series;
}

function initChart() {
    if (!chartEl.value || !chartData.value?.length) return;
    if (chart) { chart.destroy(); chart = null; }

    const opts = buildChartOpts(chartEl.value.offsetWidth);

    if (props.overlays?.length) {
        for (const ov of props.overlays) {
            opts.series.push({
                label: ov.metric.label,
                stroke: parseColor(ov.metric.color),
                width: 1.5,
                _unit: ov.metric.unit,
                scale: ov.metric.unit === props.metric.unit ? 'y' : 'y2',
            });
        }

        const hasSecondAxis = props.overlays.some(ov => ov.metric.unit !== props.metric.unit);
        if (hasSecondAxis) {
            opts.axes.push({
                side: 1,
                stroke: 'oklch(0.65 0.005 40)',
                grid: { show: false },
                font: '10px system-ui',
                size: 50,
            });
            opts.scales.y2 = {};
        }
    }

    chart = new uPlot(opts, prepareData(), chartEl.value);
}

const stats = ref({ current: null, min: null, max: null, avg: null });

function computeStats() {
    const vals = (chartData.value || []).map(d => d.value).filter(v => v != null);
    if (!vals.length) {
        stats.value = { current: null, min: null, max: null, avg: null };
        return;
    }
    stats.value = {
        current: vals[vals.length - 1],
        min: Math.min(...vals),
        max: Math.max(...vals),
        avg: vals.reduce((a, b) => a + b, 0) / vals.length,
    };
}

onMounted(() => {
    computeStats();
    nextTick(() => initChart());
    window.addEventListener('resize', handleResize);
});

onUnmounted(() => {
    if (chart) chart.destroy();
    window.removeEventListener('resize', handleResize);
});

let resizeTimeout;
function handleResize() {
    clearTimeout(resizeTimeout);
    resizeTimeout = setTimeout(() => {
        if (chart && chartEl.value) {
            chart.setSize({ width: chartEl.value.offsetWidth, height: window.innerWidth < 768 ? 280 : 400 });
        }
    }, 150);
}
```

- [ ] **Step 2: Update the template chart panel section**

Replace the chart panel placeholder `<div class="panel mt-4">...</div>` with:

```html
        <!-- Chart panel -->
        <div class="panel mt-4">
            <!-- Panel header with inline stats -->
            <div class="panel-head">
                <div class="chart-legend">
                    <span class="legend-dot" :style="{ background: metric.color }"></span>
                    <span class="panel-title">{{ metric.label }}</span>
                    <span v-if="stats.current != null" class="chart-stats">
                        <span class="chart-stat-current" :style="{ color: metric.color }">{{ fmtVal(stats.current) }} {{ metric.unit }}</span>
                        <span class="chart-stat-sep">&middot;</span>
                        <span>{{ fmtVal(stats.min) }} – {{ fmtVal(stats.max) }} {{ metric.unit }}</span>
                        <span class="chart-stat-sep">&middot;</span>
                        <span>avg {{ fmtVal(stats.avg) }}</span>
                    </span>
                </div>
                <button class="overlay-btn" @click="showOverlayPicker = !showOverlayPicker">+ Overlay</button>
            </div>

            <!-- uPlot chart container -->
            <div ref="chartEl" class="chart-container">
                <div v-if="!data?.length" class="chart-empty">No data for this time range</div>
            </div>

            <!-- Tooltip overlay -->
            <div
                v-if="tooltipData"
                class="chart-tooltip"
                :style="{ left: tooltipData.x + 'px' }"
            >
                <div class="chart-tooltip-time">{{ formatTooltipTime(tooltipData.ts) }}</div>
                <div v-for="entry in tooltipData.entries" :key="entry.label" class="chart-tooltip-row">
                    <span class="legend-dot legend-dot--sm" :style="{ background: entry.color }"></span>
                    <span class="chart-tooltip-val">{{ fmtVal(entry.value) }} {{ entry.unit }}</span>
                </div>
                <div class="chart-tooltip-hint">Drag to zoom &middot; Double-click to reset</div>
            </div>
        </div>
```

- [ ] **Step 3: Add chart-specific styles**

Add to the `<style scoped>` block:

```css
.chart-legend {
    display: flex;
    align-items: baseline;
    gap: 6px;
}

.legend-dot {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}

.legend-dot--sm {
    width: 6px;
    height: 6px;
}

.chart-stats {
    font-size: 11px;
    color: var(--color-text-dim);
    font-variant-numeric: tabular-nums;
    margin-left: 8px;
}

.chart-stat-current {
    font-weight: 600;
}

.chart-stat-sep {
    color: var(--color-border);
    margin: 0 4px;
}

.overlay-btn {
    font-size: 11px;
    color: var(--color-scarlet);
    cursor: pointer;
    font-weight: 500;
    background: none;
    border: none;
    padding: 0;
}

.overlay-btn:hover {
    text-decoration: underline;
}

.chart-container {
    position: relative;
    min-height: 280px;
}

.chart-empty {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 280px;
    color: var(--color-text-dim);
    font-size: 13px;
}

.chart-tooltip {
    position: absolute;
    top: 48px;
    transform: translateX(-50%);
    background: oklch(0.18 0.005 40 / 0.92);
    color: white;
    padding: 8px 12px;
    border-radius: 8px;
    font-size: 11px;
    white-space: nowrap;
    pointer-events: none;
    z-index: 10;
    backdrop-filter: blur(6px);
}

.chart-tooltip-time {
    color: oklch(0.70 0.005 40);
    font-size: 10px;
    margin-bottom: 4px;
}

.chart-tooltip-row {
    display: flex;
    align-items: center;
    gap: 6px;
    font-variant-numeric: tabular-nums;
}

.chart-tooltip-val {
    font-weight: 700;
    font-size: 14px;
}

.chart-tooltip-hint {
    color: oklch(0.50 0.005 40);
    font-size: 9px;
    margin-top: 4px;
}

:deep(.u-wrap) {
    position: relative !important;
}

:deep(.u-select) {
    background: oklch(0.54 0.22 27 / 0.1) !important;
}

:deep(.u-cursor-x) {
    border-right: 1px dashed oklch(0.54 0.22 27 / 0.4) !important;
}
```

- [ ] **Step 4: Verify chart renders in the browser**

Navigate to: `/admin/explore?metric=battery_voltage`
Expected: uPlot chart renders with data, area fill, Y-axis labels, grid lines. Hovering shows crosshair and tooltip. Dragging selects a zoom range and navigates to the zoomed view.

- [ ] **Step 5: Test double-click to reset zoom**

Add to the `onMounted` hook, after `initChart()`:

```js
chartEl.value?.addEventListener('dblclick', () => resetZoom());

chartEl.value?.addEventListener('keydown', (e) => {
    if (!chart || !['ArrowLeft', 'ArrowRight'].includes(e.key)) return;
    e.preventDefault();
    const idx = chart.cursor.idx ?? 0;
    const newIdx = e.key === 'ArrowRight'
        ? Math.min(idx + 1, chart.data[0].length - 1)
        : Math.max(idx - 1, 0);
    chart.setCursor({ left: chart.valToPos(chart.data[0][newIdx], 'x'), top: 0 });
});
```

And add `tabindex="0"` to the chart container in the template:

```html
<div ref="chartEl" class="chart-container" tabindex="0">
```

Verify: double-clicking the chart resets to the selected preset range. Pressing left/right arrow keys (when chart is focused) steps the crosshair between data points.

- [ ] **Step 6: Commit**

```bash
git add resources/js/Pages/Admin/Explore.vue
git commit -m "feat: integrate uPlot chart with tooltips and drag-to-zoom"
```

---

### Task 6: Add Overlay Support and Auto-Refresh Data Updates

**Files:**
- Modify: `resources/js/Pages/Admin/Explore.vue`

Wire up the "+ Overlay" button to show a metric picker, fetch overlay series via the JSON endpoint, and update the chart. Also wire auto-refresh to update chart data live.

- [ ] **Step 1: Add overlay picker state and template**

Add reactive state after existing refs:

```js
const showOverlayPicker = ref(false);
const activeOverlays = ref(props.overlays?.map(o => o.metric.slug) || []);
const overlayData = ref(props.overlays || []);
const maxOverlays = 3;
const refreshCountdown = ref(0);
let countdownTimer = null;
```

Add the overlay picker template after the chart panel closing `</div>`:

```html
        <!-- Overlay picker -->
        <div v-if="showOverlayPicker" class="panel mt-2 overlay-picker">
            <div class="overlay-picker-head">
                <span class="text-[13px] font-semibold">Add Overlay</span>
                <button class="text-[11px] text-text-dim" @click="showOverlayPicker = false">&times; Close</button>
            </div>
            <div class="overlay-picker-grid">
                <template v-for="(groupMetrics, group) in metrics" :key="group">
                    <div class="overlay-picker-group">{{ groupLabel(group) }}</div>
                    <button
                        v-for="(m, slug) in groupMetrics"
                        :key="slug"
                        class="overlay-picker-item"
                        :class="{
                            'overlay-picker-item--active': slug === metric.slug || activeOverlays.includes(slug),
                            'overlay-picker-item--disabled': activeOverlays.length >= maxOverlays && !activeOverlays.includes(slug),
                        }"
                        :disabled="slug === metric.slug || (activeOverlays.length >= maxOverlays && !activeOverlays.includes(slug))"
                        @click="toggleOverlay(slug)"
                    >
                        <span class="legend-dot legend-dot--sm" :style="{ background: m.color }"></span>
                        {{ m.label }}
                    </button>
                </template>
            </div>
            <div v-if="activeOverlays.length >= maxOverlays" class="text-[11px] text-text-dim mt-2">3 series max</div>
        </div>

        <!-- Active overlays legend bar -->
        <div v-if="overlayData.length" class="panel mt-2 overlay-legend">
            <span class="text-[12px] font-semibold" style="color: var(--color-text-secondary)">Overlays</span>
            <div v-for="ov in overlayData" :key="ov.metric.slug" class="overlay-legend-item">
                <span class="legend-dot legend-dot--sm" :style="{ background: ov.metric.color }"></span>
                <span class="text-[11px] font-medium">{{ ov.metric.label }}</span>
                <button class="overlay-remove" @click="removeOverlay(ov.metric.slug)">&times;</button>
            </div>
        </div>
```

- [ ] **Step 2: Add overlay toggle and fetch functions**

```js
async function toggleOverlay(slug) {
    if (activeOverlays.value.includes(slug)) {
        removeOverlay(slug);
        return;
    }
    if (activeOverlays.value.length >= maxOverlays) return;

    const allMetrics = props.metrics;
    let metricConfig = null;
    for (const group of Object.values(allMetrics)) {
        if (group[slug]) { metricConfig = { ...group[slug], slug }; break; }
    }
    if (!metricConfig) return;

    const url = `/admin/explore/series?metric=${slug}&start=${props.start}&end=${props.end}&step=${props.step}`;
    try {
        const res = await fetch(url);
        if (!res.ok) return;
        const json = await res.json();
        const series = json[0];

        activeOverlays.value.push(slug);
        overlayData.value.push({ metric: metricConfig, data: series.data });
        rebuildChart();
    } catch {
        // Silent
    }
}

function removeOverlay(slug) {
    activeOverlays.value = activeOverlays.value.filter(s => s !== slug);
    overlayData.value = overlayData.value.filter(o => o.metric.slug !== slug);
    rebuildChart();
}

function rebuildChart() {
    if (chart) { chart.destroy(); chart = null; }
    nextTick(() => initChart());
}
```

- [ ] **Step 3: Update doRefresh to update chart data live**

Replace the existing `doRefresh` function:

```js
async function doRefresh() {
    if (zoomed.value) return;
    const end = Math.floor(Date.now() / 1000);
    const duration = props.end - props.start;
    const start = end - duration;

    const slugs = [props.metric.slug, ...activeOverlays.value];
    const url = `/admin/explore/series?metrics=${slugs.join(',')}&start=${start}&end=${end}&step=${props.step}`;

    try {
        const res = await fetch(url);
        if (!res.ok) return;
        const json = await res.json();

        const primary = json.find(s => s.metric === props.metric.slug);
        if (primary) {
            chartData.value = primary.data;
            computeStats();
        }

        for (const ov of overlayData.value) {
            const updated = json.find(s => s.metric === ov.metric.slug);
            if (updated) ov.data = updated.data;
        }

        if (chart) {
            chart.setData(prepareData());
        }
    } catch {
        // Silent — last data stays rendered
    }
}
```

- [ ] **Step 4: Update restartRefresh with countdown ring**

Replace the existing `restartRefresh`:

```js
function restartRefresh() {
    clearInterval(refreshTimer);
    clearInterval(countdownTimer);
    refreshCountdown.value = refreshInterval.value;

    if (refreshInterval.value > 0) {
        countdownTimer = setInterval(() => {
            refreshCountdown.value = Math.max(0, refreshCountdown.value - 1);
        }, 1000);
        refreshTimer = setInterval(() => {
            doRefresh();
            refreshCountdown.value = refreshInterval.value;
        }, refreshInterval.value * 1000);
    }
}
```

And update `onUnmounted`:

```js
onUnmounted(() => {
    if (chart) chart.destroy();
    clearInterval(refreshTimer);
    clearInterval(countdownTimer);
    window.removeEventListener('resize', handleResize);
});
```

- [ ] **Step 5: Add Prometheus error banner**

Add reactive state:

```js
const fetchError = ref(false);
const retryCountdown = ref(0);
```

Update `doRefresh` to set error state on failure:

```js
    } catch {
        fetchError.value = true;
        retryCountdown.value = refreshInterval.value;
    }
```

And clear on success (inside the `try` block, after updating chart data):

```js
        fetchError.value = false;
```

Add the banner template after the toolbar `</div>` and before the chart panel:

```html
        <!-- Prometheus error banner -->
        <div v-if="fetchError" class="error-banner">
            Metrics unavailable — retrying in {{ retryCountdown }}s
        </div>
```

Add style:

```css
.error-banner {
    padding: 8px 16px;
    background: oklch(0.70 0.14 70 / 0.1);
    border: 1px solid oklch(0.70 0.14 70 / 0.3);
    border-radius: 8px;
    font-size: 12px;
    color: oklch(0.50 0.14 70);
    margin-bottom: 8px;
}
```

- [ ] **Step 6: Add overlay picker styles**

```css
.overlay-picker {
    padding: 12px 16px;
}

.overlay-picker-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.overlay-picker-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}

.overlay-picker-group {
    width: 100%;
    font-size: 10px;
    font-weight: 700;
    color: var(--color-text-dim);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    padding: 8px 0 4px;
}

.overlay-picker-group:first-child {
    padding-top: 0;
}

.overlay-picker-item {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border: 1px solid var(--color-border);
    border-radius: 5px;
    font-size: 11px;
    color: var(--color-text-secondary);
    background: var(--color-bg);
    cursor: pointer;
}

.overlay-picker-item:hover:not(:disabled) {
    border-color: var(--color-text-dim);
}

.overlay-picker-item--active {
    background: var(--color-border-light);
    color: var(--color-text-dim);
    cursor: default;
}

.overlay-picker-item--disabled {
    opacity: 0.35;
    cursor: default;
}

.overlay-legend {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 10px 16px;
    flex-wrap: wrap;
}

.overlay-legend-item {
    display: flex;
    align-items: center;
    gap: 5px;
    font-variant-numeric: tabular-nums;
}

.overlay-remove {
    color: var(--color-scarlet);
    cursor: pointer;
    font-size: 14px;
    background: none;
    border: none;
    padding: 0 2px;
    line-height: 1;
}
```

- [ ] **Step 6: Verify overlays and auto-refresh in the browser**

Navigate to: `/admin/explore?metric=battery_voltage`
1. Click "+ Overlay" — picker appears with grouped metrics
2. Click "Battery Current" — second series appears on chart with different color
3. Set refresh to 15s — chart updates every 15 seconds
4. Verify tooltip shows both series values on hover

- [ ] **Step 7: Commit**

```bash
git add resources/js/Pages/Admin/Explore.vue
git commit -m "feat: add overlay support and auto-refresh data updates"
```

---

### Task 7: Make Existing Charts Clickable

**Files:**
- Modify: `resources/js/Pages/Admin/BoatMetrics.vue`
- Modify: `resources/js/Pages/Admin/Tracker.vue`
- Note: StreamMonitor uses SRT stats (not Prometheus), so its charts are excluded

Wrap each chart panel in an Inertia `<Link>` to navigate to the explore page. Add hover affordance (subtle icon + border highlight).

- [ ] **Step 1: Add explore link styles to app.css**

In `resources/css/app.css`, add at the end:

```css
.explore-link {
    display: block;
    text-decoration: none;
    color: inherit;
    position: relative;
    transition: border-color 0.12s ease-out;
}

.explore-link:hover {
    border-color: var(--color-text-dim);
}

.explore-link .explore-icon {
    position: absolute;
    top: 10px;
    right: 10px;
    opacity: 0;
    transition: opacity 0.12s ease-out;
    color: var(--color-text-dim);
}

.explore-link:hover .explore-icon {
    opacity: 1;
}
```

- [ ] **Step 2: Update BoatMetrics.vue panels**

Add import at the top of the `<script setup>`:

```js
import { Link } from '@inertiajs/vue3';
```

Wrap each chart panel's outer `<div class="panel">` in a `<Link>`. For example, the Battery Voltage panel (line 44):

Change:
```html
<div class="panel">
```
To:
```html
<Link href="/admin/explore?metric=battery_voltage&range=24h" class="panel explore-link">
```

And close with `</Link>` instead of `</div>`.

Add the explore icon SVG inside each linked panel (after the `panel-head` div):

```html
<svg class="explore-icon" viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 2H2v4M14 10v4h-4M2 6l4-4M10 14l4-4"/></svg>
```

Apply to all chart panels:
- Battery Voltage → `?metric=battery_voltage&range=24h`
- Speed → `?metric=speed&range=24h`
- Battery Power → `?metric=battery_power&range=24h`
- Cabin Temperature → `?metric=temp_forepeak&range=24h`
- Cabin Humidity → `?metric=humidity_forepeak&range=24h`
- Diesel Level → `?metric=fuel_level&range=24h`
- Fresh Water Level → `?metric=water_level&range=24h`

Do **not** wrap the Compass or Power & Tanks panels (they are live gauges, not history charts).

- [ ] **Step 3: Update Tracker.vue panels**

Same pattern. Add `Link` import and wrap chart panels:
- Signal Strength → `?metric=lte_rssi&range=1h`
- GPS Quality → `?metric=gps_satellites&range=1h`
- CPU Usage → `?metric=cpu_usage&range=1h`
- Cabin Temperature → `?metric=temp_forepeak&range=6h`
- Humidity → `?metric=humidity_forepeak&range=6h`

- [ ] **Step 4: Update StreamMonitor.vue panels**

Same pattern for the stream monitor charts. Add `Link` import and wrap:
- Bitrate → `?metric=lte_rssi&range=1h` (stream bitrate is not in the explore registry — skip unless the SRT metrics are added to the explore config. For now, skip StreamMonitor panels as they use a different data source — SRT stats, not Prometheus.)

**Note:** StreamMonitor uses SRT stats from a different endpoint, not Prometheus. Its charts are not compatible with the explore page's Prometheus-based queries. Do not make StreamMonitor charts clickable.

- [ ] **Step 5: Verify clickable charts in the browser**

Navigate to `/admin/metrics`. Hover over any chart — subtle expand icon appears in the top-right corner, border highlights. Click the Battery Voltage chart — navigates to `/admin/explore?metric=battery_voltage&range=24h`.

Navigate to `/admin/tracker`. Same behavior for tracker charts.

- [ ] **Step 6: Commit**

```bash
git add resources/js/Pages/Admin/BoatMetrics.vue resources/js/Pages/Admin/Tracker.vue resources/css/app.css
git commit -m "feat: make existing chart panels clickable to open explore view"
```

---

### Task 8: Add Explore Link to Admin Sidebar

**Files:**
- Modify: `resources/js/Layouts/AdminLayout.vue:29`

- [ ] **Step 1: Add Explore nav link**

In `AdminLayout.vue`, after the "Boat Metrics" NavLink (line 29), add:

```html
<NavLink href="/admin/explore?metric=battery_voltage" icon="search" :active="currentPage === 'Admin/Explore'" @click="sidebarOpen = false">Explore</NavLink>
```

- [ ] **Step 2: Verify in the browser**

Check the admin sidebar — "Explore" link appears after "Boat Metrics". Clicking it navigates to the explore page. The link is highlighted when on the explore page.

- [ ] **Step 3: Commit**

```bash
git add resources/js/Layouts/AdminLayout.vue
git commit -m "feat: add Explore link to admin sidebar navigation"
```

---

### Task 9: Final Integration Test and Polish

**Files:**
- Modify: `resources/js/Pages/Admin/Explore.vue` (if fixes needed)

- [ ] **Step 1: Run all PHP tests**

Run: `php artisan test`
Expected: All existing tests pass, plus ExploreControllerTest and ExploreMetricRegistryTest.

- [ ] **Step 2: Run Vite build to verify no compilation errors**

Run: `npm run build`
Expected: Build succeeds with no errors. uPlot is bundled.

- [ ] **Step 3: End-to-end manual test**

1. Navigate to `/admin/metrics` — verify all chart panels show hover explore affordance
2. Click Battery Voltage chart — opens explore page with 24h data
3. Switch to 1h, 7d, 30d presets — data re-queries at appropriate resolution
4. Click "Custom" — date picker appears, set a range, click Apply
5. Drag to zoom on chart — zooms in, "Reset zoom" button appears
6. Double-click — resets zoom
7. Click "+ Overlay" — pick Battery Current — second series appears
8. Hover chart — tooltip shows both values
9. Set refresh to 15s — chart updates automatically
10. Switch metric via dropdown — page navigates, chart updates
11. Click "← Metrics" — returns to Boat Metrics page
12. Test on mobile viewport (resize browser to <768px) — toolbar stacks, presets scroll horizontally

- [ ] **Step 4: Fix any issues found in testing**

Address any visual or functional issues discovered during the manual test.

- [ ] **Step 5: Final commit**

```bash
git add -A
git commit -m "feat: metric explore page - final polish and integration"
```
