# Admin Restructure Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Restructure admin navigation into audience-grouped sidebar, build three new/redesigned dashboard pages with instrument visualizations, and update the design token system.

**Architecture:** Update existing Tailwind 4 theme tokens and AdminLayout sidebar, then build reusable SVG instrument components (CompassRose, WindDial, Sparkline, LevelBar), then compose them into three page redesigns (Dashboard, SkipperOverview, Broadcast). Data flows through existing MetricsService + PrometheusService + useScarletMetrics composable.

**Tech Stack:** Laravel 12, Vue 3 (Composition API), Inertia.js, Tailwind CSS 4, Leaflet, SVG, Laravel Reverb (WebSocket)

**Spec:** `docs/superpowers/specs/2026-05-24-admin-restructure-design.md`
**Design System:** `DESIGN.md`

---

### Task 1: Update CSS Tokens and Add Nunito Sans

**Files:**
- Modify: `resources/css/app.css`

- [ ] **Step 1: Update the @theme block with new design tokens**

Replace the existing `@theme` block in `resources/css/app.css` with the tokens from DESIGN.md. The key changes: Outfit font replaced with Nunito Sans, hue shifted from 70 (warm) to 205 (cool maritime), new semantic color variables added.

```css
@theme {
    --font-sans: 'Nunito Sans', ui-sans-serif, system-ui, sans-serif;
    --font-body: 'DM Sans', ui-sans-serif, system-ui, sans-serif;

    --color-scarlet: oklch(0.48 0.22 25);
    --color-scarlet-hover: oklch(0.42 0.22 25);
    --color-scarlet-light: oklch(0.48 0.22 25 / 0.06);
    --color-surface: oklch(0.99 0.004 205);
    --color-bg: oklch(0.96 0.01 205);
    --color-border: oklch(0.89 0.012 205);
    --color-border-light: oklch(0.93 0.006 205);
    --color-text-primary: oklch(0.15 0.025 205);
    --color-text-secondary: oklch(0.38 0.02 205);
    --color-text-dim: oklch(0.52 0.014 205);
    --color-green: oklch(0.45 0.16 150);
    --color-green-bg: oklch(0.45 0.16 150 / 0.08);
    --color-amber: oklch(0.48 0.17 70);
    --color-amber-bg: oklch(0.48 0.17 70 / 0.08);
    --color-error: oklch(0.48 0.22 25);
    --color-error-bg: oklch(0.48 0.22 25 / 0.08);
    --color-blue: oklch(0.42 0.14 245);
    --color-blue-bg: oklch(0.42 0.14 245 / 0.08);
    --color-teal: oklch(0.42 0.14 178);
    --color-teal-bg: oklch(0.42 0.14 178 / 0.08);
    --color-pink: oklch(0.65 0.18 330);

    --color-surface: oklch(0.99 0.004 205);
    --shadow-sm: 0 1px 5px oklch(0.2 0.02 205 / 0.08);
}
```

- [ ] **Step 2: Update dark mode tokens**

Update the `html[data-theme="dark"]` block to use hue 205 neutrals:

```css
html[data-theme="dark"] {
    --color-surface: oklch(0.19 0.014 205);
    --color-bg: oklch(0.14 0.018 205);
    --color-border: oklch(0.27 0.014 205);
    --color-border-light: oklch(0.24 0.012 205);
    --color-text-primary: oklch(0.92 0.008 205);
    --color-text-secondary: oklch(0.7 0.012 205);
    --color-text-dim: oklch(0.5 0.01 205);
    --color-scarlet: oklch(0.62 0.2 25);
    --color-scarlet-hover: oklch(0.56 0.2 25);
    --color-scarlet-light: oklch(0.62 0.2 25 / 0.12);
    --color-green: oklch(0.65 0.14 150);
    --color-green-bg: oklch(0.65 0.14 150 / 0.15);
    --color-amber: oklch(0.75 0.15 70);
    --color-amber-bg: oklch(0.75 0.15 70 / 0.15);
    --color-error: oklch(0.62 0.2 25);
    --color-error-bg: oklch(0.62 0.2 25 / 0.15);
    --color-blue: oklch(0.62 0.1 245);
    --color-blue-bg: oklch(0.62 0.1 245 / 0.15);
    --color-teal: oklch(0.65 0.12 178);
    --color-teal-bg: oklch(0.65 0.12 178 / 0.15);
    --color-pink: oklch(0.68 0.18 330);
    color-scheme: dark;
}
```

