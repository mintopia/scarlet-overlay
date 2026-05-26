# Signal K Data Sources & Admin Rework Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Switch boat metrics to Signal K sources (more accurate than tracker GPS), remove Under Sail/Power status, add heel to overlay, rework admin to show real battery/tank/environment data with 3-zone temperature and humidity charts.

**Architecture:** The `config/scarlet.php` mappings drive all data. Each mapping value is a PromQL expression passed directly to Prometheus — unit conversions (radians→degrees, m/s→knots, Kelvin→Celsius) happen in PromQL. The `MetricsService` queries these mappings via `PrometheusService::queryMultiple()`. The admin controller passes additional history arrays for charts. Frontend components consume the data via props and WebSocket updates.

**Tech Stack:** Laravel 11 (PHP), Vue 3 + Inertia, Prometheus, Tailwind CSS, SVG charts (hand-rolled)

**Data source reference (Signal K uses SI units):**
| Signal K metric | SI unit | Display unit | PromQL conversion |
|---|---|---|---|
| `speedOverGround` | m/s | knots | `* 1.94384` |
| `speedThroughWater` | m/s | knots | `* 1.94384` |
| `headingMagnetic` | radians | degrees | `* 180 / 3.14159265359` |
| `courseOverGroundTrue` | radians | degrees | `* 180 / 3.14159265359` |
| `attitude_roll` | radians | degrees | `* 180 / 3.14159265359` |
| `wind_speedApparent` | m/s | knots | `* 1.94384` |
| `wind_angleApparent` | radians | degrees | `* 180 / 3.14159265359` |
| `water_temperature` | Kelvin | Celsius | `- 273.15` |
| `trip_log` | meters | nautical miles | `/ 1852` |
| `batteries_0_capacity_stateOfCharge` | ratio 0–1 | percent | `* 100` |
| `batteries_0_capacity_timeRemaining` | seconds | hours | `/ 3600` |

**MQTT sensor topics:**
| Topic | Metrics | Location |
|---|---|---|
| `zigbee2mqtt/Main Cabin` | temperature, humidity | Main Cabin |
| `zigbee2mqtt/Quarterberth` | temperature, humidity | Quarterberth |
| `watertank` | percent | Water tank level |
| Tracker device | `scarlet_environment_temperature_celsius`, `scarlet_environment_humidity_percent` | Forepeak |

---

### Task 1: Expand boat metric mappings in config

**Files:**
- Modify: `config/scarlet.php:34-73`

This is the foundation — all other tasks depend on this config being correct.

- [ ] **Step 1: Update the boat metrics mapping**

Replace the `'boat'` array in `config/scarlet.php` (lines 35–39) with the full Signal K + MQTT mapping:

```php
'boat' => [
    // Navigation (Signal K — more accurate than tracker GPS)
    'speed_sog' => 'scarlet_signalk_navigation_speedOverGround * 1.94384',
    'speed_stw' => 'scarlet_signalk_navigation_speedThroughWater * 1.94384',
    'heading' => 'scarlet_signalk_navigation_headingMagnetic * 180 / 3.14159265359',
    'cog' => 'scarlet_signalk_navigation_courseOverGroundTrue * 180 / 3.14159265359',
    'depth' => 'scarlet_signalk_environment_depth_belowSurface',
    'heel' => 'scarlet_signalk_navigation_attitude_roll * 180 / 3.14159265359',
    'trip_log' => 'scarlet_signalk_navigation_trip_log / 1852',

    // Wind (apparent from Signal K, true from tracker)
    'wind_speed_apparent' => 'scarlet_signalk_environment_wind_speedApparent * 1.94384',
    'wind_angle_apparent' => 'scarlet_signalk_environment_wind_angleApparent * 180 / 3.14159265359',
    'wind_speed_true' => 'scarlet_boat_wind_speed_kn',
    'wind_direction_true' => 'scarlet_boat_wind_direction_deg',

    // Environment
    'air_temp' => 'scarlet_environment_temperature_celsius',
    'water_temp' => 'scarlet_signalk_environment_water_temperature - 273.15',

    // Batteries (Signal K: bank 0 = house, bank 1 = engine)
    'house_battery_voltage' => 'scarlet_signalk_electrical_batteries_0_voltage',
    'house_battery_soc' => 'scarlet_signalk_electrical_batteries_0_capacity_stateOfCharge * 100',
    'house_battery_current' => 'scarlet_signalk_electrical_batteries_0_current',
    'house_battery_time_remaining' => 'scarlet_signalk_electrical_batteries_0_capacity_timeRemaining / 3600',
    'engine_battery_voltage' => 'scarlet_signalk_electrical_batteries_1_voltage',

    // Tanks
    'fuel_level' => 'scarlet_boat_fuel_tank_percent',
    'water_level' => 'scarlet_mqtt_percent{topic="watertank"}',

    // Cabin environment (Zigbee sensors via MQTT)
    'cabin_temp_quarterberth' => 'scarlet_mqtt_temperature{topic="zigbee2mqtt/Quarterberth"}',
    'cabin_humidity_quarterberth' => 'scarlet_mqtt_humidity{topic="zigbee2mqtt/Quarterberth"}',
    'cabin_temp_main' => 'scarlet_mqtt_temperature{topic="zigbee2mqtt/Main Cabin"}',
    'cabin_humidity_main' => 'scarlet_mqtt_humidity{topic="zigbee2mqtt/Main Cabin"}',
    'cabin_temp_forepeak' => 'scarlet_environment_temperature_celsius',
    'cabin_humidity_forepeak' => 'scarlet_environment_humidity_percent',
],
```

