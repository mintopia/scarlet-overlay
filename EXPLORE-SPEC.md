# Explore Page Rework — Implementation Spec

## Overview

Replace the Explore dashboard (uniform card grid) with a grouped metric list, and add a metric type system to the detail view so different metrics render with type-appropriate charts, axes, stats, and formatting.

**Two pages, one route:**
- `GET /admin/explore` → `ExploreDashboard.vue` (no `?metric=` param)
- `GET /admin/explore?metric=speed` → `Explore.vue` (with `?metric=` param)

Both stay on the same route. Controller already handles this split.

---

## 1. Metric Type System

Add a `type` key to each metric in `config/scarlet.php`. Six types:

| Type | Y-Axis | Format | Stats | Examples |
|------|--------|--------|-------|---------|
| `standard` | Auto-range with padding | `fmtVal()` | min, max, avg, current | speed, voltage, temperature, pressure, signal |
| `compass` | Fixed 0–360°, cardinal labels | `247° WSW` | current+cardinal, range, circular avg | heading, COG, TWD, AWA |
| `inverted` | Zero at top, increases down | `fmtVal()` | shallowest, deepest, avg, current | depth |
| `gauge` | Fixed 0–100% | `87%` | min, max, rate, time-to-empty/full | SOC, diesel, water, humidity |
| `signed` | Symmetric around zero | `+42` / `-95` | peak charge, peak draw, net energy, current | battery power, battery current |
| `duration` | Auto-range | `2h 14m` | min, max, avg, current | time to waypoint |

Default type is `standard` — metrics without an explicit `type` key use standard.

### Related metrics

Add a `related` key to metrics that should show chip overlays on the detail view:

```php
'speed' => [
    // ... existing keys ...
    'type' => 'standard',
    'related' => ['stw', 'vmg'],
],
'heading' => [
    'type' => 'compass',
    'related' => ['cog', 'wind_direction_true'],
],
```

### Dashboard display hints

Add optional keys for dashboard rendering:

```php
'battery_soc' => [
    // ... existing keys ...
    'type' => 'gauge',
    'dashboard_indicator' => 'level_bar',  // shows inline level bar on dashboard
],
'heading' => [
    'type' => 'compass',
    'dashboard_indicator' => 'mini_compass',  // shows mini compass rose on dashboard
],
```

---

## 2. Config Changes (`config/scarlet.php`)

### Fix broken queries

These metrics reference non-existent series and must be updated:

```php
// BEFORE (broken)
'depth' => [
    'query' => 'scarlet_boat_depth_meters',
],
'heading' => [
    'query' => 'scarlet_boat_heading_deg',
],

// AFTER (working)
'depth' => [
    'query' => 'scarlet_signalk_environment_depth_belowSurface',
    'type' => 'inverted',
],
'heading' => [
    'query' => 'scarlet_signalk_navigation_headingTrue * 180 / 3.14159265359',
    'fallback' => 'scarlet_signalk_navigation_headingMagnetic * 180 / 3.14159265359',
    'type' => 'compass',
],
```

### Add new metrics

Priority tier 1 metrics to add (from VictoriaMetrics audit):

