# Metric Explore Page

Interactive metric exploration for the admin dashboard. Click any chart on Boat Metrics, Tracker, or Stream Monitor to open a Grafana-like explore view with configurable time ranges, auto-refresh, hover tooltips, drag-to-zoom, and multi-series overlays.

## Route & Navigation

Single shared page at `GET /admin/explore`. Metric and time state passed as query parameters:

- `?metric=battery_voltage` — which metric to display (required)
- `?range=24h` — preset slug (default: `24h`)
- `?start=<unix>&end=<unix>` — custom range (overrides `range`)
- `?overlay=battery_current,battery_power` — additional series (comma-separated)
- `?refresh=15` — auto-refresh interval in seconds (0 = off)

URL-driven state makes views bookmarkable and shareable.

Existing chart panels on Boat Metrics, Tracker, and Stream Monitor pages become clickable. Clicking a chart navigates to `/admin/explore?metric=<slug>&range=<default_range>` where `default_range` matches the chart's current fixed window (24h for boat metrics, 1h for tracker).

## Metric Registry

A PHP config array mapping metric slugs to display metadata. Sources from the existing `config('scarlet.metrics.mappings.history')` entries.

Each entry:

```php
[
    'slug'   => 'battery_voltage',
    'label'  => 'Battery Voltage',
    'unit'   => 'V',
    'color'  => 'oklch(0.62 0.15 155)',
    'query'  => 'scarlet_signalk_electrical_batteries_0_voltage',
    'group'  => 'power',
    'signed' => false,
]
```

### Groups & Metrics

**Navigation**: speed (SOG), depth, heading, COG.

**Wind**: true wind speed, true wind direction, apparent wind speed, apparent wind angle.

**Power**: battery voltage, battery current, battery power (V × A, signed: charge positive, discharge negative), battery SOC, engine battery voltage.

**Cabin**: temperature forepeak, temperature quarterberth, temperature main cabin, humidity forepeak, humidity quarterberth, humidity main cabin.

**Tanks**: fuel level (%), water level (%).

**Tracker**: LTE RSSI, WiFi RSSI, GPS satellites, CPU usage.

Battery Power uses the computed PromQL expression `scarlet_signalk_electrical_batteries_0_current * scarlet_signalk_electrical_batteries_0_voltage`. Prometheus evaluates the multiplication at query time.

## Time Range Presets

Clustered into three visual groups to reduce cognitive load:

- **Recent**: 1h, 6h, 24h
- **Extended**: 3d, 7d, 30d
- **Special**: Passage, Custom

### Step Resolution Auto-Scaling

Shorter ranges get finer granularity; longer ranges coarsen to keep data points in the 240–360 range:

| Range | Step | Points |
|-------|------|--------|
| 1h | 15s | ~240 |
| 6h | 60s | ~360 |
| 24h | 300s | ~288 |
| 3d | 900s | ~288 |
| 7d | 1800s | ~336 |
| 30d | 7200s | ~360 |
| Passage | calculated | ~300 target |
| Custom | calculated | ~300 target |

For Passage and Custom ranges, step is calculated as `floor(duration_seconds / 300)` clamped to a minimum of 15s.

### Passage Preset

Looks up the most recent journey (by `started_at` descending) from the Journey model. If no journeys exist, the Passage button is visually dimmed with a tooltip: "No journeys". If the journey has no `ended_at` (still in progress), `end` defaults to now.

## Auto-Refresh

A dropdown in the toolbar next to the time presets. Options: Off, 15s, 30s, 1m, 5m.

- Defaults to 15s for ranges up to and including 24h, Off for longer ranges.
- Uses `setInterval` to fire a fetch to the JSON series endpoint. All visible series (primary + overlays) refresh in a single batched request.
- A circular progress ring next to the selector fills over the countdown period.
- Auto-refresh pauses when the user is in a drag-to-zoom selection or viewing a custom zoomed range. A "Reset zoom" button in the toolbar restores the preset range and resumes refresh.
- Transient fetch failures are silent: the ring stops, a small disconnect indicator appears, and the last successful data stays rendered. Retries on the next interval.

## Controller & API

### Page Controller

`Admin\ExploreController@index` — renders the Inertia page with initial data.

Props passed to the Vue component:

- `metric` — the full metric config object (label, unit, color, query, group, signed)
- `metrics` — all available metrics grouped by category (for the dropdown)
- `data` — initial time-series array from Prometheus `[{timestamp, value}, ...]`
- `overlays` — array of `{metric, data}` for any overlay params
- `range` — active preset slug or `'custom'`
- `start` / `end` — Unix timestamps of the current window
- `step` — step resolution used
- `refresh` — auto-refresh interval
- `passage` — `{available, start, end}` or `null` if no journey

### JSON Series Endpoint

`GET /admin/explore/series` — returns series data without full page reload.

Query params: `metric`, `start`, `end`, `step`. Returns JSON:

```json
{
    "metric": "battery_voltage",
    "data": [{"timestamp": 1716400000, "value": 13.42}, ...],
    "stats": {"current": 13.42, "min": 11.80, "max": 14.35, "avg": 13.05}
}
```

Supports batch requests for overlay refresh: `?metrics=battery_voltage,battery_current&start=...&end=...&step=...` returns an array of series objects.

Stats (current, min, max, avg) are computed server-side from the returned data points.

## UI Layout

### Page Header (Unboxed)

Lives directly on the page background, not in a panel. Two conceptual rows on desktop (single flex row with wrap):

- **Left**: `← Metrics` back link + metric dropdown (grouped by category, shows unit next to each name)
- **Right**: time preset buttons in three clusters with visual gaps between groups, then refresh interval dropdown

### Chart Panel

Single `.panel` matching existing admin style (`border: 1px solid var(--color-border)`, `border-radius: 10px`).

**Panel header**: legend dot + metric name + inline stats as text on the left. Stats format: `13.42 V · 11.80 – 14.35 V · avg 13.05`. `+ Overlay` button on the right. When overlays are active, each gets a legend entry: color dot, name, current value, × remove button.

**Chart area**: uPlot instance, ~400px tall on desktop. Contains:
- Y-axis labels inside the chart on the left (right Y-axis activates for overlays with different units)
- Subtle grid lines in oklch neutral
- Area fill under the primary series line (matching existing admin chart style)
- For signed metrics (Battery Power): zero-line at midpoint, charging above (green area), discharging below (amber area)

**X-axis**: timestamps below, auto-formatted:
- Ranges up to 24h: time only (`14:30`)
- Ranges up to 7d: short date + time (`21 May 14:30`)
- Ranges 30d: date only (`21 May`)

### Interactions

- **Hover**: vertical crosshair (scarlet, dashed) snaps to nearest data point. Dark tooltip with backdrop-blur shows values for all visible series at that timestamp with color dots.
- **Drag to zoom**: horizontal click-drag selects a sub-range. Chart re-queries Prometheus at finer step resolution. Auto-refresh pauses. "Reset zoom" button appears in toolbar.
- **Double-click**: resets to the selected preset range.
- **Keyboard**: left/right arrow keys step the crosshair between data points when chart is focused.

### Custom Date Range

Clicking "Custom" opens an inline dropdown (not a modal) below the toolbar. Two datetime-local inputs (From / To) with an Apply button. Closes on apply or click-outside.

### Empty & Error States

- **No data**: chart area shows centered "No data for this time range" text in dim color, same pattern as existing admin charts.
- **Prometheus unreachable**: muted warning banner below toolbar: "Metrics unavailable — retrying in Xs". Last successful data stays rendered.
- **Passage unavailable**: button dimmed, title attribute explains "No active journey".
- **Overlay metric conflict**: if a user tries to add more than 3 overlays, the "+ Overlay" button is replaced with "3 series max" in dim text.

## Mobile Responsiveness

Follows existing admin breakpoint at Tailwind's `md:` (768px).

### Mobile (<768px)

- **Toolbar**: splits to two rows. Top row: back link + metric dropdown (full width). Second row: time presets in a horizontally scrolling flex container (`overflow-x: auto`, hidden scrollbar). Active preset stays scrolled into view. Refresh selector at the end of the scroll row.
- **Chart**: full width, height drops to ~280px. Touch: tap-and-hold for tooltip, pinch-to-zoom (uPlot supports touch natively).
- **Inline stats**: wraps naturally as text.
- **Overlay legend**: entries stack vertically.
- **Custom picker**: datetime inputs stack vertically.
- **Metric dropdown**: full-width on mobile.

### Connectivity Resilience

- Auto-refresh failures are silent (no error toast spam). Disconnect dot appears; retries on next interval.
- Last successful dataset stays rendered; chart never goes blank on transient failure.
- If initial page load fails Prometheus, page renders with empty arrays + "Metrics unavailable" banner. Page remains navigable.

## Charting Library

**uPlot** (~45KB, GPU-accelerated, designed for time-series). Used by Grafana internally. Supports all required features natively: multiple series, dual Y-axes, cursor/crosshair, drag-to-zoom, touch interactions.

Installed as an npm dependency. The existing custom SVG charts on Boat Metrics, Tracker, and Stream Monitor pages remain unchanged; uPlot is only used on the explore page.

## Clickable Charts (Existing Pages)

Each chart panel on Boat Metrics, Tracker, and Stream Monitor becomes an Inertia `<Link>` wrapping the panel. Visual affordance: on hover, a subtle explore icon (magnifying glass or expand arrows) appears in the top-right corner of the panel, and the panel border color shifts slightly. Cursor changes to pointer.

The link navigates to `/admin/explore?metric=<slug>&range=<default>` where default matches the chart's current fixed window.
