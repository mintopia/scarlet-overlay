# Journey/Passage System Design

## Overview

A journey system for Scarlet that records passages with persistent GPS track and metrics data, supports planned route GPX uploads, provides a shareable timeline replay view, and adds an admin dashboard landing page.

## Data Model

### `journeys` table

| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | Auto-increment |
| slug | string, unique | Auto-generated: `Str::slug("{from}-to-{to}")`. If slug exists, append `-2`, `-3` etc. Editable in admin (validated unique on save). |
| title | string | Display name, defaults to "From → To" |
| from_port | string | Origin port |
| to_port | string | Destination port |
| started_at | timestamp | When the journey began |
| ended_at | timestamp, nullable | Null while active |
| status | enum: active, completed, abandoned | Only one `active` journey at a time |
| is_public | boolean, default true | Controls public visibility |
| gpx_route_path | string, nullable | File path for uploaded GPX (stored in `journeys/gpx/{id}.gpx`) |
| route_waypoints | JSON, nullable | Parsed `<rtept>` waypoints from GPX: `[{lat, lng, name?}, ...]` |
| notes | text, nullable | Optional description |
| timestamps | | created_at, updated_at |

### `journey_track_points` table

| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | Auto-increment |
| journey_id | bigint FK | References journeys.id, cascade delete |
| recorded_at | timestamp | Sample time |
| latitude | decimal(10,7) | |
| longitude | decimal(10,7) | |
| speed_sog | float, nullable | knots |
| speed_stw | float, nullable | knots |
| heading | float, nullable | degrees |
| cog | float, nullable | degrees |
| depth | float, nullable | metres |
| wind_speed_apparent | float, nullable | knots |
| wind_angle_apparent | float, nullable | degrees |
| wind_speed_true | float, nullable | knots |
| wind_direction_true | float, nullable | degrees |
| house_battery_voltage | float, nullable | V |
| house_battery_current | float, nullable | A |
| heel | float, nullable | degrees |

Index on `(journey_id, recorded_at)` for timeline queries.

## Journey Lifecycle

### Starting a journey

- Admin clicks "Start Journey" in the admin journey section.
- Provides: from port, to port (required), GPX file (optional), notes (optional).
- Creates a journey record with `status = active`, `started_at = now()`.
- If a GPX file is uploaded, the `<rte>` element is parsed to extract `<rtept>` waypoints into the `route_waypoints` JSON column. The raw file is stored at `journeys/gpx/{id}.gpx`.
- Only one journey can be active at a time. Starting a new one while another is active is blocked.

### Recording track points

- The existing `MetricsPushCommand` (runs every 15 seconds) checks for an active journey after broadcasting metrics.
- If active, writes one `JourneyTrackPoint` row with the current metrics snapshot (lat, lng, speed, heading, depth, wind, battery, heel).
- The active journey is cached to avoid a database query every 15 seconds.

### Ending a journey