- [ ] **Step 3: Update night watch tokens**

Update the `html[data-theme="night"]` block to use hue 15/25 red-shifted values matching the existing pattern but with the updated base tokens.

- [ ] **Step 4: Update the `.panel` class to match DESIGN.md**

```css
.panel {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: 16px;
    box-shadow: var(--shadow-sm);
    overflow: hidden;
    display: flex;
    flex-direction: column;
}
```

- [ ] **Step 5: Add Nunito Sans font import**

Add to the top of `resources/css/app.css`, before the `@import 'tailwindcss'` line:

```css
@import url('https://fonts.googleapis.com/css2?family=Nunito+Sans:opsz,wght@6..12,300;6..12,400;6..12,500;6..12,600;6..12,700;6..12,800&family=DM+Sans:opsz,wght@9..40,400;9..40,600;9..40,700;9..40,800&display=swap');
```

- [ ] **Step 6: Verify the app compiles**

Run: `npm run build`
Expected: Build succeeds with no errors.

- [ ] **Step 7: Commit**

```bash
git add resources/css/app.css
git commit -m "feat: update design tokens to new admin design system with Nunito Sans"
```

---

### Task 2: Restructure Sidebar Navigation

**Files:**
- Modify: `resources/js/Layouts/AdminLayout.vue`

- [ ] **Step 1: Update the sidebar navigation sections**

Replace the current flat nav list in AdminLayout.vue with audience-grouped sections. The existing `NavLink` component is reused. The sidebar currently has two sections: "Boat" and "Links". Replace with four sections: "Skipper", "Broadcast", "System", "Tools". Dashboard sits above all sections.

In the sidebar template section, replace the nav items with:

```vue
<!-- Dashboard (top-level) -->
<NavLink href="/admin" :icon="'home'" :active="currentPage === 'Admin/Dashboard'">
    Dashboard
</NavLink>

<div class="nav-label">Skipper</div>
<NavLink href="/admin/skipper" :icon="'compass'" :active="currentPage === 'Admin/SkipperOverview'">
    Skipper Overview
</NavLink>
<NavLink href="/admin/journeys" :icon="'compass'" :active="currentPage?.startsWith('Admin/Journey')">
    Journeys
</NavLink>
<NavLink href="/admin/weather" :icon="'cloud'" :active="currentPage === 'Admin/Weather'">
    Weather
</NavLink>
<NavLink href="/admin/log" :icon="'clipboard'" :active="currentPage === 'Admin/Log'">
    Ship's Log
</NavLink>

<div class="nav-label">Broadcast</div>
<NavLink href="/admin/broadcast" :icon="'radio'" :active="currentPage === 'Admin/Broadcast'">
    Broadcast
</NavLink>
<a href="/overlay" target="_blank" class="nav-external">
    Overlay <span class="nav-arrow">↗</span>
</a>
<a href="/dashboard" target="_blank" class="nav-external">
    Public Dashboard <span class="nav-arrow">↗</span>
</a>

<div class="nav-label">System</div>
<NavLink href="/admin/tracker" :icon="'activity'" :active="currentPage === 'Admin/Tracker'">
    Tracker
</NavLink>
<NavLink href="/admin/settings" :icon="'settings'" :active="currentPage === 'Admin/Settings'">
    Settings
</NavLink>
<NavLink href="/admin/team" :icon="'users'" :active="currentPage === 'Admin/Team'">
    Team
</NavLink>

<div class="nav-label">Tools</div>
<NavLink href="/admin/explore" :icon="'search'" :active="currentPage === 'Admin/Explore' || currentPage === 'Admin/ExploreDashboard'">
    Explore
</NavLink>
```

- [ ] **Step 2: Add styles for external nav links**

Add CSS for the external links (Overlay, Public Dashboard) that open in new tabs:

