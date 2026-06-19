# Admin Navigation & Data-View Overhaul — Design

**Date:** 2026-06-19
**Status:** Approved (pending spec review)
**Area:** Admin (Inertia + Vue 3) — navigation, dashboards, metric data views, shared SVG charts

## Summary

A consolidation pass over the admin area:

1. Remove three pages (**Tracker**, **Broadcast**, **Environment**) and clean up navigation.
2. Add a camera-only **Stream** page, linked from the Tech dashboard and the sidebar.
3. Reorganise the sidebar into three labelled sections.
4. Fix two layout problems on the **Ops** dashboard (weather-band redundancy + battery-cell alignment).
5. Rename **Data Mapping → Data**, add a live "last value + age" readout per metric, and add a Grafana-style per-metric **explorer** page (replacing the removed Explore page) built on uPlot.
6. Fix variable-width lines in the hand-drawn SVG charts.

No new runtime dependencies: uPlot (`uplot ^1.6.32`) is already in `package.json`.

---

## A. Navigation cleanup & sectioning (`resources/js/Layouts/AdminLayout.vue`)

Replace the current single-divider nav with three labelled sections. Remove the Tracker, Broadcast, Environment, and Explore links. Rename "Data Mapping" to "Data". Add "Stream".

```
VOYAGE
  Dashboard      /admin
  Journeys       /admin/journeys
  Ship's Log     /admin/log
  Planner        /admin/planner
  Tracks         /admin/tracks
DASHBOARDS
  Main           /admin/dash/main
  Tech           /admin/dash/tech
  Ops            /admin/dash/ops
  Skipper        /admin/dash/skipper
  Stream         /admin/stream        (new)
SYSTEM
  Data           /admin/metrics/catalog   (label renamed)
  Settings       /admin/settings
  Team           /admin/team
(external, pinned bottom)
  Overlay ↗      /overlay
  Public Dashboard ↗   /dashboard
```

- Add a small uppercase **section-header** element (e.g. `.nav-section`) styled like the existing dim section labels; it replaces the lone `.nav-divider`. Keep one subtle divider/spacing between sections.
- **`breadcrumbMap`**: remove entries for `Admin/Tracker`, `Admin/Broadcast`, `Admin/Environment`, `Admin/Explore`, `Admin/ExploreDashboard`. Change `Admin/Catalog` label from `Data Mapping` to `Data`. Add `Admin/Stream` → `[home, { label: 'Stream' }]` and `Admin/MetricExplorer` → `[home, { label: 'Data', href: '/admin/metrics/catalog' }, { label: <metric> }]`.

## B. Remove Tracker / Broadcast / Environment

**Tracker**
- Delete `resources/js/Pages/Admin/Tracker.vue`.
- Remove route `admin.tracker` (`GET /admin/tracker` → `TrackerController@index`) from `routes/web.php`.
- Delete `TrackerController` if unreferenced elsewhere.
- The `TrackerPanel` **component** used by Tech.vue is unrelated — keep it.

**Broadcast** (page only — the pull control & stats already live on Tech)
- Delete `resources/js/Pages/Admin/Broadcast.vue`.
- Remove GET route `admin.broadcast` (`StreamMonitorController@index`).
- **Keep** `POST admin.broadcast.pull` → `StreamMonitorController::updatePull` — `components/Dash/SignalChain.vue` (rendered by Tech) calls `route('admin.broadcast.pull')` for Start/Stop Stream. Trim `StreamMonitorController` to the methods still in use.

**Environment** (covered by Ops)
- Delete `resources/js/Pages/Admin/Environment.vue`.
- Remove routes `admin.environment` (`GET /admin/environment`), the `/admin/weather` redirect, and `GET /admin/environment/series`.
- Delete `AdminEnvironmentController` if unreferenced.
- Any remaining `/admin/explore?...` or `/admin/environment` links elsewhere are updated to the new Data explorer (`/admin/data/{metric}`) or removed.

## C. New Stream page (camera only)