```php
// Navigation
'stw' => [
    'label' => 'Speed (STW)',
    'unit' => 'kn',
    'color' => 'oklch(0.42 0.14 178)',
    'query' => 'scarlet_signalk_navigation_speedThroughWater * 1.94384',
    'group' => 'navigation',
    'type' => 'standard',
    'related' => ['speed', 'vmg'],
],
'heel' => [
    'label' => 'Heel',
    'unit' => '°',
    'color' => 'oklch(0.48 0.17 70)',
    'query' => 'scarlet_signalk_navigation_attitude_roll * 180 / 3.14159265359',
    'group' => 'navigation',
    'type' => 'signed',
],
'pitch' => [
    'label' => 'Pitch',
    'unit' => '°',
    'color' => 'oklch(0.60 0.16 240)',
    'query' => 'scarlet_signalk_navigation_attitude_pitch * 180 / 3.14159265359',
    'group' => 'navigation',
    'type' => 'signed',
],
'heading_magnetic' => [
    'label' => 'Heading (Magnetic)',
    'unit' => '°',
    'color' => 'oklch(0.55 0.12 178)',
    'query' => 'scarlet_signalk_navigation_headingMagnetic * 180 / 3.14159265359',
    'group' => 'navigation',
    'type' => 'compass',
    'related' => ['heading', 'cog'],
],
'trip_log' => [
    'label' => 'Trip Log',
    'unit' => 'nm',
    'color' => 'oklch(0.42 0.14 178)',
    'query' => 'scarlet_signalk_navigation_trip_log / 1852',
    'group' => 'navigation',
    'type' => 'standard',
],

// Tanks (time-to-empty computed on frontend)
'fuel_level' => [
    // ... existing but add type
    'type' => 'gauge',
    'dashboard_indicator' => 'level_bar',
],
'water_level' => [
    // ... existing but add type
    'type' => 'gauge',
    'dashboard_indicator' => 'level_bar',
],

// Power
'battery_soc' => [
    // ... existing but add type
    'type' => 'gauge',
    'dashboard_indicator' => 'level_bar',
    'related' => ['battery_voltage', 'battery_power', 'battery_current'],
],
'battery_power' => [
    // ... existing, already has signed: true
    'type' => 'signed',
    'related' => ['battery_voltage', 'battery_soc', 'battery_current'],
],
'battery_current' => [
    // ... existing but add type
    'type' => 'signed',
],

// Water temperature
'water_temp' => [
    // ... existing, move from cabin to environment group
    'group' => 'environment',
],

// New group: Environment
'air_pressure' => [
    'label' => 'Barometric Pressure',
    'unit' => 'hPa',
    'color' => 'oklch(0.55 0.15 240)',
    'query' => 'scarlet_mqtt_pressure{topic="zigbee2mqtt/Forepeak cabin"}',
    'group' => 'environment',
    'type' => 'standard',
],
```

### Add `type` to all existing metrics

Every metric gets an explicit `type`. Most are `standard`. The ones that change:

| Metric | Type |
|--------|------|
| `depth` | `inverted` |
| `heading`, `cog`, `heading_magnetic` | `compass` |
| `wind_direction_true`, `wind_angle_apparent` | `compass` |
| `battery_soc`, `fuel_level`, `water_level` | `gauge` |
| `humidity_*` | `gauge` |
| `battery_power`, `battery_current`, `heel`, `pitch` | `signed` |
| `nav_wp_ttg` | `duration` |
| Everything else | `standard` |

### Remove `signed` boolean

Replace `'signed' => true` with `'type' => 'signed'`. The `signed` key becomes redundant.

### Update groups

Rename/reorganize groups:

| Old Group | New Group | Notes |
|-----------|-----------|-------|
| `navigation` | `navigation` | Add STW, heel, pitch, heading_magnetic, trip_log |
| `wind` | `wind` | Same |
| `power` | `power` | Same |
| `cabin` | `cabin` | Temperature + humidity only |
| — | `environment` | New: water temp, air pressure, sea state |
| `tanks` | `tanks` | Same |
| `tracker` | `tracker` | Same |

### Dashboard headline metrics per group

Add a config key for which metrics to show in collapsed group summaries on the dashboard:

```php
'explore_groups' => [
    'navigation' => [
        'label' => 'Navigation',
        'color' => 'oklch(0.42 0.14 178)',
        'expanded' => true,  // show all rows by default
    ],
    'wind' => [
        'label' => 'Wind',
        'color' => 'oklch(0.48 0.17 70)',
        'expanded' => false,
        'headlines' => ['wind_speed_true', 'wind_direction_true', 'wind_speed_apparent', 'wind_angle_apparent'],
    ],
    'power' => [
        'label' => 'Power',
        'color' => 'oklch(0.45 0.16 150)',
        'expanded' => true,
    ],
    'tanks' => [
        'label' => 'Tanks',
        'color' => 'oklch(0.48 0.17 70)',
        'expanded' => true,
    ],
    'cabin' => [
        'label' => 'Cabin',
        'color' => 'oklch(0.48 0.17 70)',
        'expanded' => false,
        'headlines' => ['temp_main_cabin', 'temp_forepeak', 'pressure_forepeak'],
    ],
    'environment' => [
        'label' => 'Environment',
        'color' => 'oklch(0.42 0.14 245)',
        'expanded' => false,
        'headlines' => ['water_temp', 'air_pressure'],
    ],
    'tracker' => [
        'label' => 'Tracker',
        'color' => 'oklch(0.52 0.014 205)',
        'expanded' => false,
        'headlines' => ['gps_satellites', 'lte_rssi', 'cpu_usage'],
    ],
],
```