```css
.nav-external {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 7px 16px;
    font-size: 13px;
    color: var(--color-text-dim);
    text-decoration: none;
    border-radius: 6px;
    transition: color 0.12s;
}
.nav-external:hover { color: var(--color-text-primary); }
.nav-arrow { font-size: 10px; opacity: 0.4; }
```

- [ ] **Step 3: Update breadcrumb map**

Update the `breadcrumbMap` computed property to include the new pages and the renamed Log:

```javascript
const breadcrumbMap = {
    'Admin/Dashboard': 'Dashboard',
    'Admin/SkipperOverview': 'Skipper Overview',
    'Admin/Settings': 'Settings',
    'Admin/Journeys': 'Journeys',
    'Admin/JourneyCreate': 'Create Journey',
    'Admin/JourneyEdit': 'Edit Journey',
    'Admin/JourneyImport': 'Import Journey',
    'Admin/Log': "Ship's Log",
    'Admin/Tracker': 'Tracker',
    'Admin/BoatMetrics': 'Boat Metrics',
    'Admin/Weather': 'Weather',
    'Admin/Explore': 'Explore',
    'Admin/ExploreDashboard': 'Explore',
    'Admin/Broadcast': 'Broadcast',
    'Admin/Profile': 'Profile',
    'Admin/Team': 'Team',
};
```

- [ ] **Step 4: Update the sidebar font to use the new design system fonts**

In the sidebar's scoped styles, update font references from Outfit to the new font stack. The sidebar labels and text should use DM Sans (body font):

```css
.sidebar { font-family: var(--font-body, 'DM Sans', system-ui, sans-serif); }
```

- [ ] **Step 5: Verify the layout renders**

Run `npm run dev`, navigate to `/admin`. The sidebar should show the new grouped navigation. All existing pages should still work via their existing routes.

- [ ] **Step 6: Commit**

```bash
git add resources/js/Layouts/AdminLayout.vue
git commit -m "feat: restructure admin sidebar into audience-grouped navigation"
```

---

### Task 3: Build Shared SVG Components

**Files:**
- Create: `resources/js/Components/Admin/CompassRose.vue`
- Create: `resources/js/Components/Admin/WindDial.vue`
- Create: `resources/js/Components/Admin/Sparkline.vue`
- Create: `resources/js/Components/Admin/LevelBar.vue`

- [ ] **Step 1: Build CompassRose.vue**

Create `resources/js/Components/Admin/CompassRose.vue`. This is a pure SVG component that accepts heading (magnetic) and COG (true) as props and renders the compass rose with tick marks, cardinal labels, heading arrow, and COG dashed line.

Props:
- `heading` (Number, default: 0) - magnetic heading in degrees
- `cog` (Number, default: null) - course over ground in degrees (true)
- `size` (Number, default: 180) - SVG width/height in pixels

The compass renders at a fixed viewBox of `0 0 200 200`. The heading arrow and COG line rotate based on the prop values. Use CSS custom properties for theming (teal for arrows, scarlet for N label). Implement using the same visual design as skipper-v8.html mockup in `.superpowers/brainstorm/`.

Reference the mockup file for exact SVG coordinates: `.superpowers/brainstorm/281757-1779606123/content/skipper-v8.html` (the heading compass SVG starting around line 250).

- [ ] **Step 2: Build WindDial.vue**

Create `resources/js/Components/Admin/WindDial.vue`. SVG component showing true wind angle with speed in center.

Props:
- `tws` (Number, default: 0) - true wind speed in knots
- `twa` (Number, default: 0) - true wind angle in degrees (negative = port, positive = starboard)
- `size` (Number, default: 190) - SVG width/height

Features:
- Point of sail zones as colored wedges (no-go scarlet, close hauled/reach teal, beam/broad reach amber, running green-amber) at 0.07-0.14 opacity
- TWA arrow with animated dash and feather marks
- Center circle with TWS number and "kts" label
- Boat silhouette at top
- Port/Stbd labels

