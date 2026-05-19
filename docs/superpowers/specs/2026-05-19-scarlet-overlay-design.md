# Scarlet Overlay: Design Specification

Real-time sailing telemetry system for the yacht Scarlet. Four surfaces: stream overlay, admin dashboard, public dashboard, and login page. All share the Outfit typeface, scarlet brand color `oklch(0.54 0.22 27)`, and rounded corners (10px/7px).

## Data Pipeline

OpenTelemetry metrics flow from onboard instruments (SignalK, GPS, ESP32 tracker) through an OTel Collector into Prometheus. Laravel reads Prometheus and pushes updates every 15 seconds via Laravel Reverb WebSocket to all connected clients.

### Available Metrics

| Source | Prefix | Key Metrics |
|--------|--------|-------------|
| SignalK | `boat_*`, `signalk_*` | Speed (SOG/STW), heading, depth, wind (true/apparent speed + angle + direction), attitude (heel, rudder), water temp, batteries (voltage, SoC, current), tank levels (fuel, water), engine RPM/hours/coolant, barometric pressure |
| GPS | `gps_*` | Latitude, longitude, altitude, satellite count, HDOP |
| ESP32 Tracker | `system_*` | Battery voltage/percentage, LTE/WiFi RSSI, uptime, heap, connection mode (realtime/saver) |
| Environment | `environment_*` | Cabin temperature, humidity (currently inactive, planned) |
| MQTT | `mqtt_*` | Message counts (currently inactive) |

### State Logic

| Condition | State |
|-----------|-------|
| Telemetry live, video feed active | Under way (video) |
| Telemetry live, no video feed | Under way (no video) |
| Telemetry older than 2 hours | Offline |
| Speed = 0, in port | Idle / In port |

Under Sail vs Under Power: determined by engine RPM (`signalk_propulsion_*_revolutions`). RPM > 0 = Under Power.

---

## 1. Stream Overlay

Fixed 1920x1080 layout for OBS compositing. Video is the hero (~90% unobstructed). Vanilla JS + Blade, no framework.

### Design Language

Broadcast-style (like sports broadcasts), friendly and welcoming.

- **Chrome:** warm-tinted glass, `oklch(0.10 0.008 40 / 0.55)` at 55% opacity for State 1, `oklch(0.08 0.008 40 / 0.72)` at 72% for map-background states. `backdrop-filter: blur(24px)`.
- **Typography:** Outfit only. `tabular-nums` for all numeric data. No monospace.
- **Text colors:** bright `oklch(0.96 0.005 70)`, mid `oklch(0.75 0.008 70)`, dim `oklch(0.62 0.008 70)`.
- **Status pills:** green for sail `oklch(0.78 0.12 155)`, amber for power/offline `oklch(0.75 0.10 70)`, blue for in port `oklch(0.72 0.08 230)`.
- Elements hug edges with 10px inset.

### State 1: Video + Telemetry Live

- **Top-left:** PiP map (240x175px) with OpenSeaMap tiles, speed-colored track (blue 0kn, green 5kn, scarlet 10kn), boat icon rotated to heading. LIVE badge adjacent.
- **Top-right:** Weather pills (temperature, sea state, wind, waves). Data from Open Meteo for current GPS position.
- **Bottom:** Single-row lower third spanning full width. Left: scarlet brand tab with angled diagonal edge. Inline metrics: speed, heading, depth. Status pill (Under Sail / Under Power). Centered passage text (from, to). Right-aligned clock.
- **Not shown:** Battery level, MMSI, wind in lower third (redundant with weather strip).

### State 2: No Video, Telemetry Live

- Full-screen Leaflet map replaces video as hero.
- Same lower third and weather pills, chrome at 72% opacity.
- LIVE badge extends with "Video Offline" tag.
- Coordinate badge bottom-left.
- Speed color gradient legend bottom-left.

### State 3: Telemetry Offline

Triggers when telemetry > 2 hours old.

- Map dimmed to 60%, track/boat faded 50%.
- OFFLINE badge (amber dot) with "Telemetry Unavailable" extension.
- Metrics show dashes.
- "Last update received" card centered on map.
- Weather pills dimmed. Status pill amber "Offline".

### State 4: Idle / In Port

- Full map, no track line, boat marker without heading rotation.
- Weather pills top-right (Open Meteo for GPS location).
- LIVE badge extends with "Updated HH:MM".
- Lower third: brand tab, blue "In Port" pill, "Currently at [port name]" (from admin settings), clock.
- No speed/heading/depth metrics.