- **Manual:** Admin clicks "End Journey". Sets `ended_at = now()`, `status = completed`.
- **Auto-stop:** If speed has been below 0.5 kn for 2 continuous hours (480 consecutive pushes at 15s), the push command auto-ends the journey. The `ended_at` is backdated to the last track point where speed was above 0.5 kn, and trailing stationary points are trimmed.
- **Abandon:** Admin can mark a journey as `abandoned` (plans changed, didn't actually depart).
- Stationary counter tracked in cache key `journey.stationary_count`, not the database.

### Retrospective import

- Admin enters from/to ports, start time, end time, optional GPX, optional notes.
- A queued job queries Prometheus `query_range` for that time window at 30-second step intervals.
- Pulls: latitude, longitude, speed (SOG/STW), heading, COG, depth, wind (apparent + true), battery voltage/current, heel.
- Creates the journey record with `status = completed` and bulk-inserts track points in batches of 500.
- If Prometheus data is unavailable for parts of the requested range (retention exceeded), the job completes with whatever data it gets and logs a warning.

## Settings Migration

- `passage_from` and `passage_to` BoatSettings are replaced by reading from the active journey's `from_port`/`to_port`.
- `port_name` remains a BoatSetting (independent of journeys — used between journeys).
- `MetricsService::getSettings()` checks for an active journey first. If one exists, its `from_port`/`to_port` are returned as `passage_from`/`passage_to`. Otherwise, empty strings.
- The "Current Passage" section in admin Settings is removed. Journey management moves to its own admin section.
- `trip_offset` remains a BoatSetting.

## GPX Route Handling

### Upload and storage

- GPX uploaded via admin journey forms (start or import).
- Raw file stored at `journeys/gpx/{journey-id}.gpx` on Laravel's default disk.
- On upload, `<rte>` element parsed — `<rtept>` waypoints extracted as `[{lat, lng, name?}, ...]` and stored in the `route_waypoints` JSON column for fast rendering.

### Map rendering

- Planned route rendered as a **dashed line** in muted blue (`oklch(0.65 0.10 240 / 0.5)`), distinct from the speed-coloured actual track.
- Waypoint markers shown as small dots along the route with names on hover.
- Displayed on: overlay maps (PiP + full), public dashboard map, and journey replay view — wherever the journey's actual track appears.
- When a journey is live, both planned route and actual track are visible simultaneously.

### Data flow

- Live views (overlay/dashboard): active journey's `route_waypoints` passed as an Inertia prop.
- Journey replay view: loaded with journey data.

## Routes

### New public routes

- `GET /journey/{slug}` — Journey replay view (map + timeline + metrics). Returns 403 if journey is not public and user is not authenticated.
- `GET /journey` — Redirects to the active journey's slug if one exists, otherwise 404.
- `GET /api/journey/{slug}/track` — JSON endpoint returning track points. For large tracks (>2000 points), the initial Inertia load sends a decimated version; this endpoint provides full-resolution segments by time range for the timeline scrubber.

### New admin routes

- `GET /admin` — Admin dashboard (new landing page).
- `GET /admin/journeys` — Journey list.
- `GET /admin/journeys/create` — Start journey form.
- `GET /admin/journeys/import` — Import from history form.
- `POST /admin/journeys` — Create/start journey.
- `POST /admin/journeys/import` — Dispatch import job.
- `GET /admin/journeys/{journey}/edit` — Edit journey.
- `PUT /admin/journeys/{journey}` — Update journey.
- `DELETE /admin/journeys/{journey}` — Delete journey.
- `POST /admin/journeys/{journey}/end` — End active journey.
- `POST /admin/journeys/{journey}/gpx` — Upload/replace GPX.

## Journey Replay View (`/journey/{slug}`)

### Layout

Map-dominant, full viewport — matching the existing public dashboard design language.

- **Full-screen Leaflet map** with the journey's speed-coloured track and planned GPX route (if uploaded).
- **Journey title overlay** (top-left): title, date, duration, distance.
- **Floating metric pills** (bottom-right): speed, heading, depth, wind, heel — values update as the user scrubs the timeline.
- **Timeline scrubber** (bottom): pinned strip with start/end times, a draggable playhead, speed-coloured progress bar, and play/pause/skip controls.
- **Boat icon** positioned on the track at the scrubbed point, rotated to heading.

### Data loading

- Initial Inertia props: journey metadata, route waypoints, decimated track (if >2000 points).
- Full-resolution track segments loaded via `/api/journey/{slug}/track` as the user scrubs into unloaded regions.
- All metrics for a given point in time come from the `journey_track_points` row at that timestamp.

### Interaction

- Dragging the playhead scrubs through the journey. The map pans to follow the boat icon. Metrics update to reflect that moment.
- Play button animates through the journey at accelerated speed (e.g. 1 point per 100ms).
- The full track polyline is always visible (so the user sees the complete route). The boat icon and metrics reflect the scrubbed position.

## Admin Dashboard (`GET /admin`)

A landing page at `/admin` providing a quick-glance overview:

- **Active Journey card** — if one exists: from → to, elapsed time, distance covered, live speed, "End Journey" button, link to journey list. If none: "Start Journey" / "Import from History" buttons.
- **Boat status strip** — current speed, heading, depth, battery voltage, status (Under Sail / Under Power / In Port). Links to Boat Metrics page.
- **Tracker status** — connection type (LTE/WiFi), signal strength, battery %. Links to Tracker page.
- **Recent journeys** — last 3-5 completed journeys with date, title, distance. Links to journey list.
- **Quick links** — Settings, Stream Monitor, Team.

Light, informational — no charts. Follows the existing admin light-theme design language (panels, data rows, compass-style accents).

## Live View Integration

### Dashboard & Overlay

- Continue to work as they do now — live data via WebSocket, GPS track from Prometheus rolling 12h window.
- When an active journey exists, the planned GPX route (dashed line) is rendered on the map alongside the live track.
- `passageFrom`/`passageTo` in the lower third come from the active journey via `MetricsService::getSettings()` (already wired through the WebSocket composable).

### MetricsPushCommand changes

After broadcasting metrics:
1. Check for active journey (cached query).
2. If active, write one `JourneyTrackPoint` row with current metrics.
3. Increment or reset stationary counter in cache.
4. If stationary counter reaches 480, auto-end the journey (backdate `ended_at`, trim stationary tail).

### What doesn't change

- GPS track on live views still comes from Prometheus (not the database).
- Weather data from weather API, not stored per-journey.
- WebSocket broadcasting, video feed, compass, all untouched.

## Models

### `Journey` (Eloquent)

- `hasMany(JourneyTrackPoint::class)`
- Scopes: `active()`, `completed()`, `public()`
- Accessor: `duration` (computed from started_at/ended_at)
- Accessor: `distance` (computed from track points, great-circle sum)
- Static: `current()` — returns the active journey or null (cached)
- Mutator: auto-generate slug from `from_port`/`to_port` on creation, handle uniqueness with suffix

### `JourneyTrackPoint` (Eloquent)

- `belongsTo(Journey::class)`
- Mass-assignable columns for bulk insert during import

## Jobs

### `ImportJourneyFromPrometheus`

- Queued job, dispatched when admin imports from history.
- Accepts: journey ID, start time, end time.
- Queries Prometheus `queryRange` for each metric over the time window at 30s step.
- Merges by timestamp, bulk-inserts in batches of 500.
- Updates journey status to `completed` on finish.

## Admin Navigation

Sidebar nav updated:
- **Boat** group: Dashboard (new), Settings, Journeys (new), Tracker, Boat Metrics, Stream Monitor.
