# Weather Summary, Weather Page & Ship's Log

## Overview

Three related features for the admin panel:

1. **Dashboard weather summary** — minimal weather card on admin dashboard
2. **Weather page** — full weather detail with boat sensor comparison
3. **Ship's log page** — tabular point-in-time snapshots of boat metrics
4. **Journey log** — same log table embedded on the journey edit/view page

## 1. Dashboard Weather Summary

A compact panel on the admin dashboard (after Boat Status, before Navigation).

**Content:**
- Weather icon (derived from WMO code, already implemented in `Weather::getWeatherSummary()`)
- Condition text (e.g. "Sunny", "Rain", "Cloudy")
- Air temperature (°C)
- Wind speed (kn) and compass direction (e.g. "NNW")
- Sea surface temperature (°C)

**Boat comparison line:**
- One row below showing boat-reported true wind speed/direction and water temp
- Labelled "Boat sensors" to distinguish from forecast data

**Link:** "View Weather →" links to `/admin/weather`

**Data source:** `WeatherService::getWeather()` via `MetricsService::getWeatherData()`. Boat wind/water temp already in `getBoatMetrics()`.

**Controller change:** Add `weather` prop to `AdminDashboardController::index()`.

## 2. Weather Page (`/admin/weather`)

New admin page with full weather detail.

**Sidebar:** New nav item "Weather" with a cloud/sun icon, between "Boat Metrics" and "Explore".

**Panels:**

### Conditions
- Weather icon (large) + condition text
- Air temperature
- Daytime/nighttime indicator

### Wind
| Source | Speed | Direction |
|--------|-------|-----------|
| Forecast (Open-Meteo) | X kn | NNW (270°) |
| Boat (true wind) | X kn | NNW (270°) |
| Boat (apparent wind) | X kn | 45° |

### Sea State
| Metric | Value |
|--------|-------|
| Wave height | X m |
| Wave direction | NNW (270°) |
| Wave period | X s |
| Sea surface temp (forecast) | X °C |
| Water temp (boat sensor) | X °C |

### Ocean Current
| Metric | Value |
|--------|-------|
| Current speed | X kn |
| Current direction | NNW (270°) |

**Data source:** `WeatherService::getWeather()` for forecast, `MetricsService::getBoatMetrics()` for boat sensors.

**Controller:** New `AdminWeatherController` with `index()` method.

## 3. Ship's Log Page (`/admin/log`)

New admin page showing a table of point-in-time metric snapshots.

**Sidebar:** New nav item "Log" with a clipboard/list icon, between "Journeys" and "Tracker".

### Period Selector
Dropdown with options: Last 6h, 12h, **24h** (default), 48h, 7d. Changing the period reloads the page with the selected duration.

### Log Table

One row per hour. Each value is the instantaneous Prometheus reading at that timestamp.

| Column | Source metric / query | Format |
|--------|----------------------|--------|
| Time (Local) | Row timestamp | `HH:MM` (or `DD/MM HH:MM` for periods > 24h) |
| Trip Log | `scarlet_signalk_navigation_trip_log / 1852` | X.X nm |
| Wind Dir | `scarlet_boat_wind_direction_deg` | Compass cardinal (N, NNE, NE, ENE, E, etc.) |
| Wind (Bft) | `scarlet_boat_wind_speed_kn` | Beaufort number (0–12) |
| Baro | — | Always `—` (not available) |
| Lat/Long | `scarlet_signalk_navigation_position_latitude`, `scarlet_signalk_navigation_position_longitude` | DD°MM.MMM'N/S DD°MM.MMM'E/W |
| WP Distance | `scarlet_signalk_navigation_courseGreatCircle_nextPoint_distance / 1852` | X.X nm (or `—` if null) |
| WP TTG | `scarlet_signalk_navigation_courseGreatCircle_nextPoint_timeToGo` | Xh Xm (or `—` if null) |
| Battery % | `scarlet_signalk_electrical_batteries_0_capacity_stateOfCharge * 100` | X% |
| Water % | `scarlet_mqtt_percent{topic="watertank"}` | X% |
| Fuel % | `clamp_max(scarlet_signalk_tanks_fuel_currentLevel / 0.91, 1) * 100` | X% |

### Beaufort Conversion

| Beaufort | Knots |
|----------|-------|
| 0 | < 1 |
| 1 | 1–3 |
| 2 | 4–6 |
| 3 | 7–10 |
| 4 | 11–16 |
| 5 | 17–21 |
| 6 | 22–27 |
| 7 | 28–33 |
| 8 | 34–40 |
| 9 | 41–47 |
| 10 | 48–55 |
| 11 | 56–63 |
| 12 | ≥ 64 |

### Compass Direction Conversion

16-point compass: N, NNE, NE, ENE, E, ESE, SE, SSE, S, SSW, SW, WSW, W, WNW, NW, NNW. Each sector is 22.5°.

### Backend

New method `MetricsService::getLogData(string $duration, string $step, ?int $start, ?int $end)`:

- Queries each metric using `PrometheusService::queryRange()` with the given duration and step
- Aligns all metrics by timestamp
- Returns an array of rows, each with all column values at that timestamp

**Controller:** New `AdminLogController` with `index(Request $request)`. Accepts `period` query param (default `24h`). Step is always `3600` (1 hour).

**Route:** `GET /admin/log` → `AdminLogController@index`

## 4. Journey Log

On the `JourneyEdit` page, add a "Log" section below the existing form fields (only for journeys with `started_at` and `ended_at`).

Same table as the Log page, but:
- Time range: journey's `started_at` to `ended_at`
- Step auto-calculated based on duration:
  - < 3 days: 1 hour (`3600s`)
  - 3–7 days: 2 hours (`7200s`)
  - > 7 days: 4 hours (`14400s`)

**Controller change:** Add log data to `JourneyController::edit()` when the journey has start/end times.

## 5. WMO Code to Condition Text

Map the WMO weather codes to human-readable text for display:

| Code | Text |
|------|------|
| 0 | Clear sky |
| 1 | Mainly clear |
| 2 | Partly cloudy |
| 3 | Overcast |
| 45, 48 | Fog |
| 51, 53, 55 | Drizzle |
| 56, 57 | Freezing drizzle |
| 61, 63, 65 | Rain |
| 66, 67 | Freezing rain |
| 71, 73, 75 | Snow |
| 77 | Snow grains |
| 80, 81, 82 | Showers |
| 85, 86 | Snow showers |
| 95, 96, 99 | Thunderstorm |

## File Changes Summary

### New files
- `app/Http/Controllers/Admin/AdminWeatherController.php`
- `app/Http/Controllers/Admin/AdminLogController.php`
- `resources/js/Pages/Admin/Weather.vue`
- `resources/js/Pages/Admin/Log.vue`

### Modified files
- `routes/web.php` — add `/admin/weather` and `/admin/log` routes
- `resources/js/Layouts/AdminLayout.vue` — add Weather and Log nav items
- `app/Http/Controllers/Admin/AdminDashboardController.php` — add weather prop
- `resources/js/Pages/Admin/Dashboard.vue` — add weather summary panel
- `app/Http/Controllers/Admin/JourneyController.php` — add log data to edit
- `resources/js/Pages/Admin/JourneyEdit.vue` — add log table section
- `app/Services/MetricsService.php` — add `getLogData()` method
- `config/scarlet.php` — add log metric mappings