### Map Behavior (All States)

- Leaflet with OpenSeaMap tiles, served from local tileserver.
- Boat path polyline colored by speed: blue (0kn) through green (5kn) to scarlet (10kn).
- Boat icon SVG rotated to current heading.
- Appropriate zoom level to show recent track without losing detail.

---

## 2. Admin Dashboard

Light/neutral product UI. Laravel + Vue 3 + Inertia.js + Tailwind CSS 4.

### Design Language

- **Background:** `oklch(0.97 0.003 70)`.
- **Surface (cards/sections):** `oklch(1 0 0)` with 1px border `oklch(0.90 0.005 70)`, `border-radius: 10px`.
- **Text:** primary `oklch(0.18 0.005 40)`, secondary `oklch(0.45 0.005 40)`, dim `oklch(0.60 0.005 40)`.
- **Accent colors:** scarlet `oklch(0.54 0.22 27)`, green `oklch(0.62 0.15 155)`, amber `oklch(0.70 0.14 70)`.
- **Typography:** Outfit, `tabular-nums` for data. No monospace.
- Inline save buttons per section. No global save.
- Charts use area fills with gradient opacity, consistent axis labels, and time ranges.

### Layout

Fixed sidebar (220px) + scrollable main content (max-width 820px).

**Sidebar:**
- Brand header: "Scarlet" in scarlet, "Admin" subtitle.
- Grouped navigation:
  - **Boat:** Settings, Tracker, Boat Metrics
  - **Links:** Broadcast Overlay (external, new tab), Public Dashboard (external, new tab). Same styling as other nav items, no dimming. All section labels use consistent styling with no dividers between groups.
  - **Account:** Profile, Team
- Footer: user avatar (initials), name, role, sign out link.

### Authentication

Centered login card on light grey background.

- Email + password fields.
- "Remember me" checkbox + "Forgot password?" link.
- "or" divider.
- "Sign in with passkey" button (WebAuthn).
- Multi-user system with invite flow for new users.

### Page: Settings

Boat-wide configuration. Three sections, each with inline save.

- **Boat Identity:** Name (text), MMSI (9-digit numeric).
- **Current Passage:** From (text), To (text). Displayed when under way.
- **Port Settings:** Current Port (text). Displayed when idle/in port.

### Page: Tracker

ESP32 tracker device monitoring. Live data indicator with "last update Xs ago".

- **Device status strip** (4-column grid):
  - Status: Connected/Disconnected badge + uptime duration.
  - Connection: LTE or WiFi, with standby indicator for the other.
  - Mode: Realtime (15s intervals) or Saver.
  - Battery: percentage + voltage + charging state. Sparkline trend.

- **Signal Strength chart** (half-width): 1h trend. LTE as solid scarlet line, WiFi as dashed blue line. Current dBm values in subtitle. Gradient fill under LTE line.

- **GPS Quality chart** (half-width): 1h trend. Satellite count as green area chart. HDOP rating in subtitle.

- **Cabin Temperature chart** (half-width): 6h trend. Amber area chart. Current reading in subtitle.

- **Humidity chart** (half-width): 6h trend. Blue area chart. Current reading in subtitle.

- **Device Details** table: WiFi network + RSSI, LTE carrier + band, IP address, firmware version, last reboot time.

### Page: Boat Metrics

All interesting boat metrics with visual treatments. Live data indicator.

- **Critical metrics strip** (5-column grid with sparklines):
  - Speed (SOG, scarlet sparkline)
  - Depth (below keel, blue sparkline)
  - Wind (true speed + direction, green sparkline)
  - House Battery (voltage + SoC%, green sparkline)
  - Heading (degrees magnetic, neutral sparkline)

- **Barometric Pressure chart** (half-width): 24h trend. Blue area chart. Current hPa in subtitle.

- **Battery State of Charge chart** (half-width): 24h trend. Green area chart. Current percentage in subtitle.

- **Tank Levels** section: Fuel (amber fill bar, percentage) and Fresh Water (blue fill bar, percentage) side by side.

- **Wind panel** (half-width): SVG compass rose with scarlet arrow pointing to true wind direction. Tick marks at 30-degree intervals, cardinal labels. Data alongside: true direction, true speed, apparent speed, apparent angle.

- **Navigation panel** (half-width): SVG compass with blue arrow pointing to heading. Data alongside: COG, SOG, STW, heel angle, trip log.