- [ ] **Step 2: Update the history mapping**

Replace the `'history'` array (lines 67–72) to add per-zone temperature, humidity, and house battery voltage from Signal K:

```php
'history' => [
    'battery' => 'scarlet_signalk_electrical_batteries_0_voltage',
    'speed' => 'scarlet_signalk_navigation_speedOverGround * 1.94384',
    'temp_forepeak' => 'scarlet_environment_temperature_celsius',
    'temp_quarterberth' => 'scarlet_mqtt_temperature{topic="zigbee2mqtt/Quarterberth"}',
    'temp_main_cabin' => 'scarlet_mqtt_temperature{topic="zigbee2mqtt/Main Cabin"}',
    'humidity_forepeak' => 'scarlet_environment_humidity_percent',
    'humidity_quarterberth' => 'scarlet_mqtt_humidity{topic="zigbee2mqtt/Quarterberth"}',
    'humidity_main_cabin' => 'scarlet_mqtt_humidity{topic="zigbee2mqtt/Main Cabin"}',
],
```

- [ ] **Step 3: Verify the PromQL expressions return sensible values**

Run from the project root:
```bash
# Should return ~0 (boat at rest) — value in knots
curl -s 'http://rapunzel.mintopia.net:9090/api/v1/query?query=scarlet_signalk_navigation_speedOverGround%20*%201.94384' | python3 -m json.tool | grep -A1 '"value"'

# Should return a heading in degrees (0–360)
curl -s 'http://rapunzel.mintopia.net:9090/api/v1/query?query=scarlet_signalk_navigation_headingMagnetic%20*%20180%20/%203.14159265359' | python3 -m json.tool | grep -A1 '"value"'

# Should return water temp in Celsius (~19°C)
curl -s 'http://rapunzel.mintopia.net:9090/api/v1/query?query=scarlet_signalk_environment_water_temperature%20-%20273.15' | python3 -m json.tool | grep -A1 '"value"'
```

- [ ] **Step 4: Commit**

```bash
git add config/scarlet.php
git commit -m "feat: expand boat metrics to Signal K sources with unit conversions"
```

---

### Task 2: Update admin controller to pass 3-zone history data

**Files:**
- Modify: `app/Http/Controllers/Admin/BoatMetricsController.php`

The admin view needs per-zone temperature and humidity history arrays for the charts.

- [ ] **Step 1: Update the controller to pass all history arrays**

Replace the full `index` method in `BoatMetricsController.php`:

```php
public function index(MetricsService $metrics, PrometheusService $prometheus)
{
    $history = config('scarlet.metrics.mappings.history');

    return Inertia::render('Admin/BoatMetrics', [
        'boat' => $metrics->getBoatMetrics(),
        'batteryHistory' => $prometheus->queryRange($history['battery'], '24h', '300s'),
        'speedHistory' => $prometheus->queryRange($history['speed'], '24h', '300s'),
        'tempHistoryForepeak' => $prometheus->queryRange($history['temp_forepeak'], '24h', '300s'),
        'tempHistoryQuarterberth' => $prometheus->queryRange($history['temp_quarterberth'], '24h', '300s'),
        'tempHistoryMainCabin' => $prometheus->queryRange($history['temp_main_cabin'], '24h', '300s'),
        'humidityHistoryForepeak' => $prometheus->queryRange($history['humidity_forepeak'], '24h', '300s'),
        'humidityHistoryQuarterberth' => $prometheus->queryRange($history['humidity_quarterberth'], '24h', '300s'),
        'humidityHistoryMainCabin' => $prometheus->queryRange($history['humidity_main_cabin'], '24h', '300s'),
    ]);
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Http/Controllers/Admin/BoatMetricsController.php
git commit -m "feat: pass 3-zone temp and humidity history to admin view"
```

