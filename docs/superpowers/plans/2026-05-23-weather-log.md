# Weather & Ship's Log Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add weather summary to admin dashboard, a full weather page, a ship's log page with hourly metric snapshots, and embed the same log table on the journey edit page.

**Architecture:** Weather data comes from `WeatherService` (Open-Meteo API) and boat sensor metrics from Prometheus via `MetricsService`. The log page queries Prometheus range data for multiple metrics at hourly intervals, aligns them by timestamp, and returns rows for a Vue table. A shared `LogTable` component is reused between the standalone Log page and the JourneyEdit page.

**Tech Stack:** Laravel (PHP 8.3), Inertia.js, Vue 3 (Composition API), Prometheus range queries, existing admin panel patterns.

---

### Task 1: Add WMO condition text to Weather model

**Files:**
- Modify: `app/Models/Weather.php`

- [ ] **Step 1: Add `getConditionText()` method to Weather model**

In `app/Models/Weather.php`, add this method after the existing `getWeatherSummary()` method:

```php
public function getConditionText(): string
{
    return match ($this->wmoCode) {
        0 => 'Clear sky',
        1 => 'Mainly clear',
        2 => 'Partly cloudy',
        3 => 'Overcast',
        45, 48 => 'Fog',
        51, 53, 55 => 'Drizzle',
        56, 57 => 'Freezing drizzle',
        61, 63, 65 => 'Rain',
        66, 67 => 'Freezing rain',
        71, 73, 75 => 'Snow',
        77 => 'Snow grains',
        80, 81, 82 => 'Showers',
        85, 86 => 'Snow showers',
        95, 96, 99 => 'Thunderstorm',
        default => 'Unknown',
    };
}
```

- [ ] **Step 2: Add `conditionText` to WeatherResource**

In `app/Http/Resources/V1/WeatherResource.php`, add `'conditionText' => $this->getConditionText(),` to the `toArray()` return array, after the `'summary'` key.

- [ ] **Step 3: Commit**

```bash
git add app/Models/Weather.php app/Http/Resources/V1/WeatherResource.php
git commit -m "feat: add WMO condition text to Weather model and resource"
```

---

### Task 2: Add weather data to admin dashboard

**Files:**
- Modify: `app/Http/Controllers/Admin/AdminDashboardController.php`
- Modify: `resources/js/Pages/Admin/Dashboard.vue`

- [ ] **Step 1: Add weather prop to controller**

In `app/Http/Controllers/Admin/AdminDashboardController.php`, add the `MetricsService::getWeatherData()` call. The `MetricsService` is already injected. Add `'weather' => $metrics->getWeatherData(),` to the `Inertia::render()` props array, after `'tracker'`.

- [ ] **Step 2: Add weather summary panel to Dashboard.vue**

In `resources/js/Pages/Admin/Dashboard.vue`, add `weather: Object,` to `defineProps`. Then add this panel in the template after the Navigation panel (after line 93, before the Tracker Status panel):

```vue
<!-- Weather -->
<div v-if="weather" class="panel p-4 mb-4">
    <div class="flex items-baseline justify-between mb-3">
        <span class="panel-title">Weather</span>
        <Link href="/admin/weather" class="text-[12px] text-scarlet font-medium hover:underline">View Weather →</Link>
    </div>
    <div class="strip">
        <div class="strip-cell">
            <div class="strip-label">Conditions</div>
            <div class="strip-value text-[16px]">{{ weather.conditionText }}</div>
        </div>
        <div class="strip-cell">
            <div class="strip-label">Air Temp</div>
            <div class="strip-value">{{ fmt(weather.temp) }}</div>
            <div class="strip-unit">°C</div>
        </div>
        <div class="strip-cell">
            <div class="strip-label">Wind</div>
            <div class="strip-value">{{ fmt(weather.wind?.speed) }}</div>
            <div class="strip-unit">kn {{ degreesToCompass(weather.wind?.direction) }}</div>
        </div>
        <div class="strip-cell">
            <div class="strip-label">Sea Temp</div>
            <div class="strip-value" style="color: oklch(0.55 0.15 240)">{{ fmt(weather.seaTemp) }}</div>
            <div class="strip-unit">°C</div>
        </div>
    </div>
    <div v-if="boat?.wind_speed_true != null || boat?.water_temp != null" class="text-[11px] text-text-dim mt-2">
        <span class="font-semibold">Boat sensors:</span>
        <span v-if="boat?.wind_speed_true != null"> Wind {{ fmt(boat.wind_speed_true) }} kn {{ degreesToCompass(boat.wind_direction_true) }}</span>
        <span v-if="boat?.water_temp != null"> · Water {{ fmt(boat.water_temp) }}°C</span>
    </div>
</div>
```