- Route: `GET /admin/stream` → name `admin.stream`. Thin controller (new `Admin/StreamController@index` or a method on the trimmed `StreamMonitorController`) returning `Inertia::render('Admin/Stream')`. No props beyond what the layout needs.
- `resources/js/Pages/Admin/Stream.vue`: full-bleed live camera using the existing `composables/useVideoFeed.js` (HLS `/hls/live_web/index.m3u8`, native-HLS-or-hls.js fallback already implemented). Mirror the `<video>` wiring used by `Public/Camera.vue` / the old Broadcast page. Show a tasteful "waiting for feed" state while the stream connects. No stats, no controls.
- **Link from Tech** (`Dash/Tech.vue`): insert a "Live Camera" link card between `SignalChain` and `TrackerPanel` that navigates to `/admin/stream` (Inertia `<Link>`).
- Sidebar entry under **Dashboards** (see A).

## D. Ops dashboard fixes (`resources/js/Pages/Admin/Dash/Ops.vue`)

### D1. De-duplicate the weather band

Today: wind appears in both the **Atmosphere** column and **Sailing & Navigation**; gust appears both folded into the wind value (`{{ windDisplay }}` renders `12 kts G18`) and as a standalone "Gust" row.

Target:
- **Atmosphere** column rows: **Wind** (speed only — drop the `G…` suffix), **Direction**, **Gust** (the single home for gust), **Pressure**.
- **Sailing & Navigation** column: remove the **Wind** row. Keep **SOG · Heading · COG · Depth · ETA** (boat-motion only).
- Implementation: introduce a speed-only wind display (e.g. `windSpeedDisplay`) for the Atmosphere Wind row; keep `gustDisplay` for the Gust row; delete the Sailing Wind `<div class="ops-sdrow">`.

### D2. Battery-cell alignment (Endurance region)

The House and EcoFlow cells (`.ops-power` grid) drift vertically because their text blocks above the chart differ in height. Give both cells a shared vertical rhythm so SOC figures, detail line, chart top, and axis row line up across columns:
- Enforce consistent min-heights / structure on `.ops-sys__header`, `.ops-sys__main`, `.ops-sys__det` so the `.ops-chart` starts at the same Y in both columns.
- Verify both `TrendChart`s receive identical `height`/`width` and that the chart baseline renders level (helped by fix F).

## E. Data page + per-metric explorer

### E1. Rename Data Mapping → Data (`resources/js/Pages/Admin/Catalog.vue`)

- Page `<h1>`, `<Head title>`, and sidebar/breadcrumb labels change to "Data". **Route name and path stay** (`admin.catalog`, `/admin/metrics/catalog`) to avoid churn across `route()` references.

### E2. Live "last value + age" per metric

- On mount, fetch current canonical values for all catalog metrics in one request and render a compact readout on each metric row, e.g. `12.3 kn · 4s ago`, dimmed when stale / `—` when absent.
- New endpoint: `GET admin.catalog.current` (or extend the existing inventory fetch) returning `{ [metricKey]: { value, display_unit, age_s, stale } }`, read via **`CanonicalReader`** (the sole metrics read path; the legacy registry is removed). Reuses the same staleness semantics already used by the per-source "Test" button.
- This is advisory/live data: failure leaves the row showing `—`, the catalog still works.

### E3. Metric explorer page (Grafana-style) — replaces Explore

- Route: `GET /admin/data/{metric}` → name `admin.data.show`, served by a new `Admin/DataController`. `{metric}` is the canonical metric key.
- Time-series endpoint: `GET admin.data.series` → `DataController@series`, params `metrics` (comma-separated keys) + `range` (`6h|24h|7d|30d`), reading **`CanonicalReader::readRange`**. Returns per-metric `{ label, display_unit, data: [{ t, value }], current }`. (Repurposes what the old `ExploreController@series` did.)
- `resources/js/Pages/Admin/MetricExplorer.vue`:
  - Header: metric label/key + **time-range pills** (`6h / 24h / 7d / 30d`). uPlot provides drag-to-zoom; double-click resets.
  - Opens with the routed metric charted in the first graph panel.
  - **`+ Add graph`** → appends another stacked graph panel below, each with its own metric picker and (inherited) range.
  - **`+ Overlay metric`** on a panel → adds a second series to that panel on a **separate, labelled right-hand Y axis** (uPlot multi-axis). Each series legend shows its unit.
  - The page receives the full catalog metric list as a prop (key, label, unit, group) to drive pickers without extra round-trips.
