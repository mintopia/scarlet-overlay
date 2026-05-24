# Admin Restructure Design Spec

## Overview

Restructure the admin interface from a flat list of organically-grown pages into a coherent, audience-grouped navigation with purpose-built dashboards. The existing functionality is preserved but reorganized, with three new overview pages and a redesigned sidebar.

## Information Architecture

### Sidebar Navigation (grouped, flat)

```
Dashboard (main overview)

SKIPPER
  Skipper Overview
  Journeys
  Weather
  Ship's Log

BROADCAST
  Broadcast

SYSTEM
  Tracker
  Settings
  Team

TOOLS
  Explore
```

**Profile** stays in the user menu dropdown (top-right), not the sidebar.

### Page Changes from Current

| Current Page | New Location | Changes |
|---|---|---|
| Dashboard | Dashboard | Complete redesign (compass hero layout) |
| Settings | System > Settings | No functional changes |
| Journeys | Skipper > Journeys | No functional changes |
| Log | Skipper > Ship's Log | Renamed |
| Tracker | System > Tracker | No functional changes |
| Boat Metrics | Removed | Absorbed into Skipper Overview; charts available via Explore |
| Weather | Skipper > Weather | No functional changes |
| Explore | Tools > Explore | No functional changes; charts across all pages link here |
| Stream Monitor | Broadcast | Redesigned with video embed |
| Profile | User menu dropdown | Unchanged |
| Team | System > Team | Unchanged |

### New Pages

1. **Dashboard** (redesigned) at `/admin`
2. **Skipper Overview** at `/admin/skipper`
3. **Broadcast** (replaces Stream Monitor) at `/admin/broadcast`

## Design System

Full design system documented in `/DESIGN.md` at project root (admin pages only).

### Key Decisions

- **Fonts**: Nunito Sans (numbers/values), DM Sans (text/labels)
- **Colors**: OKLCH throughout, hue 205 neutrals, five semantic roles (teal, amber, blue, green, scarlet)
- **Panels**: 16px radius, subtle shadow, flex column for bottom-pinned content
- **Value hierarchy**: Primary (26-72px/600-700), Secondary (16px/600), Tertiary (12px/500)
- **Charts**: Sparklines are clickable links to Explore with the relevant metric pre-selected. No separate "Explore" buttons on chart panels.
- **Theme support**: Light, Dark, Night Watch (existing system, tokens updated to match new palette)

## Data Sources

All data comes from Prometheus. SignalK metrics (`scarlet_signalk_*`) are the primary source for accuracy. Fallback to `scarlet_boat_*`/`scarlet_gps_*` when SignalK data is unavailable.

### Metric Mapping

**Navigation:**
| Display | Metric | Conversion |
|---|---|---|
| SOG | `scarlet_signalk_navigation_speedOverGround` | m/s to knots (* 1.94384) |
| SOW | `scarlet_signalk_navigation_speedThroughWater` | m/s to knots |
| Heading (M) | `scarlet_signalk_navigation_headingMagnetic` | radians to degrees |
| Heading (T) | `scarlet_signalk_navigation_headingTrue` | radians to degrees |
| COG | `scarlet_signalk_navigation_courseOverGroundTrue` | radians to degrees |
| Depth | `scarlet_boat_depth_meters` | direct (metres) |
| Heel | `scarlet_signalk_navigation_attitude_roll` | radians to degrees |
| Rudder | `scarlet_signalk_steering_rudderAngle` | radians to degrees |
| ROT | `scarlet_signalk_navigation_rateOfTurn` | radians/s to degrees/min |
| Trip log | `scarlet_signalk_navigation_trip_log` | metres to nautical miles |
| DTW | `scarlet_signalk_navigation_courseGreatCircle_nextPoint_distance` | metres to nm |
| TTG | `scarlet_signalk_navigation_courseGreatCircle_nextPoint_timeToGo` | seconds to h:mm |
| Position | `scarlet_signalk_navigation_position_latitude/longitude` | decimal degrees to DM format |

**Wind:**
| Display | Metric | Notes |
|---|---|---|
| Apparent wind angle | `scarlet_signalk_environment_wind_angleApparent` | radians to degrees |
| Apparent wind speed | `scarlet_signalk_environment_wind_speedApparent` | m/s to knots |
| True wind speed | `scarlet_boat_wind_speed_kn` | direct (tracker calculates true from apparent) |
| True wind direction | `scarlet_boat_wind_direction_deg` | direct |

True wind speed and angle are not available as direct SignalK metrics. The tracker calculates true wind from apparent wind + boat speed/heading via `scarlet_boat_wind_*`. Beaufort scale and point of sail are derived client-side from TWS and TWA.

**Batteries & Power:**
| Display | Metric |
|---|---|
| House SoC | `scarlet_signalk_electrical_batteries_0_capacity_stateOfCharge` (0-1 to %) |
| House voltage | `scarlet_signalk_electrical_batteries_0_voltage` |
| House current | `scarlet_signalk_electrical_batteries_0_current` |
| Engine voltage | `scarlet_signalk_electrical_batteries_1_voltage` |
| Power (watts) | Calculated: voltage * current |

