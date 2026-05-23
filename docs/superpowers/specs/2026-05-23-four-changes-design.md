# Four Changes: Reload, Fuel, Navigation, Battery Power

Four independent changes to the Scarlet overlay system.

## 1. Forced Reload via WebSocket

### Broadcast Event

New `App\Events\ForceReload` event broadcasting on the `metrics` channel as `.force-reload`. No payload needed.

### Listeners

Overlay (`resources/js/Pages/Public/Overlay.vue`) and Dashboard (`resources/js/Pages/Public/Dashboard.vue`) already subscribe to the `metrics` Echo channel. Add a listener for `.force-reload` that calls `window.location.reload()`.

The admin pages do not listen for this event.

### Admin Trigger

**Route**: `POST /admin/settings/force-reload` — broadcasts the `ForceReload` event.

**UI**: Button on the Settings page (`resources/js/Pages/Admin/Settings.vue`). Styled HTML confirmation modal (never `confirm()`). Button label: "Force Reload Clients". Modal text: "This will reload all overlay and dashboard browser windows. Continue?"

## 2. Fuel Sensor Update

### Metric Change

Old: `scarlet_signalk_tanks_diesel_currentLevel`
New: `scarlet_signalk_tanks_fuel_currentLevel`

### Scaling

The sensor reads 0.91 as full (100%). Formula: `min(scarlet_signalk_tanks_fuel_currentLevel / 0.91, 1) * 100`

### Config Changes

All in `config/scarlet.php`:

- `metrics.mappings.boat.fuel_level`: change PromQL to `clamp_max(scarlet_signalk_tanks_fuel_currentLevel / 0.91, 1) * 100`
- `metrics.mappings.history.fuel_level`: same PromQL
- `metrics.mappings.explore.fuel_level.query`: same PromQL

The `clamp_max(..., 1)` prevents values above 100% if the sensor overshoots 0.91.

## 3. Autopilot Navigation Data

### Prometheus Metrics

- `scarlet_signalk_navigation_courseRhumbline_nextPoint_distance` — metres to next waypoint
- `scarlet_signalk_navigation_courseRhumbline_nextPoint_timeToGo` — seconds to next waypoint

### Config

Add to `metrics.mappings.boat`:

```php
'nav_wp_distance' => 'scarlet_signalk_navigation_courseRhumbline_nextPoint_distance / 1852',
'nav_wp_ttg' => 'scarlet_signalk_navigation_courseRhumbline_nextPoint_timeToGo',
```

Values: distance in nautical miles, TTG in seconds. ETA computed client-side as `Date.now() + ttg * 1000`.

### Display Rules

Navigation data is hidden when there is no active waypoint. Inactive is defined as: `nav_wp_distance == null || nav_wp_distance <= 0 || nav_wp_ttg == null || nav_wp_ttg <= 0`.

### Admin: Boat Metrics Page

Add to the Compass panel's data rows (after the existing Trip row), conditionally rendered:

- **Next WP**: distance in nm (e.g. "2.4 nm")
- **TTG**: formatted duration (e.g. "1h 23m")
- **ETA**: formatted time (e.g. "14:35")

### Admin: Dashboard Page

Add a "Navigation" section below the journey info, conditionally rendered when waypoint is active. Shows the same three values: distance, TTG, ETA.

### Public Dashboard

Add navigation data to the existing floating info cluster on the map. Same three values, same conditional rendering. Hidden when no active waypoint.

### Overlay

Not displayed on the overlay (per requirements).

### Explore Page

Add both metrics to the explore registry:

```php
'nav_wp_distance' => [
    'label' => 'Distance to Waypoint',
    'unit' => 'nm',
    'color' => 'oklch(0.55 0.15 240)',
    'query' => 'scarlet_signalk_navigation_courseRhumbline_nextPoint_distance / 1852',
    'group' => 'navigation',
],
'nav_wp_ttg' => [
    'label' => 'Time to Waypoint',
    'unit' => 's',
    'color' => 'oklch(0.60 0.16 240)',
    'query' => 'scarlet_signalk_navigation_courseRhumbline_nextPoint_timeToGo',
    'group' => 'navigation',
],
```

## 4. Battery Power Charge/Discharge in Explore

### Current State

The BoatMetrics page already renders battery power with green (charging) above zero and amber (discharging) below, using SVG clip paths. The explore page's uPlot chart for `battery_power` currently renders as a single-color area fill.

### Change

When the explore chart displays a metric with `signed: true` (currently only `battery_power`), modify the uPlot configuration:

- **Zero line**: horizontal dashed line at y=0 in a neutral color
- **Split fills**: green (`oklch(0.62 0.15 155 / 0.08)`) for the area above zero, amber (`oklch(0.65 0.18 40 / 0.08)`) for the area below zero
- **Split strokes**: green stroke above zero, amber stroke below zero
- **Y-axis**: show `+` prefix for positive values (already implemented)
- **Legend/stats**: show current value with sign, use green for positive, amber for negative

Implementation: uPlot supports this via the `bands` plugin or by using two series (one clamped to positive, one to negative) with separate fill colors. The two-series approach is simpler: split the data into `positiveValues` (negative clamped to null) and `negativeValues` (positive clamped to null), render as two series with different colors sharing the same Y scale.