Computed:
- `pointOfSail` - derived from absolute TWA: "In Irons" (0-45), "Close Hauled" (45-60), "Close Reach" (60-80), "Beam Reach" (80-100), "Broad Reach" (100-150), "Running" (150-170), "Dead Run" (170-180)
- `beaufort` - lookup from TWS to Beaufort number (F0-F12)
- `isPort` - TWA < 0

Reference: `.superpowers/brainstorm/281757-1779606123/content/skipper-v8.html` (the wind dial SVG).

- [ ] **Step 3: Build Sparkline.vue**

Create `resources/js/Components/Admin/Sparkline.vue`. Reusable sparkline chart.

Props:
- `data` (Array, required) - array of numbers
- `color` (String, default: 'var(--color-teal)') - stroke color
- `height` (Number, default: 36) - SVG height in px
- `fill` (Boolean, default: true) - show fill area below line
- `showDot` (Boolean, default: true) - show current value dot at end
- `zeroLine` (Boolean, default: false) - show dashed zero line (for power charts)

The component computes the SVG path from the data array, scaling Y to fit the height. If `zeroLine` is true, values above zero get one color and below get another (for charge/discharge).

- [ ] **Step 4: Build LevelBar.vue**

Create `resources/js/Components/Admin/LevelBar.vue`. Horizontal level bar.

Props:
- `value` (Number, default: 0) - percentage 0-100
- `label` (String, required) - "Battery", "Fuel", "Water"
- `color` (String, default: 'green') - 'green', 'amber', 'blue'

Template renders: label (56px) | track with fill | value text. Uses gradient fills matching DESIGN.md.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Components/Admin/
git commit -m "feat: add shared SVG instrument components (CompassRose, WindDial, Sparkline, LevelBar)"
```

---

### Task 4: Build SkipperOverview Controller and Route

**Files:**
- Create: `app/Http/Controllers/Admin/SkipperOverviewController.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Create SkipperOverviewController**

Create `app/Http/Controllers/Admin/SkipperOverviewController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\MetricsService;
use App\Services\PrometheusService;
use Inertia\Inertia;

class SkipperOverviewController extends Controller
{
    public function index(MetricsService $metrics, PrometheusService $prometheus)
    {
        $boat = $metrics->getBoatMetrics();
        $gps = $metrics->getGpsMetrics();
        $weather = $metrics->getWeatherData();

        $depthHistory = $prometheus->queryRange(
            'scarlet_boat_depth_meters',
            '1h',
            '60s'
        );

        $powerHistory = $prometheus->queryRange(
            'scarlet_signalk_electrical_batteries_0_voltage * scarlet_signalk_electrical_batteries_0_current',
            '1h',
            '60s'
        );

        $pressureHistory = $prometheus->queryRange(
            'scarlet_weather_pressure_hpa',
            '24h',
            '15m'
        );

        $speedHistory = $prometheus->queryRange(
            config('scarlet.metrics.mappings.boat.speed_sog'),
            '1h',
            '60s'
        );

        return Inertia::render('Admin/SkipperOverview', [
            'boat' => $boat,
            'gps' => $gps,
            'weather' => $weather,
            'depthHistory' => $depthHistory,
            'powerHistory' => $powerHistory,
            'pressureHistory' => $pressureHistory,
            'speedHistory' => $speedHistory,
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
```

- [ ] **Step 2: Add route**

In `routes/web.php`, add inside the admin middleware group:

```php
Route::get('skipper', [App\Http\Controllers\Admin\SkipperOverviewController::class, 'index'])->name('admin.skipper');
```

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/Admin/SkipperOverviewController.php routes/web.php
git commit -m "feat: add SkipperOverview controller and route"
```

---

### Task 5: Build SkipperOverview.vue Page

**Files:**
- Create: `resources/js/Pages/Admin/SkipperOverview.vue`

- [ ] **Step 1: Create the SkipperOverview page component**

Create `resources/js/Pages/Admin/SkipperOverview.vue`. This is a large component. It uses the AdminLayout, receives props from the controller, and composes the shared components.

Structure:
- Page header with title + status badge (using `useScarletMetrics` for boat status)
- Primary row (3 equal panels): Heading & Course (CompassRose + HDG/COG readings + position), Speed (hero SOG + sparkline + nav data), True Wind (WindDial + point of sail + Beaufort)
- Secondary row (3 equal panels): Systems (LevelBar x3 + voltages), Depth & Power (Sparkline x2), Weather (hero strip + data rows + pressure sparkline)

Props from controller: `boat`, `gps`, `weather`, `depthHistory`, `powerHistory`, `pressureHistory`, `speedHistory`, `timestamp`

Use `useFormatters` for number formatting. Use `useScarletMetrics` for real-time updates and status derivation. All numeric values use the `font-sans` class (Nunito Sans). All labels use `font-body` class (DM Sans).

Reference the mockup file for exact layout: `.superpowers/brainstorm/281757-1779606123/content/skipper-v8.html`.

Key implementation details:
- Panel titles: `text-[11px] font-extrabold tracking-[2.5px] uppercase text-text-dim`
- Hero numbers (SOG): `font-sans text-[72px] font-bold tracking-tight text-teal`
- Secondary values: `font-sans text-[16px] font-semibold`
- Tertiary labels: `text-[12px] font-medium text-text-dim`
- Weather hero: gradient background flush with panel top, no panel title
- Charts are wrapped in `<a>` tags linking to `/admin/explore?metric=<metric_name>`
- Beaufort scale: computed from TWS with a lookup table
- Point of sail: computed from TWA with range-based classification

- [ ] **Step 2: Verify the page renders**

Run `npm run dev`, navigate to `/admin/skipper`. The page should render with real data from Prometheus.

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/Admin/SkipperOverview.vue
git commit -m "feat: add Skipper Overview page with instrument panels"
```

---

### Task 6: Redesign Dashboard.vue

**Files:**
- Modify: `resources/js/Pages/Admin/Dashboard.vue`
- Modify: `app/Http/Controllers/Admin/AdminDashboardController.php`

- [ ] **Step 1: Update AdminDashboardController to pass additional data**

The controller already provides `boat`, `gps`, `weather`, `activeJourney`, `plannedJourney`, `routeWaypoints`, `timestamp`. Add tracker status for health indicators and SRT stream status:

```php
$tracker = $metrics->getTrackerMetrics();

$srtUp = $prometheus->query('scarlet_srt_up');
$srtPublisher = $prometheus->query('scarlet_srt_publisher_connected');

return Inertia::render('Admin/Dashboard', [
    // ...existing data...
    'tracker' => $tracker,
    'streamOnline' => ($srtUp[0]['value'] ?? 0) >= 1,
    'streamPublisher' => ($srtPublisher[0]['value'] ?? 0) >= 1,
]);
```

- [ ] **Step 2: Redesign Dashboard.vue**

Complete rewrite of Dashboard.vue following the spec. The page uses:
- CompassRose component (340px, centered hero)
- Instrument readings flanking the compass (SOG, SOW, HDG, Depth on left; TWS, TWA on right)
- Status ribbon at top (sailing badge, boat name "Scarlet" in scarlet, journey summary, tracker + stream health dots)
- Journey section (Leaflet map + nav data panel: DTW, TTG, ETA, lat/lon)
- Bottom row: Ship Status panel (LevelBar x3 + power sparkline) and Weather panel (gradient hero + data rows + pressure sparkline)

Reference the mockup: `.superpowers/brainstorm/281757-1779606123/content/dashboard-v5.html`.

Key differences from current Dashboard.vue:
- Remove journey start/end modals (those belong on the Journeys page)
- Remove recent journeys list
- Remove tracker status section (now a health dot in the ribbon)
- Add compass rose as visual centerpiece
- Add point of sail pill below compass
- Instrument readings as inline pairs, not a grid of cards

Preserve the existing Leaflet map logic from `useScarletMetrics` (initMap, addMapTarget, updateAllMaps).

- [ ] **Step 3: Verify dashboard renders with real data**