- `resources/js/components/Admin/MetricSelect.vue`: typeahead autocomplete combobox over catalog metrics (filter as you type, keyboard-navigable, groups shown). Emits the chosen metric key.
- `resources/js/components/Admin/UplotChart.vue`: thin uPlot wrapper — props `series` (array of `{ key, label, unit, data, axis: 'left'|'right' }`) + `range`; responsive (ResizeObserver), theme-aware (reads CSS custom properties for light/dark/night), multi-axis, time X-axis. Cleans up the uPlot instance on unmount.
- Remove `ExploreController`, `resources/js/Pages/Admin/Explore.vue`, `resources/js/Pages/Admin/ExploreDashboard.vue`, and routes `admin.explore`, `admin.explore.series`, `admin.explore.current`.

## F. Crisp SVG lines

**Root cause:** the hand-drawn charts render into a stretched viewBox (`preserveAspectRatio="none"`), so a constant `stroke-width` is scaled more horizontally than vertically — diagonal segments look thicker/thinner and lines vary in width.

**Fix:** add `vector-effect="non-scaling-stroke"` to the stroke `<path>`/`<line>` elements (not fills) in:
- `resources/js/components/Admin/Sparkline.vue`
- `resources/js/components/Admin/TrendChart.vue`
- LCARS line graphs (`resources/js/components/Lcars/LcarsLineGraph.vue`, and any sibling LCARS SVGs with the same stretched-viewBox stroke issue).

Stroke width then stays constant in device pixels regardless of the viewBox aspect ratio. This also makes the Ops/Tech battery chart baselines render level (supports D2).

---

## Components & files at a glance

**New**
- `resources/js/Pages/Admin/Stream.vue`
- `resources/js/Pages/Admin/MetricExplorer.vue`
- `resources/js/components/Admin/MetricSelect.vue`
- `resources/js/components/Admin/UplotChart.vue`
- `app/Http/Controllers/Admin/DataController.php` (+ a Stream controller method)

**Modified**
- `resources/js/Layouts/AdminLayout.vue` (sections, links, breadcrumbs)
- `resources/js/Pages/Admin/Dash/Ops.vue` (weather de-dup + battery alignment)
- `resources/js/Pages/Admin/Dash/Tech.vue` (Stream link card)
- `resources/js/Pages/Admin/Catalog.vue` (rename + last value/age)
- `resources/js/components/Admin/Sparkline.vue`, `TrendChart.vue`, LCARS line graph(s)
- `routes/web.php`
- `StreamMonitorController` (trim to used methods)

**Deleted**
- `resources/js/Pages/Admin/Tracker.vue`, `Broadcast.vue`, `Environment.vue`, `Explore.vue`, `ExploreDashboard.vue`
- `TrackerController`, `ExploreController`, `AdminEnvironmentController` (each only if unreferenced)

## Testing

PHPUnit feature tests (the project's standard):
- Removed routes return 404: `/admin/tracker`, `/admin/broadcast` (GET), `/admin/environment`, `/admin/weather`, `/admin/explore`.
- `POST /admin/broadcast/pull` still works (Tech's Start/Stop).
- `GET /admin/stream` renders `Admin/Stream`.
- `GET /admin/metrics/catalog` still renders (now "Data") and the current-values endpoint returns the expected shape.
- `GET /admin/data/{metric}` renders `Admin/MetricExplorer`; `GET /admin/data/series` returns the expected per-metric shape for a known metric.
- A nav assertion (or page render) confirms the removed links are gone and Stream/Data are present.

Manual verification in-app (`npm run dev`): uPlot explorer (add graph / overlay axis / range / zoom), MetricSelect autocomplete, Ops layout, and crisp SVG lines across themes.

## Out of scope / non-goals

- No change to the canonical catalog schema, ingestion pipeline, or metric sources.
- No redesign of the audience dashboards beyond the specific Ops fixes.
- No new charting dependency; uPlot only.
- Public-facing pages (Overlay, Public Dashboard, Camera) are unchanged except where they referenced removed admin routes.