---

### Task 3: Replace Under Sail/Power status with Underway

**Files:**
- Modify: `resources/js/composables/useScarletMetrics.js:29-46`
- Modify: `resources/js/components/ScarletLowerThird.vue:165-183`

- [ ] **Step 1: Update status logic in composable**

In `useScarletMetrics.js`, replace the `statusText` and `statusClass` computed properties (lines 29–46):

```javascript
const statusText = computed(() => {
    if (isOffline.value) return 'Offline';
    const sog = boat.value?.speed_sog;
    if ((sog == null || sog < 0.5) && portName) return 'In Port';
    return 'Underway';
});

const statusClass = computed(() => {
    const map = {
        'Offline': 'status-offline',
        'In Port': 'status-port',
        'Underway': 'status-underway',
    };
    return map[statusText.value] ?? 'status-underway';
});
```

- [ ] **Step 2: Update CSS classes in ScarletLowerThird.vue**

In `ScarletLowerThird.vue`, replace the four `.status-*` CSS rules (lines 165–183) with three:

```css
.status-underway {
    color: oklch(0.78 0.12 155);
    background: oklch(0.78 0.12 155 / 0.12);
}

.status-port {
    color: oklch(0.70 0.12 240);
    background: oklch(0.70 0.12 240 / 0.12);
}

.status-offline {
    color: oklch(0.65 0.06 55);
    background: oklch(0.65 0.06 55 / 0.12);
}
```

- [ ] **Step 3: Verify the overlay and dashboard render correctly**

Start the dev server (`npm run dev`) and check:
- Overlay at `/overlay` — status badge shows "In Port" or "Underway" (not "Under Sail" or "Under Power")
- Dashboard at `/` — same status in lower third

- [ ] **Step 4: Commit**

```bash
git add resources/js/composables/useScarletMetrics.js resources/js/components/ScarletLowerThird.vue
git commit -m "feat: replace Under Sail/Power status with Offline/In Port/Underway"
```

---

### Task 4: Add heel angle floating pill to overlay

**Files:**
- Modify: `resources/js/Pages/Public/Overlay.vue`

The dashboard already shows heel in its instrument pills. The overlay should show it as a single floating pill near the bottom-right corner.

- [ ] **Step 1: Add spring animation and computed for heel**

In the `<script setup>` section of `Overlay.vue`, after the `compassWind` computed (line 81), add:

```javascript
import { useSpringValue } from '../../composables/useSpringValue';

const animHeel = useSpringValue(() => Math.abs(boat.value?.heel ?? 0), { tension: 80, friction: 12 });

const heelSide = computed(() => {
    const heel = boat.value?.heel;
    if (heel == null) return '';
    return heel < 0 ? 'port' : 'starboard';
});

function fmtSpring(anim, raw, decimals = 1) {
    if (raw == null) return '--';
    return Number(anim).toFixed(decimals);
}
```

Note: `useSpringValue` is already imported in Dashboard.vue — check that the import path matches: `'../../composables/useSpringValue'`.

- [ ] **Step 2: Add the heel pill to the template**

In the template, after the `bl-cluster` div and before the offline card `<Transition>`, add:

```html
<!-- BOTTOM-RIGHT: Heel angle pill -->
<div class="heel-pill">
    <div class="heel-lbl">HEEL</div>
    <div class="heel-val">{{ fmtSpring(animHeel, boat?.heel, 0) }}°</div>
    <div class="heel-sub">{{ heelSide }}</div>
</div>
```

- [ ] **Step 3: Add the heel pill CSS**

In the `<style scoped>` section, add before the `/* ── State-dependent opacity */` comment:

```css
/* ── Heel pill ─────────────────────────── */
.heel-pill {
    position: absolute;
    bottom: 88px;
    right: 16px;
    z-index: 10;
    background: oklch(0.08 0.008 40 / 0.72);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    border-radius: 10px;
    border: 1.5px solid oklch(0.32 0.01 40 / 0.18);
    padding: 10px 16px;
    text-align: center;
    min-width: 72px;
    opacity: 0;
    visibility: hidden;
    transform: translateY(6px);
    transition: opacity 0.4s cubic-bezier(0.16, 1, 0.3, 1),
                visibility 0.4s,
                transform 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}

[data-state="video-live"] .heel-pill,
[data-state="no-video"] .heel-pill,
[data-state="port"] .heel-pill {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.heel-lbl {
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.04em;
    color: oklch(0.62 0.008 70);
    margin-bottom: 2px;
}

.heel-val {
    font-size: 21px;
    font-weight: 500;
    color: oklch(0.96 0.005 70);
    line-height: 1.1;
}

.heel-sub {
    font-size: 14px;
    color: oklch(0.62 0.008 70);
    margin-top: 2px;
}
```

- [ ] **Step 4: Hide heel pill when offline**

Add to the existing `[data-state="offline"]` rules:

```css
[data-state="offline"] .heel-pill { opacity: 0.5; visibility: visible; transform: translateY(0); }
```

And add to the `[data-state="loading"]` rules:

```css
[data-state="loading"] .heel-pill {
    opacity: 0;
    transform: translateY(6px);
}
```

- [ ] **Step 5: Verify on the overlay**

Check `/overlay` — the heel pill should appear bottom-right showing the current heel angle with port/starboard indicator. It should fade in/out with other chrome based on overlay state.

- [ ] **Step 6: Commit**

```bash
git add resources/js/Pages/Public/Overlay.vue
git commit -m "feat: add heel angle floating pill to stream overlay"
```

---

### Task 5: Rework admin — simplify power to batteries, replace engine with environment

**Files:**
- Modify: `resources/js/Pages/Admin/BoatMetrics.vue`

This is the largest task. We're making three changes to the admin view:
1. **Power Balance** → simplified to house + engine battery (no solar/load bars)
2. **Engine panel** → replaced with 3-zone Environment panel
3. **Temperature chart** → 3 lines (Forepeak, Quarterberth, Main Cabin)
4. **Add humidity chart** → 3 lines matching temperature zones
5. **Tank levels** → wired to MQTT data (already using correct field names)

- [ ] **Step 1: Update props to accept new history arrays**

Replace the `defineProps` in the `<script setup>` block (lines 472–478):

```javascript
const props = defineProps({
    boat: Object,
    batteryHistory: Array,
    speedHistory: Array,
    tempHistoryForepeak: Array,
    tempHistoryQuarterberth: Array,
    tempHistoryMainCabin: Array,
    humidityHistoryForepeak: Array,
    humidityHistoryQuarterberth: Array,
    humidityHistoryMainCabin: Array,
});
```

- [ ] **Step 2: Update temperature chart computed values**

Replace the `tempMin` and `tempMax` computed properties (lines 537–544) to account for all 3 zones:

```javascript
const allTempValues = computed(() => {
    const fp = (props.tempHistoryForepeak ?? []).map(d => d.value);
    const qb = (props.tempHistoryQuarterberth ?? []).map(d => d.value);
    const mc = (props.tempHistoryMainCabin ?? []).map(d => d.value);
    return [...fp, ...qb, ...mc];
});

const tempMin = computed(() => {
    const vals = allTempValues.value;
    return vals.length ? Math.min(...vals) - 2 : 0;
});
const tempMax = computed(() => {
    const vals = allTempValues.value;
    return vals.length ? Math.max(...vals) + 2 : 40;
});

const hasTempData = computed(() => allTempValues.value.length > 0);
```

- [ ] **Step 3: Add humidity chart computed values**

Add below the temperature computed values:

```javascript
const allHumidityValues = computed(() => {
    const fp = (props.humidityHistoryForepeak ?? []).map(d => d.value);
    const qb = (props.humidityHistoryQuarterberth ?? []).map(d => d.value);
    const mc = (props.humidityHistoryMainCabin ?? []).map(d => d.value);
    return [...fp, ...qb, ...mc];
});

const humidityMin = computed(() => {
    const vals = allHumidityValues.value;
    return vals.length ? Math.min(...vals) - 5 : 0;
});
const humidityMax = computed(() => {
    const vals = allHumidityValues.value;
    return vals.length ? Math.max(...vals) + 5 : 100;
});

const hasHumidityData = computed(() => allHumidityValues.value.length > 0);
```

- [ ] **Step 4: Remove solar/load/engine computed values**