Run `npm run dev`, navigate to `/admin`. Verify compass renders, instruments show real values, map shows position, weather panel populates.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/Admin/Dashboard.vue app/Http/Controllers/Admin/AdminDashboardController.php
git commit -m "feat: redesign admin dashboard with compass rose hero layout"
```

---

### Task 7: Redesign StreamMonitor into Broadcast

**Files:**
- Rename/Create: `resources/js/Pages/Admin/Broadcast.vue` (replace StreamMonitor.vue)
- Modify: `app/Http/Controllers/Admin/StreamMonitorController.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Update route**

In `routes/web.php`, change the stream monitor route:

```php
// Replace:
Route::get('stream', [StreamMonitorController::class, 'index'])->name('admin.stream');
// With:
Route::get('broadcast', [StreamMonitorController::class, 'index'])->name('admin.broadcast');
```

Keep a redirect from the old URL:

```php
Route::redirect('stream', 'broadcast');
```

- [ ] **Step 2: Update StreamMonitorController render target**

In StreamMonitorController.php, change the Inertia render target:

```php
return Inertia::render('Admin/Broadcast', [
    // ...same data as before...
]);
```

Also add the HLS stream URL to the returned data:

```php
'hlsUrl' => config('scarlet.mediamtx.api_url') ? str_replace('/v3', '', config('scarlet.mediamtx.api_url')) . '/scarlet/index.m3u8' : null,
```

- [ ] **Step 3: Create Broadcast.vue**

Create `resources/js/Pages/Admin/Broadcast.vue` following the mockup at `.superpowers/brainstorm/281757-1779606123/content/broadcast.html`.

Structure:
- Header: page title + external links (Overlay, Public Dashboard)
- Status hero: large pulsing indicator, "Live"/"Offline" text, bitrate + drops at-a-glance
- Main section: 2-column grid
  - Left (wider): 16:9 HLS video embed using `useVideoFeed.js` composable
  - Right (320px): 3 vertically stacked compact panels (Bitrate sparkline, Drops spike chart, RTT sparkline)

The video embed uses the existing `useVideoFeed` composable with the HLS URL. When publisher is not connected, show a dark placeholder with a play icon.

Chart panels are clickable links to `/admin/explore?metric=<metric>`. Use compact padding (ptitle-sm, pbody-sm classes). Values at 22px Nunito Sans.

Polling: keep the existing 15-second auto-refresh via `router.reload()`.

- [ ] **Step 4: Remove old StreamMonitor.vue**

Delete `resources/js/Pages/Admin/StreamMonitor.vue`.

- [ ] **Step 5: Verify broadcast page works**

Run `npm run dev`, navigate to `/admin/broadcast`. Verify status hero shows stream state, charts render, video embed appears (or placeholder if no stream).

- [ ] **Step 6: Commit**

```bash
git add resources/js/Pages/Admin/Broadcast.vue app/Http/Controllers/Admin/StreamMonitorController.php routes/web.php
git rm resources/js/Pages/Admin/StreamMonitor.vue
git commit -m "feat: redesign stream monitor into broadcast page with video embed"
```

---

### Task 8: Final Cleanup and Verification

**Files:**
- Modify: `resources/js/Layouts/AdminLayout.vue` (if any nav link adjustments needed)
- Verify: all existing pages still render correctly

- [ ] **Step 1: Verify all pages render**

Navigate to each admin page and confirm it renders without errors:
- `/admin` (Dashboard)
- `/admin/skipper` (Skipper Overview)
- `/admin/broadcast` (Broadcast)
- `/admin/settings`
- `/admin/journeys`
- `/admin/weather`
- `/admin/log`
- `/admin/tracker`
- `/admin/explore`
- `/admin/team`
- `/admin/profile`

- [ ] **Step 2: Verify theme switching**

Toggle between Light, Dark, and Night Watch themes. Verify the new pages respect theme changes via CSS custom properties.

- [ ] **Step 3: Verify sidebar navigation**

Confirm all sidebar links navigate correctly, active states highlight properly, external links open in new tabs.

- [ ] **Step 4: Verify real-time updates**

On the Dashboard and Skipper Overview, confirm WebSocket updates flow through and update instrument values in real-time.

- [ ] **Step 5: Run build**

Run: `npm run build`
Expected: Build succeeds with no errors or warnings.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "chore: final cleanup and verification for admin restructure"
```