**Tanks:**
| Display | Metric |
|---|---|
| Fuel | `scarlet_signalk_tanks_fuel_currentLevel` (0-1 to %) |
| Water | `scarlet_mqtt_percent{topic="watertank"}` (may have no data) |

**Environment:**
| Display | Metric |
|---|---|
| Air temperature | `scarlet_weather_temperature_celsius` (OpenMeteo) |
| Sea temperature | `scarlet_signalk_environment_water_temperature` (Kelvin to Celsius) |
| Pressure | `scarlet_weather_pressure_hpa` (OpenMeteo) |
| Main Cabin temp | `scarlet_mqtt_temperature{topic="zigbee2mqtt/Main Cabin"}` |
| Main Cabin humidity | `scarlet_mqtt_humidity{topic="zigbee2mqtt/Main Cabin"}` |
| Forepeak temp | `scarlet_environment_temperature_celsius` |
| Forepeak humidity | `scarlet_environment_humidity_percent` |
| Quarterberth temp | `scarlet_mqtt_temperature{topic="zigbee2mqtt/Quarterberth"}` |
| Quarterberth humidity | `scarlet_mqtt_humidity{topic="zigbee2mqtt/Quarterberth"}` |
| Current set | `scarlet_signalk_environment_current_setTrue` (radians to degrees) |
| Current drift | `scarlet_signalk_environment_current_drift` (m/s to knots) |

**Weather (OpenMeteo forecast):**
| Display | Metric |
|---|---|
| Forecast wind speed | `scarlet_weather_wind_speed_kn` |
| Forecast wind direction | `scarlet_weather_wind_direction_deg` |
| Wave height | `scarlet_weather_wave_height_m` |
| Wave period | `scarlet_weather_wave_period_s` |
| Wave direction | `scarlet_weather_wave_direction_deg` |
| Ocean current speed | `scarlet_weather_current_speed_kn` |
| Ocean current direction | `scarlet_weather_current_direction_deg` |

**Stream (SRT):**
| Display | Metric |
|---|---|
| Server up | `scarlet_srt_up` |
| Publisher connected | `scarlet_srt_publisher_connected` |
| Publisher bitrate | `scarlet_srt_publisher_bitrate_bps` (bps to Mbps) |
| Publisher RTT | `scarlet_srt_publisher_rtt_ms` |
| Publisher drops | `scarlet_srt_publisher_dropped_packets_total` |
| Publisher latency | `scarlet_srt_publisher_latency_ms` |

**Boat Status (derived):**

Sailing mode is derived client-side (existing logic in `useScarletMetrics.js`):
- SOG < 0.5 knots AND port name configured = **In Port**
- Battery current > 0 = **Under Power**
- Otherwise = **Under Sail**

## Page Designs

### 1. Main Dashboard (`/admin`)

The entry point. A high-level overview spanning all audience groups. Answers "is everything OK?" at a glance.

**Layout**: Single page, no tabs. Status ribbon at top, compass hero section, journey map, ship status + weather panels below.

**Status ribbon**: Sailing state badge (Under Sail / Under Power / In Port), boat name "Scarlet" in brand color, journey summary (from/to, distance remaining), health indicators for Tracker and Stream (green dot + label).

**Compass rose (hero)**: Large SVG compass (340px) as the visual centerpiece. Boat always pointing up. Heading shown as solid arrow, COG as dashed line. Point of sail zones as colored wedges (no-go red, close hauled teal, beam/broad reach amber, running green-amber). Wind arrow with feather marks and animated dash. Boat silhouette with wake lines.

Below compass: point of sail label in amber text, HDG/TWD footer.

**Instrument readings**: Flanking the compass. Navigation instruments (SOG, SOW, HDG, Depth) on the left in teal. True wind instruments (TWS, TWA) on the right in amber. 46px Nunito Sans weight 600. Inline label-value pairs, not cards. Apparent wind is not shown on the main dashboard (skippers sail by true wind).

**Journey section**: Map (Leaflet) showing current position with GPS track and next waypoint. Navigation data panel (DTW, TTG, ETA) on the right side of the map. Lat/lon position at the bottom.

**Bottom row (2 panels)**:
- **Ship Status**: Battery/Fuel/Water level bars (10px, gradient fills with glow), power sparkline (charge green, discharge scarlet)
- **Weather**: Sky gradient hero strip (flush with panel top, no title), temperature 44px, condition icon + description, sea temp. Below: forecast wind, waves, current as data rows. Pressure sparkline at bottom.

### 2. Skipper Overview (`/admin/skipper`)

Deep instrument view for the skipper. All sailing data with historical sparklines and operational information.

**Layout**: Two rows of three panels each.

**Primary row (3 equal panels)**:

**Heading & Course** (centered title, teal):
- SVG compass rose (165px) with HDG arrow + COG dashed line
- HDG and COG readings below (26px/600) in a two-cell grid with divider
- Lat/lon position centered at bottom