- [ ] **Step 3: Add `degreesToCompass` helper to Dashboard.vue script**

Add this function in the `<script setup>` section:

```javascript
function degreesToCompass(deg) {
    if (deg == null) return '—';
    const dirs = ['N','NNE','NE','ENE','E','ESE','SE','SSE','S','SSW','SW','WSW','W','WNW','NW','NNW'];
    return dirs[Math.round(((deg % 360) + 360) % 360 / 22.5) % 16];
}
```

- [ ] **Step 4: Verify the dashboard renders correctly**

Run: `php artisan serve` (or access via existing dev server) and check `/admin`. The weather panel should appear with conditions, temp, wind, and sea temp. The boat sensor comparison line should appear below if wind/water data is available.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Admin/AdminDashboardController.php resources/js/Pages/Admin/Dashboard.vue
git commit -m "feat: add weather summary panel to admin dashboard"
```

---

### Task 3: Add nav icons for Weather and Log to NavLink component

**Files:**
- Modify: `resources/js/Layouts/NavLink.vue`
- Modify: `resources/js/Layouts/AdminLayout.vue`

- [ ] **Step 1: Add `cloud` and `clipboard` icon templates to NavLink.vue**

In `resources/js/Layouts/NavLink.vue`, add these two templates inside the `<svg>` element, after the existing `<template v-else-if="icon === 'search'">` block:

```vue
<template v-else-if="icon === 'cloud'"><path d="M18 10h-1.26A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10z"/></template>
<template v-else-if="icon === 'clipboard'"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></template>
```

- [ ] **Step 2: Add Weather and Log nav items to AdminLayout.vue sidebar**

In `resources/js/Layouts/AdminLayout.vue`, add these two `NavLink` entries in the `<nav>` section. Add the Weather link after the "Boat Metrics" NavLink (after line 29). Add the Log link after the "Journeys" NavLink (after line 27):

The nav section should become (showing relevant lines only):

```vue
<NavLink href="/admin/journeys" icon="compass" :active="currentPage?.startsWith('Admin/Journey')" @click="sidebarOpen = false">Journeys</NavLink>
<NavLink href="/admin/log" icon="clipboard" :active="currentPage === 'Admin/Log'" @click="sidebarOpen = false">Log</NavLink>
<NavLink href="/admin/tracker" icon="activity" :active="currentPage === 'Admin/Tracker'" @click="sidebarOpen = false">Tracker</NavLink>
<NavLink href="/admin/metrics" icon="chart" :active="currentPage === 'Admin/BoatMetrics'" @click="sidebarOpen = false">Boat Metrics</NavLink>
<NavLink href="/admin/weather" icon="cloud" :active="currentPage === 'Admin/Weather'" @click="sidebarOpen = false">Weather</NavLink>
<NavLink href="/admin/explore?metric=battery_voltage" icon="search" :active="currentPage === 'Admin/Explore'" @click="sidebarOpen = false">Explore</NavLink>
```

- [ ] **Step 3: Commit**

```bash
git add resources/js/Layouts/NavLink.vue resources/js/Layouts/AdminLayout.vue
git commit -m "feat: add Weather and Log nav items to admin sidebar"
```

---

### Task 4: Create Weather page (controller, route, Vue)

**Files:**
- Create: `app/Http/Controllers/Admin/AdminWeatherController.php`
- Create: `resources/js/Pages/Admin/Weather.vue`
- Modify: `routes/web.php`

- [ ] **Step 1: Create AdminWeatherController**

Create `app/Http/Controllers/Admin/AdminWeatherController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\MetricsService;
use Inertia\Inertia;

