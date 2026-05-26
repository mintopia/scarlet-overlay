# Ship's Log — Persistent DB Records with Editable Notes

**Date:** 2026-05-26
**Status:** Design approved, pending implementation

## Summary

Replace the current ephemeral Prometheus-queried ship's log with persistent `ShipLog` database records generated on an hourly schedule. Each record captures the same metrics currently shown in the log table, plus a user-editable notes field. A one-time backfill command populates historical data from Prometheus. The admin log page switches to reading from the database.

## Data Model

### `ship_logs` table

| Column | Type | Nullable | Description |
|---|---|---|---|
| `id` | bigint PK | no | Auto-increment |
| `journey_id` | FK → journeys | yes | Active journey at time of recording, null if none |
| `recorded_at` | timestamp | no | Hour-aligned UTC timestamp (e.g., 14:00:00) |
| `latitude` | decimal(10,7) | yes | Position latitude |
| `longitude` | decimal(10,7) | yes | Position longitude |
| `course` | decimal(5,1) | yes | COG in degrees (computed from COG, fallback heading) |
| `trip_log` | decimal(8,1) | yes | Cumulative trip log in nautical miles |
| `wind_speed` | decimal(5,1) | yes | True wind speed in knots (computed) |
| `wind_direction` | decimal(5,1) | yes | True wind direction in degrees (computed) |
| `pressure` | decimal(6,1) | yes | Barometric pressure in hPa (from MQTT Zigbee sensor) |
| `wp_distance` | decimal(8,1) | yes | Distance to next waypoint in nm |
| `wp_ttg` | decimal(10,0) | yes | Time to go to next waypoint in seconds |
| `battery_soc` | decimal(5,1) | yes | House battery state of charge % |
| `water_level` | decimal(5,1) | yes | Fresh water tank % |
| `fuel_level` | decimal(5,1) | yes | Fuel tank % |
| `notes` | text | yes | User-editable log entry notes |
| `created_at` | timestamp | no | Laravel timestamp |
| `updated_at` | timestamp | no | Laravel timestamp |

**Indexes:**
- Unique index on `recorded_at` (prevents duplicate hourly entries, enables idempotent generation)
- Index on `journey_id` (for journey-scoped queries)

**Key decisions:**
- Stores computed values (true wind, course) not raw sensor readings — matches what a physical log book would record
- `journey_id` is nullable because logging runs continuously regardless of journey state
- Barometric pressure sourced from `scarlet_mqtt_pressure{topic="zigbee2mqtt/Forepeak"}`

### `ShipLog` Eloquent model

- Belongs to `Journey` (nullable)
- `$casts`: `recorded_at` as datetime
- Fillable: all metric columns + `notes` + `journey_id`
- Factory and seeder for testing

## Scheduled Generation

### `ship-log:generate` Artisan command

Runs hourly via Laravel scheduler. Each invocation:

1. Calculates the hour-aligned timestamp for the current hour (e.g., running at 14:02 generates the 14:00 entry)
2. Checks if a `ShipLog` already exists for that `recorded_at` — skips if so (idempotent)
3. Queries Prometheus for all metrics defined in `config('scarlet.metrics.mappings.log')` at that timestamp
4. Computes true wind direction/speed from apparent wind + STW + heading (reuses `MetricsService::calculateTrueWind`)
5. Computes course from COG (fallback to heading), converting radians to degrees
6. Looks up `Journey::current()` to set `journey_id` (null if no active journey)
7. Creates the `ShipLog` record

**Schedule registration** in `routes/console.php`:
```php
Schedule::command('ship-log:generate')->hourly();
```

### `ship-log:backfill` Artisan command

One-time (or repeatable) command to populate historical records from Prometheus:

1. Accepts `--from` and `--to` date arguments (defaults to last 7 days if not specified)
2. Queries Prometheus range data in hourly steps (step=3600s) for the entire period
3. For each hourly timestamp, applies the same computation logic as `ship-log:generate`
4. Associates entries with journeys by matching `recorded_at` against journey `started_at`/`ended_at` ranges
5. Skips any timestamps where a `ShipLog` already exists (idempotent via unique index)
6. Outputs progress: "Created N entries, skipped M duplicates"

## Admin Log Page Changes

### Controller (`AdminLogController`)

Rewritten to read from `ShipLog` model instead of querying Prometheus:

1. Period selection unchanged: journey, 6h, 12h, 24h, 48h, 7d
2. Queries `ShipLog::where('recorded_at', '>=', $start)->orderBy('recorded_at')` instead of `MetricsService::getLogData()`
3. For journey periods, filters by `journey_id` instead of date range
4. Computes derived columns in PHP from stored values:
   - `dist`: delta between consecutive `trip_log` values
   - `dmg`: delta between consecutive `wp_distance` values (inverted — decreasing distance = positive DMG)
   - `diff`: `dmg - dist`
   - `cum_diff`: running cumulative of `diff`
5. Includes `total_log` (raw `trip_log`) and journey-relative `trip_log` (offset from first entry) for journey periods
6. Passes notes and ShipLog IDs to the frontend

### New route

```
PATCH /admin/ship-log/{shipLog}  →  AdminLogController@update
```

Accepts `{ notes: string|null }`. Protected by standard auth middleware. Any authenticated team member can edit notes.

### LogTable component — notes UI

**Three-state expandable row pattern:**

#### Icon column
- ~28px wide, last column in the table
- Rows with notes: filled comment/speech-bubble icon (dim text color)
- Rows without notes: dim `+` icon
- Visual scan indicator — filled icons mark which hours have annotations

#### "Notes" toolbar toggle
- Checkbox/toggle in the toolbar row alongside Timezone and Period selectors
- Label: "Notes"
- When enabled: all rows with notes auto-expand in read-only mode
- Provides a full logbook reading view

#### Row states

**Collapsed** (default): Only the data row visible. Icon column shows comment or `+` icon.

**Expanded — read mode**: Click the icon (or `+`) to expand. A `<tr>` with `<td colspan="17">` appears below the data row. Note text displayed as plain text, left-aligned, subtle background tint (`--color-bg`). Multiple rows can be expanded simultaneously.

**Expanded — edit mode**: Click on the expanded note text (or `+` on an empty row) to enter edit mode. Plain text becomes a textarea (2-3 rows, auto-grows). Right-aligned Save and Cancel buttons appear. Only one row in edit mode at a time.

#### Interactions
- **Save**: `router.patch()` to `ship-log.update`. On success, returns to read-only expanded state (stays expanded).
- **Cancel**: Returns to read-only state. If the note was empty and nothing was typed, collapses the row.
- **Opening edit on another row**: Current edit is cancelled (no confirm dialog — notes are low-stakes).

## What stays the same

- Timezone selector, period selector, passage efficiency chart — unchanged
- Column layout and formatting — identical, reading from DB fields instead of Prometheus
- `MetricsService::getLogData()` method preserved (used by backfill command) but no longer called by the controller

## What gets removed

- Nothing removed. The Prometheus query path remains available for the backfill command.

## Out of scope

- Public dashboard ship log view (admin-only for now, can extend later)
- Custom log interval (fixed at hourly)
- Automatic gap detection/fill (backfill command handles this manually)