---

## 3. Controller Changes (`ExploreController.php`)

### Dashboard method

The dashboard no longer fetches sparkline chart data. It fetches current values only.

```php
private function dashboard(Request $request, PrometheusService $prometheus, MetricsService $metrics)
{
    $allMetrics = config('scarlet.metrics.mappings.explore');
    $groups = config('scarlet.metrics.mappings.explore_groups');

    // Group metrics
    $grouped = [];
    foreach ($allMetrics as $slug => $metric) {
        $metric['slug'] = $slug;
        $grouped[$metric['group']][$slug] = $metric;
    }

    // Fetch current values for all metrics (single batch query)
    $queries = [];
    foreach ($allMetrics as $slug => $metric) {
        if (!empty($metric['computed'])) continue;
        $queries[$slug] = $metric['query'];
    }
    $currentValues = $prometheus->queryMultipleAt($queries, now()->timestamp);

    // Handle computed metrics
    // (true wind speed/direction need special handling via MetricsService)

    return Inertia::render('Admin/ExploreDashboard', [
        'groups' => $groups,
        'metrics' => $grouped,
        'currentValues' => $currentValues,
    ]);
}
```

Key changes:
- No more 8 hardcoded dashboard slugs
- No more chart data / sparkline queries
- Single batch `queryMultipleAt` for current values
- Pass group config so frontend knows expand/collapse state and headline metrics
- Remove `range`, `start`, `end`, `step`, `passage` props (no longer needed)

### Detail method

Add `journeys` prop for the journey dropdown:

```php
$journeys = Journey::whereNotNull('started_at')
    ->orderByDesc('started_at')
    ->limit(10)
    ->get(['id', 'title', 'from_port', 'to_port', 'started_at', 'ended_at']);
```

Pass to frontend as `journeys` array alongside existing `passage` data.

### Series endpoint

Add support for fetching a companion series for sailing/motoring band inference:

```php
// When the primary metric is speed, optionally include battery_current
// so the frontend can compute sailing vs motoring bands
if ($request->boolean('include_propulsion') && isset($allMetrics['battery_current'])) {
    $bandData = $this->queryMetricRange(
        $prometheus, $allMetrics['battery_current'],
        null, $step, $start, $end, $metrics
    );
    // Return as additional field on the speed series result
}
```

---

## 4. Dashboard Component (`ExploreDashboard.vue`)

Complete rewrite. The new dashboard is a grouped metric list.

### Props

```js
defineProps({
    groups: Object,      // group config from explore_groups
    metrics: Object,     // grouped metrics { navigation: { speed: {...}, ... }, ... }
    currentValues: Object, // { speed: 6.2, depth: 3.2, ... }
});
```

### Structure

```
┌─────────────────────────────────────────┐
│ Explore                    Updated 4s ago│
│ [Search: Filter metrics...]             │
│                                         │
│ NAVIGATION (teal)                       │
│ ┌─ Speed (SOG)          ▲  6.2 kn  › ─┐│
│ │  Speed (STW)          ▲  5.8 kn  › ││
│ │  Heading         [compass] 247°   › ││
│ │  Depth                    3.2 m   › ││
│ │  Heel                     12° stbd› ││
│ │  COG             [compass] 252°   › ││
│ └──────────────────────────────────────┘│
│                                         │
│ WIND (amber) — collapsed                │
│ ┌─ TWS 14.2kn · TWD 215° · ...   8 › ─┐│
│ └──────────────────────────────────────┘│
│                                         │
│ POWER (green)                           │
│ ┌─ Battery SOC   [===87%===] 87%    › ─┐│
│ │  Battery Power          +42 W     › ││
│ │  Voltage                13.2 V    › ││
│ │  Engine Battery         12.8 V    › ││
│ └──────────────────────────────────────┘│
│ ...                                     │
└─────────────────────────────────────────┘
```

### Key behaviors

- **Max width**: match admin layout max-width (not full bleed)
- **Search**: client-side filter on metric labels. Hides groups with no matches.
- **Expanded groups**: show each metric as a row. Click row → navigate to detail view.
- **Collapsed groups**: show headline values inline. Click → expand to show all rows.
- **Trend arrows**: ▲ ▼ — computed by comparing current value to a value from N minutes ago. Arrow hidden if no change or no recent data.
- **Type indicators**: compass metrics show mini compass rose; gauge metrics show inline level bar; signed metrics show +/- prefix with green/scarlet coloring.
- **Staleness**: "Updated Xs ago" next to header. Yellow if >2 min, red if >10 min.
- **No sparklines**: the dashboard is an index, not a monitoring wall.
- **Auto-refresh**: poll current values every 15s via fetch to a lightweight endpoint.