class AdminWeatherController extends Controller
{
    public function index(MetricsService $metrics)
    {
        return Inertia::render('Admin/Weather', [
            'weather' => $metrics->getWeatherData(),
            'boat' => $metrics->getBoatMetrics(),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
```

- [ ] **Step 2: Add route**

In `routes/web.php`, add the `use` statement at the top:

```php
use App\Http\Controllers\Admin\AdminWeatherController;
```

Add the route inside the admin middleware group, after the explore routes (after line 66):

```php
Route::get('/weather', [AdminWeatherController::class, 'index'])->name('admin.weather');
```

- [ ] **Step 3: Create Weather.vue**

Create `resources/js/Pages/Admin/Weather.vue`:

```vue
<template>
    <AdminLayout>
        <Head title="Weather" />
        <h1 class="text-[22px] font-bold mb-6">Weather</h1>

        <template v-if="weather">
            <!-- Conditions -->
            <div class="panel p-4 mb-4">
                <div class="panel-title mb-3">Conditions</div>
                <div class="space-y-2 text-[13px]">
                    <div class="data-row"><span>Conditions</span><span>{{ weather.conditionText }}</span></div>
                    <div class="data-row"><span>Air Temperature</span><span>{{ fmt(weather.temp) }}°C</span></div>
                    <div class="data-row"><span>Time of Day</span><span>{{ weather.daytime ? 'Day' : 'Night' }}</span></div>
                </div>
            </div>

            <!-- Wind Comparison -->
            <div class="panel p-4 mb-4">
                <div class="panel-title mb-3">Wind</div>
                <table class="w-full text-[13px]">
                    <thead>
                        <tr class="text-left text-[11px] font-semibold text-text-dim uppercase tracking-wide">
                            <th class="pb-2">Source</th>
                            <th class="pb-2">Speed</th>
                            <th class="pb-2">Direction</th>
                        </tr>
                    </thead>
                    <tbody class="tabular-nums">
                        <tr class="border-t border-border-light">
                            <td class="py-2 text-text-secondary">Forecast</td>
                            <td class="py-2 font-semibold">{{ fmt(weather.wind?.speed) }} kn</td>
                            <td class="py-2 font-semibold">{{ degreesToCompass(weather.wind?.direction) }} ({{ fmt(weather.wind?.direction, 0) }}°)</td>
                        </tr>
                        <tr class="border-t border-border-light">
                            <td class="py-2 text-text-secondary">Boat (true)</td>
                            <td class="py-2 font-semibold">{{ fmt(boat?.wind_speed_true) }} kn</td>
                            <td class="py-2 font-semibold">{{ degreesToCompass(boat?.wind_direction_true) }} ({{ fmt(boat?.wind_direction_true, 0) }}°)</td>
                        </tr>
                        <tr class="border-t border-border-light">
                            <td class="py-2 text-text-secondary">Boat (apparent)</td>
                            <td class="py-2 font-semibold">{{ fmt(boat?.wind_speed_apparent) }} kn</td>
                            <td class="py-2 font-semibold">{{ fmt(boat?.wind_angle_apparent, 0) }}°</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Sea State -->
            <div class="panel p-4 mb-4">
                <div class="panel-title mb-3">Sea State</div>
                <div class="space-y-2 text-[13px]">
                    <div class="data-row"><span>Wave Height</span><span>{{ fmt(weather.waves?.height) }} m</span></div>
                    <div class="data-row"><span>Wave Direction</span><span>{{ degreesToCompass(weather.waves?.direction) }} ({{ fmt(weather.waves?.direction, 0) }}°)</span></div>
                    <div class="data-row"><span>Wave Period</span><span>{{ fmt(weather.waves?.period) }} s</span></div>
                    <div class="data-row"><span>Sea Surface Temp (forecast)</span><span style="color: oklch(0.55 0.15 240)">{{ fmt(weather.seaTemp) }}°C</span></div>
                    <div class="data-row"><span>Water Temp (boat sensor)</span><span style="color: oklch(0.55 0.15 240)">{{ fmt(boat?.water_temp) }}°C</span></div>
                </div>
            </div>

            <!-- Ocean Current -->
            <div class="panel p-4 mb-4">
                <div class="panel-title mb-3">Ocean Current</div>
                <div class="space-y-2 text-[13px]">
                    <div class="data-row"><span>Current Speed</span><span>{{ fmt(weather.current?.speed) }} kn</span></div>
                    <div class="data-row"><span>Current Direction</span><span>{{ degreesToCompass(weather.current?.direction) }} ({{ fmt(weather.current?.direction, 0) }}°)</span></div>
                </div>
            </div>
        </template>

        <div v-else class="panel p-6 text-center text-[13px] text-text-secondary">
            Weather data unavailable. Check GPS position and network connectivity.
        </div>

        <p v-if="timestamp" class="text-[11px] text-text-dim mt-2 tabular-nums">Updated {{ timestamp }}</p>
    </AdminLayout>
</template>

<script setup>
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { fmt } from '@/composables/useFormatters.js';

defineProps({
    weather: Object,
    boat: Object,
    timestamp: String,
});

function degreesToCompass(deg) {
    if (deg == null) return '—';
    const dirs = ['N','NNE','NE','ENE','E','ESE','SE','SSE','S','SSW','SW','WSW','W','WNW','NW','NNW'];
    return dirs[Math.round(((deg % 360) + 360) % 360 / 22.5) % 16];
}
</script>

<style scoped>
.panel-title { font-size: 15px; font-weight: 600; }
.data-row { display: flex; justify-content: space-between; align-items: baseline; }
.data-row span:first-child { color: var(--color-text-secondary); }
.data-row span:last-child { font-variant-numeric: tabular-nums; font-weight: 600; }
</style>
```

- [ ] **Step 4: Verify weather page renders**

Navigate to `/admin/weather` — all four panels should display with forecast and boat sensor data.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Admin/AdminWeatherController.php resources/js/Pages/Admin/Weather.vue routes/web.php
git commit -m "feat: add full weather page with boat sensor comparison"
```

---

### Task 5: Add `getLogData()` to MetricsService and log metric mappings

**Files:**
- Modify: `config/scarlet.php`
- Modify: `app/Services/MetricsService.php`

- [ ] **Step 1: Add log metric mappings to config**

In `config/scarlet.php`, add a `'log'` key inside `'mappings'` (after the `'explore'` block, before the closing `],` of `'mappings'`):

```php
'log' => [
    'trip_log' => 'scarlet_signalk_navigation_trip_log / 1852',
    'wind_direction' => 'scarlet_boat_wind_direction_deg',
    'wind_speed' => 'scarlet_boat_wind_speed_kn',
    'latitude' => 'scarlet_signalk_navigation_position_latitude',
    'longitude' => 'scarlet_signalk_navigation_position_longitude',
    'wp_distance' => 'scarlet_signalk_navigation_courseGreatCircle_nextPoint_distance / 1852',
    'wp_ttg' => 'scarlet_signalk_navigation_courseGreatCircle_nextPoint_timeToGo',
    'battery_soc' => 'scarlet_signalk_electrical_batteries_0_capacity_stateOfCharge * 100',
    'water_level' => 'scarlet_mqtt_percent{topic="watertank"}',
    'fuel_level' => 'clamp_max(scarlet_signalk_tanks_fuel_currentLevel / 0.91, 1) * 100',
],
```

- [ ] **Step 2: Add `getLogData()` method to MetricsService**

In `app/Services/MetricsService.php`, add this method:

```php
public function getLogData(?string $duration = '24h', string $step = '3600', ?int $start = null, ?int $end = null): array
{
    $queries = config('scarlet.metrics.mappings.log');
    $seriesByKey = [];

    foreach ($queries as $key => $promql) {
        $data = $this->prometheus->queryRange($promql, $duration, $step . 's', $start, $end);
        $seriesByKey[$key] = collect($data)->keyBy('timestamp');
    }

    $allTimestamps = collect($seriesByKey)
        ->flatMap(fn ($series) => $series->keys())
        ->unique()
        ->sort()
        ->values();

    $rows = [];
    foreach ($allTimestamps as $ts) {
        $row = ['timestamp' => $ts];
        foreach ($queries as $key => $promql) {
            $point = $seriesByKey[$key]->get($ts);
            $row[$key] = $point ? $point['value'] : null;
        }
        $rows[] = $row;
    }

    return $rows;
}
```

- [ ] **Step 3: Commit**

```bash
git add config/scarlet.php app/Services/MetricsService.php
git commit -m "feat: add log metric mappings and getLogData() to MetricsService"
```

---

### Task 6: Create Log page (controller, route, Vue)

**Files:**
- Create: `app/Http/Controllers/Admin/AdminLogController.php`
- Create: `resources/js/Pages/Admin/Log.vue`
- Modify: `routes/web.php`

- [ ] **Step 1: Create AdminLogController**

Create `app/Http/Controllers/Admin/AdminLogController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\MetricsService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminLogController extends Controller
{
    public function index(Request $request, MetricsService $metrics)
    {
        $period = $request->input('period', '24h');
        $allowed = ['6h', '12h', '24h', '48h', '168h'];
        if (!in_array($period, $allowed)) {
            $period = '24h';
        }

        return Inertia::render('Admin/Log', [
            'rows' => $metrics->getLogData($period, '3600'),
            'period' => $period,
        ]);
    }
}
```

- [ ] **Step 2: Add route**

In `routes/web.php`, add the `use` statement:

```php
use App\Http\Controllers\Admin\AdminLogController;
```

Add the route inside the admin middleware group, after the weather route:

```php
Route::get('/log', [AdminLogController::class, 'index'])->name('admin.log');
```

- [ ] **Step 3: Create Log.vue**

Create `resources/js/Pages/Admin/Log.vue`:

```vue
<template>
    <AdminLayout>
        <Head title="Ship's Log" />
        <div class="flex items-baseline justify-between mb-6">
            <h1 class="text-[22px] font-bold">Ship's Log</h1>
            <select v-model="selectedPeriod" @change="changePeriod" class="field-input w-auto text-[13px] py-1.5 px-3">
                <option value="6h">Last 6 hours</option>
                <option value="12h">Last 12 hours</option>
                <option value="24h">Last 24 hours</option>
                <option value="48h">Last 48 hours</option>
                <option value="168h">Last 7 days</option>
            </select>
        </div>

        <LogTable :rows="rows" :show-date="showDate" />
    </AdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import LogTable from '@/components/LogTable.vue';

const props = defineProps({
    rows: Array,
    period: String,
});

const showDate = ['48h', '168h'].includes(props.period);
const selectedPeriod = ref(props.period);

function changePeriod() {
    router.get('/admin/log', { period: selectedPeriod.value }, { preserveState: true });
}
</script>
```

- [ ] **Step 4: Create the shared LogTable component**

Create `resources/js/components/LogTable.vue`:

```vue
<template>
    <div class="panel p-0 overflow-x-auto">
        <table class="log-table">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Trip Log</th>
                    <th>Wind Dir</th>
                    <th>Wind (Bft)</th>
                    <th>Baro</th>
                    <th>Lat/Long</th>
                    <th>WP Dist</th>
                    <th>WP TTG</th>
                    <th>Batt %</th>
                    <th>Water %</th>
                    <th>Fuel %</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="row in rows" :key="row.timestamp">
                    <td class="whitespace-nowrap">{{ fmtTime(row.timestamp) }}</td>
                    <td>{{ fmtVal(row.trip_log, 1) }}</td>
                    <td>{{ degreesToCompass(row.wind_direction) }}</td>
                    <td>{{ knotsToBeaufort(row.wind_speed) }}</td>
                    <td>—</td>
                    <td class="whitespace-nowrap">{{ fmtCoord(row.latitude, row.longitude) }}</td>
                    <td>{{ fmtVal(row.wp_distance, 1) }}</td>
                    <td class="whitespace-nowrap">{{ fmtTtg(row.wp_ttg) }}</td>
                    <td>{{ fmtPct(row.battery_soc) }}</td>
                    <td>{{ fmtPct(row.water_level) }}</td>
                    <td>{{ fmtPct(row.fuel_level) }}</td>
                </tr>
                <tr v-if="!rows.length">
                    <td colspan="11" class="text-center text-text-dim py-6">No log data for this period.</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<script setup>
const props = defineProps({
    rows: { type: Array, default: () => [] },
    showDate: { type: Boolean, default: false },
});

function fmtTime(ts) {
    const d = new Date(ts * 1000);
    if (props.showDate) {
        return d.toLocaleDateString('en-GB', { day: '2-digit', month: '2-digit' }) + ' ' +
               d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
    }
    return d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
}

function fmtVal(v, decimals = 1) {
    return v != null ? Number(v).toFixed(decimals) : '—';
}

function fmtPct(v) {
    return v != null ? Math.round(v) + '%' : '—';
}

function fmtCoord(lat, lon) {
    if (lat == null || lon == null) return '—';
    return formatDM(lat, 'N', 'S') + ' ' + formatDM(lon, 'E', 'W');
}

function formatDM(decimal, pos, neg) {
    const dir = decimal >= 0 ? pos : neg;
    const abs = Math.abs(decimal);
    const deg = Math.floor(abs);
    const min = ((abs - deg) * 60).toFixed(3);
    return `${deg}°${min}'${dir}`;
}

function fmtTtg(seconds) {
    if (seconds == null || seconds <= 0) return '—';
    const s = Math.floor(seconds);
    const d = Math.floor(s / 86400);
    const h = Math.floor((s % 86400) / 3600);
    const m = Math.floor((s % 3600) / 60);
    if (d > 0) return `${d}d ${h}h`;
    if (h > 0) return `${h}h ${m}m`;
    return `${m}m`;
}

function degreesToCompass(deg) {
    if (deg == null) return '—';
    const dirs = ['N','NNE','NE','ENE','E','ESE','SE','SSE','S','SSW','SW','WSW','W','WNW','NW','NNW'];
    return dirs[Math.round(((deg % 360) + 360) % 360 / 22.5) % 16];
}

function knotsToBeaufort(kn) {
    if (kn == null) return '—';
    if (kn < 1) return '0';
    if (kn <= 3) return '1';
    if (kn <= 6) return '2';
    if (kn <= 10) return '3';
    if (kn <= 16) return '4';
    if (kn <= 21) return '5';
    if (kn <= 27) return '6';
    if (kn <= 33) return '7';
    if (kn <= 40) return '8';
    if (kn <= 47) return '9';
    if (kn <= 55) return '10';
    if (kn <= 63) return '11';
    return '12';
}
</script>

<style scoped>
.log-table {
    width: 100%;
    font-size: 13px;
    font-variant-numeric: tabular-nums;
    border-collapse: collapse;
}

.log-table th {
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--color-text-dim);
    padding: 10px 8px;
    text-align: left;
    white-space: nowrap;
    border-bottom: 1px solid var(--color-border);
}

.log-table td {
    padding: 8px;
    border-bottom: 1px solid var(--color-border-light);
}

.log-table tbody tr:hover {
    background: oklch(0.98 0.003 70);
}
</style>
```

- [ ] **Step 5: Verify the Log page**

Navigate to `/admin/log`. The table should display with hourly rows for the last 24 hours. Switch between periods with the dropdown.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Admin/AdminLogController.php resources/js/Pages/Admin/Log.vue resources/js/components/LogTable.vue routes/web.php
git commit -m "feat: add ship's log page with hourly metric snapshots"
```

---

### Task 7: Add log table to JourneyEdit page

**Files:**
- Modify: `app/Http/Controllers/Admin/JourneyController.php`
- Modify: `resources/js/Pages/Admin/JourneyEdit.vue`

- [ ] **Step 1: Add log data to JourneyController::edit()**

In `app/Http/Controllers/Admin/JourneyController.php`, update the `edit()` method. Add `MetricsService $metrics` as a parameter, and add log data when the journey has start and end times.

Replace the `edit` method:

```php
public function edit(Journey $journey, MetricsService $metrics)
{
    $logRows = [];
    if ($journey->started_at && $journey->ended_at) {
        $durationHours = $journey->started_at->diffInHours($journey->ended_at);
        if ($durationHours > 168) {
            $step = 14400;
        } elseif ($durationHours > 72) {
            $step = 7200;
        } else {
            $step = 3600;
        }
        $logRows = $metrics->getLogData(
            null,
            (string) $step,
            $journey->started_at->timestamp,
            $journey->ended_at->timestamp,
        );
    }

    return Inertia::render('Admin/JourneyEdit', [
        'journey' => array_merge(
            $journey->only('id', 'slug', 'title', 'from_port', 'to_port', 'is_public', 'notes', 'status', 'gpx_route_path'),
            [
                'started_at' => $journey->started_at?->toIso8601String(),
                'ended_at' => $journey->ended_at?->toIso8601String(),
                'track_point_count' => $journey->trackPoints()->count(),
            ],
        ),
        'logRows' => $logRows,
    ]);
}
```

- [ ] **Step 2: Add LogTable to JourneyEdit.vue**

In `resources/js/Pages/Admin/JourneyEdit.vue`, add the import for LogTable in the `<script setup>`:

```javascript
import LogTable from '@/components/LogTable.vue';
```

Update `defineProps` to include `logRows`:

```javascript
const props = defineProps({
    journey: Object,
    logRows: { type: Array, default: () => [] },
});
```

Add the log section in the template, after the Track Data panel (after the `</div>` on line 76, before the Danger Zone panel):

```vue
<!-- Ship's Log -->
<div v-if="logRows.length" class="panel p-0 mb-6">
    <div class="px-6 pt-5 pb-3">
        <h2 class="text-[15px] font-semibold">Ship's Log</h2>
    </div>
    <LogTable :rows="logRows" :show-date="true" />
</div>
```

- [ ] **Step 3: Verify on a completed journey**

Navigate to `/admin/journeys/{id}/edit` for a journey that has start and end times. The Ship's Log table should appear with data from the journey's time period.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/Admin/JourneyController.php resources/js/Pages/Admin/JourneyEdit.vue
git commit -m "feat: add ship's log table to journey edit page"
```