Delete these computed properties (they're no longer needed):
- `solarWatts` (line 517)
- `loadWatts` (lines 518–522)
- `isCharging` (lines 524–528)
- `powerBarWidth` function (lines 531–534)
- `engineRunning` (lines 511–514)

- [ ] **Step 5: Replace Power Balance section in template**

Replace the entire Power Balance section (lines 323–383) with a simplified batteries panel:

```html
<!-- 8. Batteries -->
<div class="bg-surface border border-border rounded-[10px] p-4 mb-4">
    <div class="text-[15px] font-semibold mb-4">Batteries</div>
    <div class="grid grid-cols-2 gap-6">
        <!-- House Battery -->
        <div class="space-y-2.5 text-[13px]">
            <div class="text-[12px] font-semibold text-text-dim uppercase tracking-wide mb-3">House Battery</div>
            <div class="flex justify-between">
                <span class="text-text-secondary">Voltage</span>
                <span class="tabular-nums font-semibold">{{ live?.house_battery_voltage != null ? Number(live.house_battery_voltage).toFixed(2) + ' V' : '—' }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-text-secondary">State of Charge</span>
                <span class="tabular-nums font-semibold text-green">{{ live?.house_battery_soc != null ? Number(live.house_battery_soc).toFixed(0) + '%' : '—' }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-text-secondary">Current</span>
                <span class="tabular-nums font-semibold" :class="live?.house_battery_current != null && live.house_battery_current > 0 ? 'text-green' : 'text-amber'">{{ live?.house_battery_current != null ? Number(live.house_battery_current).toFixed(1) + ' A' : '—' }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-text-secondary">Time Remaining</span>
                <span class="tabular-nums font-semibold">{{ live?.house_battery_time_remaining != null ? Number(live.house_battery_time_remaining).toFixed(0) + ' h' : '—' }}</span>
            </div>
        </div>
        <!-- Engine Battery -->
        <div class="space-y-2.5 text-[13px]">
            <div class="text-[12px] font-semibold text-text-dim uppercase tracking-wide mb-3">Engine Battery</div>
            <div class="flex justify-between">
                <span class="text-text-secondary">Voltage</span>
                <span class="tabular-nums font-semibold">{{ live?.engine_battery_voltage != null ? Number(live.engine_battery_voltage).toFixed(2) + ' V' : '—' }}</span>
            </div>
        </div>
    </div>
</div>
```

- [ ] **Step 6: Replace Temperature chart with 3-zone version**

Replace the Temperature chart section (lines 105–140) with:

```html
<!-- Temperature (3 zones) -->
<div class="bg-surface border border-border rounded-[10px] p-4">
    <div class="text-[15px] font-semibold mb-0.5">Cabin Temperature</div>
    <div class="text-[12px] text-text-dim mb-3 tabular-nums flex gap-4">
        <span><span class="font-medium" style="color: oklch(0.70 0.14 70)">&#9679;</span> Forepeak {{ live?.cabin_temp_forepeak != null ? Number(live.cabin_temp_forepeak).toFixed(1) + '°C' : '—' }}</span>
        <span><span class="font-medium" style="color: oklch(0.60 0.16 240)">&#9679;</span> Quarterberth {{ live?.cabin_temp_quarterberth != null ? Number(live.cabin_temp_quarterberth).toFixed(1) + '°C' : '—' }}</span>
        <span><span class="font-medium" style="color: oklch(0.65 0.18 330)">&#9679;</span> Main Cabin {{ live?.cabin_temp_main != null ? Number(live.cabin_temp_main).toFixed(1) + '°C' : '—' }}</span>
    </div>
    <svg viewBox="0 0 400 120" class="w-full" preserveAspectRatio="none">
        <defs>
            <linearGradient id="tempGradFp" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="oklch(0.70 0.14 70)" stop-opacity="0.10"/>
                <stop offset="100%" stop-color="oklch(0.70 0.14 70)" stop-opacity="0.01"/>
            </linearGradient>
        </defs>
        <polygon v-if="tempHistoryForepeak?.length" :points="toAreaPolygon(tempHistoryForepeak, 400, 120, tempMin, tempMax)" fill="url(#tempGradFp)"/>
        <polyline v-if="tempHistoryForepeak?.length" :points="toPolyline(tempHistoryForepeak, 400, 120, tempMin, tempMax)" fill="none" stroke="oklch(0.70 0.14 70)" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round"/>
        <polyline v-if="tempHistoryQuarterberth?.length" :points="toPolyline(tempHistoryQuarterberth, 400, 120, tempMin, tempMax)" fill="none" stroke="oklch(0.60 0.16 240)" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round"/>
        <polyline v-if="tempHistoryMainCabin?.length" :points="toPolyline(tempHistoryMainCabin, 400, 120, tempMin, tempMax)" fill="none" stroke="oklch(0.65 0.18 330)" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round"/>
        <text v-if="!hasTempData" x="200" y="65" text-anchor="middle" font-size="12" fill="oklch(0.70 0.005 40)">No data</text>
    </svg>
    <div class="flex justify-between text-[10px] text-text-dim mt-1">
        <span>24h ago</span><span>now</span>
    </div>
</div>
```

- [ ] **Step 7: Replace Battery Voltage chart**

Replace the Battery Voltage chart section (lines 142–178) with a Humidity chart:

```html
<!-- Humidity (3 zones) -->
<div class="bg-surface border border-border rounded-[10px] p-4">
    <div class="text-[15px] font-semibold mb-0.5">Cabin Humidity</div>
    <div class="text-[12px] text-text-dim mb-3 tabular-nums flex gap-4">
        <span><span class="font-medium" style="color: oklch(0.70 0.14 70)">&#9679;</span> Forepeak {{ live?.cabin_humidity_forepeak != null ? Number(live.cabin_humidity_forepeak).toFixed(0) + '%' : '—' }}</span>
        <span><span class="font-medium" style="color: oklch(0.60 0.16 240)">&#9679;</span> Quarterberth {{ live?.cabin_humidity_quarterberth != null ? Number(live.cabin_humidity_quarterberth).toFixed(0) + '%' : '—' }}</span>
        <span><span class="font-medium" style="color: oklch(0.65 0.18 330)">&#9679;</span> Main Cabin {{ live?.cabin_humidity_main != null ? Number(live.cabin_humidity_main).toFixed(0) + '%' : '—' }}</span>
    </div>
    <svg viewBox="0 0 400 120" class="w-full" preserveAspectRatio="none">
        <defs>
            <linearGradient id="humidGradFp" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="oklch(0.70 0.14 70)" stop-opacity="0.10"/>
                <stop offset="100%" stop-color="oklch(0.70 0.14 70)" stop-opacity="0.01"/>
            </linearGradient>
        </defs>
        <polygon v-if="humidityHistoryForepeak?.length" :points="toAreaPolygon(humidityHistoryForepeak, 400, 120, humidityMin, humidityMax)" fill="url(#humidGradFp)"/>
        <polyline v-if="humidityHistoryForepeak?.length" :points="toPolyline(humidityHistoryForepeak, 400, 120, humidityMin, humidityMax)" fill="none" stroke="oklch(0.70 0.14 70)" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round"/>
        <polyline v-if="humidityHistoryQuarterberth?.length" :points="toPolyline(humidityHistoryQuarterberth, 400, 120, humidityMin, humidityMax)" fill="none" stroke="oklch(0.60 0.16 240)" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round"/>
        <polyline v-if="humidityHistoryMainCabin?.length" :points="toPolyline(humidityHistoryMainCabin, 400, 120, humidityMin, humidityMax)" fill="none" stroke="oklch(0.65 0.18 330)" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round"/>
        <text v-if="!hasHumidityData" x="200" y="65" text-anchor="middle" font-size="12" fill="oklch(0.70 0.005 40)">No data</text>
    </svg>
    <div class="flex justify-between text-[10px] text-text-dim mt-1">
        <span>24h ago</span><span>now</span>
    </div>
</div>
```

- [ ] **Step 8: Replace Engine panel with Environment panel**

Replace the Engine panel section (lines 424–462) with a 3-zone environment panel:

```html
<!-- Environment (3 zones) -->
<div class="bg-surface border border-border rounded-[10px] p-4">
    <div class="text-[15px] font-semibold mb-4">Environment</div>
    <div class="space-y-4">
        <!-- Forepeak -->
        <div>
            <div class="text-[12px] font-semibold text-text-dim uppercase tracking-wide mb-2">Forepeak</div>
            <div class="grid grid-cols-2 gap-4 text-[13px]">
                <div class="flex justify-between">
                    <span class="text-text-secondary">Temperature</span>
                    <span class="tabular-nums font-semibold" style="color: oklch(0.70 0.14 70)">{{ live?.cabin_temp_forepeak != null ? Number(live.cabin_temp_forepeak).toFixed(1) + '°C' : '—' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-text-secondary">Humidity</span>
                    <span class="tabular-nums font-semibold" style="color: oklch(0.70 0.14 70)">{{ live?.cabin_humidity_forepeak != null ? Number(live.cabin_humidity_forepeak).toFixed(0) + '%' : '—' }}</span>
                </div>
            </div>
        </div>
        <div class="border-t border-border"></div>
        <!-- Quarterberth -->
        <div>
            <div class="text-[12px] font-semibold text-text-dim uppercase tracking-wide mb-2">Quarterberth</div>
            <div class="grid grid-cols-2 gap-4 text-[13px]">
                <div class="flex justify-between">
                    <span class="text-text-secondary">Temperature</span>
                    <span class="tabular-nums font-semibold" style="color: oklch(0.60 0.16 240)">{{ live?.cabin_temp_quarterberth != null ? Number(live.cabin_temp_quarterberth).toFixed(1) + '°C' : '—' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-text-secondary">Humidity</span>
                    <span class="tabular-nums font-semibold" style="color: oklch(0.60 0.16 240)">{{ live?.cabin_humidity_quarterberth != null ? Number(live.cabin_humidity_quarterberth).toFixed(0) + '%' : '—' }}</span>
                </div>
            </div>
        </div>
        <div class="border-t border-border"></div>
        <!-- Main Cabin -->
        <div>
            <div class="text-[12px] font-semibold text-text-dim uppercase tracking-wide mb-2">Main Cabin</div>
            <div class="grid grid-cols-2 gap-4 text-[13px]">
                <div class="flex justify-between">
                    <span class="text-text-secondary">Temperature</span>
                    <span class="tabular-nums font-semibold" style="color: oklch(0.65 0.18 330)">{{ live?.cabin_temp_main != null ? Number(live.cabin_temp_main).toFixed(1) + '°C' : '—' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-text-secondary">Humidity</span>
                    <span class="tabular-nums font-semibold" style="color: oklch(0.65 0.18 330)">{{ live?.cabin_humidity_main != null ? Number(live.cabin_humidity_main).toFixed(0) + '%' : '—' }}</span>
                </div>
            </div>
        </div>
    </div>
</div>
```

- [ ] **Step 9: Add Battery Voltage history chart**

Below the humidity chart (still in the grid-cols-2 section with temp chart), we should add the battery voltage chart back. It was previously in the same row as temperature. Replace the charts grid structure to have temperature + humidity on one row, then battery + speed on the next row. The full charts section becomes:

```html
<!-- 3 & 4. Temperature + Humidity charts -->
<div class="grid grid-cols-2 gap-4 mb-4">
    <!-- Temperature chart (as defined in Step 6) -->
    ...
    <!-- Humidity chart (as defined in Step 7) -->
    ...
</div>

<!-- 5 & 6. Battery Voltage + Speed charts -->
<div class="grid grid-cols-2 gap-4 mb-4">
    <!-- Battery Voltage -->
    <div class="bg-surface border border-border rounded-[10px] p-4">
        <div class="text-[15px] font-semibold mb-0.5">Battery Voltage</div>
        <div class="text-[12px] text-text-dim mb-3 tabular-nums">
            <span class="text-green font-medium">{{ batteryVal }}</span>
            &nbsp;current
        </div>
        <svg viewBox="0 0 400 120" class="w-full" preserveAspectRatio="none">
            <defs>
                <linearGradient id="batteryGrad" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="oklch(0.62 0.15 155)" stop-opacity="0.20"/>
                    <stop offset="100%" stop-color="oklch(0.62 0.15 155)" stop-opacity="0.02"/>
                </linearGradient>
            </defs>
            <polygon v-if="props.batteryHistory?.length" :points="toAreaPolygon(props.batteryHistory, 400, 120, batteryMin, batteryMax)" fill="url(#batteryGrad)"/>
            <polyline v-if="props.batteryHistory?.length" :points="toPolyline(props.batteryHistory, 400, 120, batteryMin, batteryMax)" fill="none" stroke="oklch(0.62 0.15 155)" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round"/>
            <text v-if="!props.batteryHistory?.length" x="200" y="65" text-anchor="middle" font-size="12" fill="oklch(0.70 0.005 40)">No data</text>
        </svg>
        <div class="flex justify-between text-[10px] text-text-dim mt-1">
            <span>24h ago</span><span>now</span>
        </div>
    </div>

    <!-- Speed -->
    <div class="bg-surface border border-border rounded-[10px] p-4">
        <div class="text-[15px] font-semibold mb-0.5">Speed</div>
        <div class="text-[12px] text-text-dim mb-3 tabular-nums">
            <span class="text-scarlet font-medium">
                {{ live?.speed_sog != null ? live.speed_sog.toFixed(1) + ' kn' : '—' }}
            </span>
            &nbsp;current
        </div>
        <svg viewBox="0 0 400 120" class="w-full" preserveAspectRatio="none">
            <defs>
                <linearGradient id="speedGrad" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="oklch(0.54 0.22 27)" stop-opacity="0.20"/>
                    <stop offset="100%" stop-color="oklch(0.54 0.22 27)" stop-opacity="0.02"/>
                </linearGradient>
            </defs>
            <polygon v-if="props.speedHistory?.length" :points="toAreaPolygon(props.speedHistory, 400, 120, 0, speedMax)" fill="url(#speedGrad)"/>
            <polyline v-if="props.speedHistory?.length" :points="toPolyline(props.speedHistory, 400, 120, 0, speedMax)" fill="none" stroke="oklch(0.54 0.22 27)" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round"/>
            <text v-if="!props.speedHistory?.length" x="200" y="65" text-anchor="middle" font-size="12" fill="oklch(0.70 0.005 40)">No data</text>
        </svg>
        <div class="flex justify-between text-[10px] text-text-dim mt-1">
            <span>24h ago</span><span>now</span>
        </div>
    </div>
</div>
```

- [ ] **Step 10: Update the critical metrics strip Battery card**

The Battery card in the critical metrics strip (line 64–87) currently references `batterySparkline` which uses `batteryHistory`. This should continue to work as-is since `batteryHistory` is still passed from the controller. No changes needed.

- [ ] **Step 11: Verify the admin view renders correctly**

Navigate to the admin boat metrics page. Verify:
- Critical metrics strip shows Speed, Depth, Wind, Battery, Heading
- Temperature chart shows 3 coloured lines with legend
- Humidity chart shows 3 coloured lines with legend
- Battery voltage chart shows single green line
- Speed chart shows single scarlet line
- Tank levels show fuel and water percentages
- Batteries panel shows house (voltage, SoC, current, time remaining) and engine (voltage)
- Wind and Navigation compass roses display data
- Environment panel shows 3 zones with temp and humidity
- No Engine panel visible

- [ ] **Step 12: Commit**

```bash
git add resources/js/Pages/Admin/BoatMetrics.vue
git commit -m "feat: rework admin view with 3-zone environment, batteries, humidity chart"
```

---

### Task 6: Remove pressure pill from dashboard (no data source)

**Files:**
- Modify: `resources/js/Pages/Public/Dashboard.vue:162-164`

There is no barometric pressure metric in Prometheus. The pressure pill always shows dashes. Remove it.

- [ ] **Step 1: Remove the pressure pill from the instruments section**

In `Dashboard.vue`, remove the pressure pill (lines 160–164):

```html
<!-- DELETE THIS BLOCK -->
<div class="pill">
    <div class="pill-lbl">PRESSURE</div>
    <div class="pill-val">{{ fmtSpring(animPressure, boat?.pressure, 0) }}</div>
    <div class="pill-sub">hPa</div>
</div>
```

Also remove the `animPressure` spring (line 75):
```javascript
// DELETE THIS LINE
const animPressure = useSpringValue(() => boat.value?.pressure, { tension: 60, friction: 10 });
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/Pages/Public/Dashboard.vue
git commit -m "fix: remove pressure pill from dashboard (no data source)"
```

---

## Full layout reference (admin view after changes)

```
┌─────────────────────────────────────────────────────────┐
│  Speed │ Depth │  Wind  │ Battery │ Heading             │  ← critical strip
├────────────────────────┬────────────────────────────────┤
│  Cabin Temperature     │  Cabin Humidity                │  ← 3-zone charts
│  (3 lines + legend)    │  (3 lines + legend)            │
├────────────────────────┴────────────────────────────────┤
│  Tank Levels: Fuel  ████░░░░  │  Water  ████████░░░░    │
├────────────────────────┬────────────────────────────────┤
│  Battery Voltage       │  Speed                         │  ← 24h charts
│  (single line chart)   │  (single line chart)           │
├────────────────────────┴────────────────────────────────┤
│  Batteries                                              │
│  House: V, SoC, Current, Time │  Engine: V              │
├────────────────────────┬────────────────────────────────┤
│  Wind Compass          │  Navigation Compass            │
│  True dir/speed,       │  COG, SOG, STW,                │
│  Apparent speed/angle  │  Heel, Trip Log                │
├────────────────────────┴────────────────────────────────┤
│  Environment                                            │
│  Forepeak:     Temp / Humidity                          │
│  Quarterberth: Temp / Humidity                          │
│  Main Cabin:   Temp / Humidity                          │
└─────────────────────────────────────────────────────────┘
```