- **Power Balance** section (full-width): Horizontal fill bars comparing Solar generation (green, watts) vs Load consumption (amber, watts). Net power indicator with charging badge. Details: house battery, engine battery, solar current, load current.

- **Water & Air Temperature chart** (half-width): 24h dual-line trend. Blue for water, amber for air. Legend in axis.

- **Engine panel** (half-width): Status (running/off), RPM, coolant temp, engine hours, rudder angle, rate of turn. Values show dashes when engine is off.

### Page: Profile

Personal account settings.

- **Your Details:** Name (text), Email (email). Inline save.
- **Change Password:** Current password, new password, confirm new password. Single-column layout. "Update password" button.
- **Passkeys:** Table with name, registered date, last used date, remove button. "Register new" button in section header.

### Page: Team

Multi-user management. Owner and Crew roles.

- **Members** table: User (avatar initials + name + email), role badge (Owner in scarlet, Crew in neutral), last active, remove button (not shown for self). "Invite member" button in section header.
- **Pending Invites** table: Email, sent date, resend button.

---

## 3. Public Dashboard

Full-viewport interactive map for casual viewers. Vue 3 + Leaflet. Same dark chrome design language as overlay.

### Design Language

- **Chrome:** `oklch(0.08 0.008 40 / 0.72)` with `backdrop-filter: blur(24px)`.
- **Text:** same bright/mid/dim scale as overlay.
- Floating clusters positioned in four corners over full-viewport Leaflet map.
- All labels must be readable for a mixed audience (no sailing abbreviations like AWA/AWS/VMG/BARO).

### Layout: Four-Corner Floating Clusters

- **Top-left:** LIVE badge (matching overlay style) + map controls (zoom +/-, re-centre on boat button).

- **Top-right:** Weather pills. Hero pill with weather icon + temperature + condition text. Secondary pills: Sea State, Wind, Waves.

- **Bottom-left:** Coordinate badge (lat/lon) + speed color gradient legend (0kn blue, 5kn green, 10kn scarlet).

- **Bottom-right:** Sailing instruments as compound pills. Apparent Wind (speed + angle in one pill). Heel. Pressure. Trip distance.

- **Bottom spanning:** Lower third bar matching overlay design. Scarlet brand tab, speed/heading/depth metrics, status pill, centered passage text, clock.

### Map Behavior

- Full-viewport Leaflet map with OpenSeaMap tiles from local tileserver.
- Boat path polyline colored by speed (same gradient as overlay).
- Boat icon SVG rotated to heading.
- User can pan and zoom freely.
- WebSocket updates move boat marker and extend track polyline but do NOT reset the user's current pan/zoom position.
- Re-centre button snaps back to boat without changing zoom level.
- Other states (offline, in port) and mobile bottom-sheet layout to be designed during implementation, carrying the same state logic from overlay.

---

## 4. Implementation Notes

### Tech Stack

| Surface | Rendering | Styling | Interactivity |
|---------|-----------|---------|---------------|
| Stream overlay | Blade + vanilla JS | Scoped CSS (no Tailwind) | WebSocket, Leaflet |
| Admin | Vue 3 + Inertia.js | Tailwind CSS 4 | Inertia router, WebSocket |
| Public dashboard | Vue 3 | Tailwind CSS 4 | WebSocket, Leaflet |
| Login | Vue 3 + Inertia.js | Tailwind CSS 4 | WebAuthn API |

### WebSocket

All surfaces connect to Laravel Reverb. Updates pushed every 15 seconds. Channel structure TBD during implementation.

### Authentication

- Email + password with bcrypt.
- WebAuthn passkeys as secondary method.
- Multi-user with Owner/Crew roles.
- Invite flow: Owner sends email invite, recipient creates account.
- Session-based auth via Laravel Sanctum or session driver.

### Local Tileserver

OpenSeaMap tiles served locally to avoid external dependencies at sea. Tiles pre-downloaded for expected cruising area. Tileserver runs alongside the application.

### Mockup Reference Files

All approved mockups in `.superpowers/brainstorm/37477-1779186435/content/`:

| File | Surface |
|------|---------|
| `05-overlay-broadcast.html` | Overlay State 1 (video + telemetry) |
| `06-overlay-no-video.html` | Overlay State 2 (no video) |
| `07-overlay-offline.html` | Overlay State 3 (offline) |
| `08-overlay-idle.html` | Overlay State 4 (in port) |
| `11-public-dashboard-map.html` | Public dashboard |
| `12-admin-login.html` | Admin login |
| `13-admin-dashboard.html` | Admin (all 5 pages) |