**Speed** (left-aligned title):
- SOG as hero number (72px/700, teal)
- SOW as secondary line below
- Speed sparkline
- "NAVIGATION" sub-section label (teal), then data rows: Next Waypoint (DTW), Time to Go, ETA
- Trip Distance below a divider

**True Wind** (centered title, amber):
- SVG wind dial (175px): TWS in center circle (26px/800), TWA arrow with feathers
- Point of sail zones as colored background wedges on the dial
- Point of sail name in amber text below
- Beaufort number (F4) below

**Secondary row (3 equal panels)**:

**Systems**:
- Battery / Fuel / Water level bars (10px, gradient fills)
- Divider, then House Battery voltage and Engine Battery voltage as data rows

**Depth & Power**:
- Depth sparkline (44px, blue) with current value (18px/600)
- Power sparkline (44px, green/scarlet split) with current value
- Charts are clickable links to Explore

**Weather**:
- No panel title. Sky gradient hero flush with top border
- Temperature (44px), condition icon, "Partly Cloudy", sea temp
- Forecast wind, waves, current as data rows
- Pressure sparkline with current value at bottom

### 3. Broadcast (`/admin/broadcast`)

Stream monitoring with video preview.

**Header**: Page title + external links to Overlay and Public Dashboard (open in new tabs).

**Status hero**: Large pulsing green indicator (52px), "Live" text with connection details + stream duration, at-a-glance stats (Bitrate, Drops).

When publisher disconnected: grey indicator, "Offline" text, stats show zeroes.

**Main section**: Two columns.
- **Left (wider)**: 16:9 HLS video embed showing the live stream preview. Uses existing `useVideoFeed.js` composable.
- **Right (320px)**: Three vertically stacked compact panels matching the video height:
  - **Bitrate** (teal): current value (22px) + sparkline
  - **Dropped Packets** (scarlet): session count (22px) + spike chart
  - **RTT** (amber): current value (22px) + sparkline

All chart panels are clickable links to Explore.

## Interaction Patterns

### Sparkline Charts

All sparkline charts across all pages:
- Are clickable, linking to the Explore page with the relevant metric(s) pre-selected
- Show the last 1 hour of data by default
- Update in real-time via WebSocket (existing Laravel Reverb infrastructure)
- Have a current-value dot at the right end (semantic color fill, white stroke)

### Real-Time Updates

All pages receive live data updates via the existing WebSocket infrastructure (Laravel Reverb + Echo). The existing `useScarletMetrics.js` composable handles metric subscriptions. Sparklines append new points and shift the window.

### Theme Support

All new pages support the existing three themes (Light, Dark, Night Watch) via CSS custom properties. The token values in DESIGN.md define the light theme; dark and night overrides follow the existing pattern in `app.css`.

### Empty States

- **No active journey**: Speed panel shows SOG/SOW but nav section shows "No active journey" with a link to Journeys page
- **Stream offline**: Broadcast status hero shows grey/offline state, video embed shows "No stream" placeholder
- **Sensor offline**: Missing data shows "—" instead of a value (e.g., water tank level)
- **Cabin sensors offline**: MQTT sensors may not be connected; show "—" for temp/humidity

## Implementation Notes

### Files to Create
- `resources/js/Pages/Admin/SkipperOverview.vue`
- Update `resources/js/Pages/Admin/Dashboard.vue` (complete redesign)
- Update `resources/js/Pages/Admin/StreamMonitor.vue` (rename to Broadcast, redesign)

### Files to Modify
- `resources/js/Layouts/AdminLayout.vue` (sidebar navigation restructure)
- `resources/css/app.css` (update tokens to match DESIGN.md, add Nunito Sans)
- `routes/web.php` (add `/admin/skipper` route)
- `app/Http/Controllers/Admin/AdminDashboardController.php` (updated data for new dashboard)
- `config/scarlet.php` (any new metric mappings needed)

### Font Loading
Add Nunito Sans via Google Fonts or self-host. Load weights 300, 400, 500, 600, 700, 800. Keep DM Sans for body text. Remove Outfit (current font) or keep as fallback during transition.

### Compass Rose & Wind Dial
These are custom SVG components. Build as Vue components:
- `CompassRose.vue` — accepts heading, COG as props, renders SVG
- `WindDial.vue` — accepts TWS, TWA as props, renders SVG with point of sail zones and Beaufort calculation

### Beaufort Scale
Client-side lookup table from TWS (knots) to Beaufort number + description. Standard scale.

### Point of Sail
Client-side derivation from TWA:
- 0-45°: In Irons / No-go zone
- 45-60°: Close Hauled
- 60-80°: Close Reach
- 80-100°: Beam Reach
- 100-150°: Broad Reach
- 150-170°: Running
- 170-180°: Dead Run

### Existing Pages (unchanged)
Journeys, Weather, Ship's Log (renamed from Log), Tracker, Settings, Team, Profile, Explore all retain their current functionality. They get the updated sidebar navigation but no content changes in this phase.