### New lightweight endpoint

Add `GET /admin/explore/current` that returns just current values (no range data):

```php
public function current(PrometheusService $prometheus): JsonResponse
{
    $allMetrics = config('scarlet.metrics.mappings.explore');
    $queries = [];
    foreach ($allMetrics as $slug => $metric) {
        if (!empty($metric['computed'])) continue;
        $queries[$slug] = $metric['query'];
    }
    return response()->json($prometheus->queryMultipleAt($queries, now()->timestamp));
}
```

Route: `Route::get('/explore/current', [ExploreController::class, 'current'])->name('admin.explore.current');`

---

## 5. Detail Component (`Explore.vue`)

Refactor, not rewrite. Most chart logic stays. Changes:

### Toolbar

Replace the three preset-cluster divs + select with:

```html
<div class="toolbar">
  <div class="toolbar-left">
    <h1 class="toolbar-title">{{ metric.label }}</h1>
    <span class="toolbar-unit">{{ metric.unit }}</span>
  </div>
  <div class="toolbar-right">
    <!-- Single segmented control -->
    <div class="range-seg">
      <button v-for="p in allPresets" :key="p" ...>{{ p }}</button>
    </div>
    <!-- Journey dropdown (conditional) -->
    <select v-if="journeys.length" v-model="selectedJourney" class="journey-select">
      <option value="">Journey...</option>
      <option v-for="j in journeys" :key="j.id" :value="j.id">
        {{ j.from_port }} → {{ j.to_port }}
      </option>
    </select>
    <!-- Refresh indicator -->
    <div v-if="refreshInterval > 0" class="refresh-indicator">
      <span class="refresh-dot"></span>
      <span class="refresh-label">{{ refreshInterval }}s</span>
    </div>
  </div>
</div>
```

Presets: `['1h', '6h', '24h', '3d', '7d', '30d']` as one flat array.

### Type-aware chart building

In `buildChartOpts()`, branch on `metric.type`:

**compass:**
```js
scales: { y: { range: [0, 360] } },
axes: [{ /* x axis */ }, {
    values: (u, vals) => vals.map(v => {
        const cardinals = ['N','NE','E','SE','S','SW','W','NW'];
        return `${Math.round(v)}° ${cardinals[Math.round(v / 45) % 8]}`;
    }),
}],
```
Plus wrap-aware rendering: detect jumps >180° between consecutive points and break the path.

**inverted:**
```js
scales: { y: { dir: -1 } },  // uPlot native inversion
```
Stats labels change: "Shallowest" instead of "Min", "Deepest" instead of "Max".

**gauge:**
```js
scales: { y: { range: [0, 100] } },
```
Show level bar in chart header. Compute time-to-full/empty from rate of change.

**signed:**
Keep existing `drawSignedPath` logic. Add net energy computation to stats.

**duration:**
```js
axes: [{ /* x */ }, {
    values: (u, vals) => vals.map(v => fmtDuration(v)),
}],
```

### Sailing/motoring bands (speed metrics only)

When metric slug is `speed` or `stw`:
1. Fetch `battery_current` series alongside the primary metric
2. Compute band regions: positive current = motoring, negative = sailing
3. Draw background bands using uPlot `hooks.draw` before the line renders
4. Show "Avg Sailing" and "Avg Motoring" stats

### Stats bar

Replace the inline `.chart-stats` with a dedicated stats bar below the chart. Content varies by type (see type table in section 1).

### Related metrics panel

Replace the overlay picker modal with a chip bar below the chart:

```html
<div v-if="metric.related?.length" class="related-panel">
  <span class="related-title">Related</span>
  <div class="related-grid">
    <span class="related-chip active">{{ metric.label }}</span>
    <button v-for="slug in metric.related" :key="slug"
      class="related-chip"
      :class="{ active: activeOverlays.includes(slug) }"
      @click="toggleOverlay(slug)">
      {{ metrics[slug]?.label }}
    </button>
  </div>
</div>
```

### Compass header

For compass-type metrics, show a mini compass rose SVG next to the current value, with the needle rotated to the current heading. Include cardinal text (N/S/E/W).

---

## 6. Formatters (`composables/useFormatters.js`)

Expand the formatters composable:

```js
export function fmtVal(v, type = 'standard') {
    if (v == null) return '—';
    switch (type) {
        case 'compass':
            return `${Math.round(v)}°`;
        case 'gauge':
            return Math.round(v).toString();
        case 'signed':
            const rounded = Math.abs(v) >= 10 ? v.toFixed(0) : v.toFixed(1);
            return v > 0 ? `+${rounded}` : rounded;
        case 'duration':
            return fmtDuration(v);
        default:
            if (Math.abs(v) >= 100) return v.toFixed(0);
            if (Math.abs(v) >= 10) return v.toFixed(1);
            return v.toFixed(2);
    }
}

export function bearingToCardinal(deg) {
    const cardinals = ['N','NNE','NE','ENE','E','ESE','SE','SSE',
                        'S','SSW','SW','WSW','W','WNW','NW','NNW'];
    return cardinals[Math.round(((deg % 360) + 360) % 360 / 22.5) % 16];
}

export function fmtDuration(seconds) {
    if (seconds == null) return '—';
    const d = Math.floor(seconds / 86400);
    const h = Math.floor((seconds % 86400) / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    if (d > 0) return `${d}d ${h}h`;
    if (h > 0) return `${h}h ${m}m`;
    return `${m}m`;
}

export function computeRate(data, windowMinutes = 60) {
    // Returns rate of change per hour from recent data
    // Used for time-to-empty/full on gauge metrics
}

export function computeTimeToTarget(currentValue, ratePerHour, target) {
    // Returns estimated time in seconds to reach target
    // target = 0 for empty, 100 for full
}
```

---

## 7. Implementation Order

### Phase 1: Config & backend (no frontend changes yet)
1. Fix broken queries (depth, heading) in `config/scarlet.php`
2. Add `type` to all existing metrics
3. Add `explore_groups` config
4. Add new metrics (STW, heel, pitch, heading_magnetic, trip_log)
5. Remove `signed` boolean, use `type` => `signed` instead
6. Add `related` arrays to metrics
7. Add `dashboard_indicator` where needed
8. Add `current` endpoint to controller
9. Update dashboard controller method (batch current values, pass groups)
10. Update detail controller method (pass journeys list)
11. Test: all endpoints return valid data

### Phase 2: Dashboard rewrite
12. Rewrite `ExploreDashboard.vue` as grouped list
13. Search filter
14. Type-aware indicators (mini compass, level bars, signed coloring)
15. Collapsed group summaries with headlines
16. Auto-refresh via `/explore/current`
17. Staleness indicator
18. Test: dashboard loads, search works, navigation to detail works

### Phase 3: Detail view type system
19. Expand `useFormatters.js`
20. Refactor toolbar (segmented control, journey dropdown, refresh dot)
21. Add type-aware `buildChartOpts()` branching
22. Implement compass type (0–360 axis, cardinal labels, wrap handling)
23. Implement inverted type (depth with zero at top)
24. Implement gauge type (0–100 scale, level bar header, time-to-empty)
25. Implement duration formatting on Y axis
26. Type-aware stats bar
27. Related metrics chip bar (replace overlay picker)
28. Compass header SVG for compass-type metrics
29. Test: each type renders correctly with real data

### Phase 4: Sailing/motoring bands
30. Fetch battery_current alongside speed metrics
31. Compute band regions from current polarity
32. Draw bands in uPlot hooks.draw
33. Compute and show avg sailing / avg motoring stats
34. Test: bands appear correctly on speed detail view

---

## 8. Files Changed

| File | Action |
|------|--------|
| `config/scarlet.php` | Edit: fix queries, add types, add metrics, add groups config |
| `app/Http/Controllers/Admin/ExploreController.php` | Edit: new dashboard method, current endpoint, journeys |
| `routes/web.php` | Edit: add `/explore/current` route |
| `resources/js/Pages/Admin/ExploreDashboard.vue` | Rewrite |
| `resources/js/Pages/Admin/Explore.vue` | Refactor: toolbar, type system, related panel, stats bar |
| `resources/js/composables/useFormatters.js` | Edit: expand with type-aware formatting |

No new files created (everything fits in existing files).
