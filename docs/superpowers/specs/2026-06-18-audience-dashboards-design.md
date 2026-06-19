# Audience Dashboards — Design Spec (Part 5)

**Status:** Design locked 2026-06-18 · awaiting spec review before `writing-plans`
**Workspace:** `/home/workspace/scarlet-overlay` (Laravel 12 + Inertia/Vue 3 + Octane)
**Related:** ADR 0002 (canonical catalog), ADR 0003 (two-layer normalization), ADR 0004 (custom SVG charts),
ADR 0005 (operational-metrics read path). Mockups: `docs/superpowers/mockups/audience-dashboards/` (+ `INDEX.md`).
Memory: `project_audience_dashboards`, `reference_signalk_unmapped_catalog`.

## 1. Overview

Four bespoke, role-scoped **Audience Dashboards**, hand-coded as Inertia/Vue pages, each consuming metrics
through the shared read path (ADR 0005). There is **no user-facing dashboard builder** — curation is
editorial (designed with Jess, implemented in code). The governing aim throughout: **avoid a "wall of
cards"** by giving each dashboard real visual hierarchy.

| Dashboard | Hero / organizing principle | Audience | Mockup (locked) |
|-----------|-----------------------------|----------|-----------------|
| **Main** | Map-led (nav chart hero + corner clusters) | Generalist viewer | `main-v9-polish.html` |
| **Skipper** | Instrument-led (compass/POS hero) | Skipper/captain, sailing | `skipper-v4.html` |
| **Ops** | Balanced multi-zone (no forced hero) | Endurance + habitability | `ops-v8.html` |
| **Tech** | Broadcast signal-chain hero (+ controls) | Technical operator | `tech-v3.html` |

### Non-goals

- No drag-drop builder, no per-user DB-persisted widget config.
- No reshaping of the data layer; dashboards **consume** the read contract, they do not add raw PromQL
  (ADR 0005). Surfacing currently-unmapped series is **additive data-layer work** (ADR 0002), tracked as a
  dependency here, not built by this program.
- Deferred to a later pass (whole set): an **alarm/threshold tier** and a **Night-Watch contrast** audit.

## 2. Shared foundations

### 2.1 Design system

Tokens from `PRODUCT.md` / `DESIGN.md`. OKLCH palette — **teal** = nav/SOG, **amber** = wind/fuel,
**blue** = depth/water, **green** = battery/positive/charging, **scarlet** = brand/discharge/alert,
**pink** accent. Numbers in **Nunito Sans** (700, negative letter-spacing), labels in **DM Sans**
(uppercase, tracked). Light theme default; dark + Night-Watch variants follow the same token contract.
Section chrome: `seclabel` (10px/800/tracked uppercase), region dividers, `num`/`u` for value+unit.

### 2.2 Read path (ADR 0005)

All values — physical and operational — come from VictoriaMetrics **via the shared metrics service**,
resolved through the admin **source-mapping** (logical data-source → VM series, with fallbacks/priority/
units/staleness). Every value carries the read-contract envelope **{ value, age, stale, trend }**.
Controllers pass Inertia props; **no raw PromQL in dashboard controllers**. Live updates ride the existing
Echo/Reverb broadcast (`metrics` channel) where already wired; otherwise Inertia poll/reload.

**Recency/staleness display.** Stale values render in a muted/aged treatment with an age hint (the admin
fuel/water recency pattern, already shipped). A dead source (e.g. tracker offline dock-side, SRT scrape
down) reads as `stale`/idle and the UI shows an idle state rather than a stale number presented as live.

### 2.3 `<TrendChart>` (ADR 0004)

One reusable custom-SVG component extending `Sparkline.vue`, covering every graph in the set. Variants:

- **line / area** — standard trend (e.g. fuel, water, pressure, bitrate).
- **inverted water-fill** — depth as a column filling from the top; seabed line + tan fill below
  (Main + Skipper depth).
- **signed bipolar charge/discharge** — power trend coloured by flow sign against the zero baseline:
  **green ≥ 0 (charging), scarlet < 0 (discharging)**, split cleanly at zero crossings (SVG clipPath
  above/below baseline). Applies to **every House Battery + EcoFlow power trend on all four dashboards**
  (supersedes the single-colour sparks in earlier mockups).
- **time-axis** — clock-time labels; tanks use **6-hr median** smoothing.
- **event markers** — vertical bars overlaid on a line; height ∝ a bucketed `increase()`. Used for Tech's
  **dropped-frame markers** on the publish-bitrate trend
  (`increase(scarlet_srt_publisher_dropped_packets_total[bucket])`).

Reduced-motion honoured; component is responsible for its own a11y summary + stacking behaviour.

### 2.4 Reused components

`CompassRose.vue` (combined heading + point-of-sail + wind zone), `Sparkline.vue` (base of `<TrendChart>`),
`LevelBar.vue`, and the currently-orphaned `TemperatureGauge.vue` (Ops cabin gauges). Instruments stay
custom (ADR 0004).

### 2.5 Map

Leaflet with **EMODnet/OpenSeaMap bathymetry contour tiles** via the existing `OpenSeaMapService` /
`/openseamap/{z}/{x}/{y}` route. Main = full hero with floating corner clusters (public-dashboard chrome);
Skipper = contoured chart drawing real route legs + XTE; Ops = supporting map in the position band.

### 2.6 Control actions (Tech only)

Write-actions reuse existing endpoints, all behind `auth` middleware (no policies today):

- **Stop/Start Stream** = MediaMTX pull toggle → `MediaMtxService::syncLiveSource()` via
  `POST admin.broadcast.pull { enabled }`.
- **Refresh Overlay** = `ForceReload` event (broadcast on the `metrics` channel) via
  `POST admin.settings.force-reload`.

Each action **must** open a **styled HTML modal confirm** — `confirm()` is banned repo-wide
(`feedback_no_browser_confirm`). Reuse the inline `<Teleport>` modal pattern from `Settings.vue`
(focus-trap, Escape-to-close, disabled-while-pending button). Factor a small shared confirm-modal
component if the duplication warrants it during implementation.

### 2.7 Responsive

Each dashboard defines its own breakpoints (e.g. Main stacks at <880px with the map on top; Ops/Tech grids
collapse to single column at <960px). Stacking order is content-priority, not source order. The signal
chain (Tech) reflows from horizontal flow to vertical stack with rotated connectors.

## 3. Per-dashboard specs

### 3.1 Main — map-led (`main-v9-polish.html`)

Hero = a large nav chart with **four floating corner clusters** (weather TL / position TR / SOG headline +
COG/HDG BL / waypoint + TTG + ETA BR) in public-dashboard chrome. A right **instrument rail**
(`minmax(280px,1fr)`, map stretches to its height) stacks: `CompassRose` with active **Point of Sail**
readout (e.g. "Close Hauled") + brightened wind zone; through-water speed with the **SOG vs STW delta**
graph (band = current; "fair/foul tide" stated as a word, not colour-only); a **depth** water-column graph
(fills from the top, seabed line + tan below). Footer = three substantial trend graphs — **House Battery**
(SOC/V/Power, signed bipolar spark), **Fuel**, **Water** — on clock-time axes, tanks on 6-hr median.
Responsive: stacks at <880px (map on top).

### 3.2 Skipper — instrument-led (`skipper-v4.html`)

Hero = `CompassRose`/POS rose + a **dense sailing block** (SOG/STW/VMG + tidal set & drift + fair/foul
delta; speed graph paired beside a compact instrument list: TWS/AWA/heel/pitch/rate-of-turn/trip).
Secondary = a prominent **contoured chart** drawing real **route legs** (prev→active→next→following, active
leg bold, waypoints labelled, XTE offset) + a dense **nav panel** (heading T/M, COG, variation,
rate-of-turn, bearing-to-WP, track bearing, XTE, DTW, TTG, VMG, trip) + an **autopilot status** strip
(state + rudder angle + locked heading). Safety trends row (Depth water-column + barometric Pressure,
~78px). Footer (House SOC/V/Power, Engine V, Fuel, ~70px). **Dense by design** (approved).

### 3.3 Ops — balanced multi-zone (`ops-v8.html`)

Three labelled regions; grouping + varied form do the work a hero does elsewhere. Purpose: *endurance (can
we keep going) + habitability (are we comfortable)* — a periodic scanning check.

1. **Endurance** — `endgrid` [power 1.55fr | tanks 1fr, divider]. Power = House Battery + EcoFlow side by
   side (SOC + runtime/to-full + V/W + tall ~104px bipolar charge/discharge trend). Tanks = Fuel + Water as
   **trend graphs** with time axes + %/days-remaining.
2. **Climate** — current outside weather cell (icon+temp+condition) + 3 cabin gauges (temp+humidity) +
   sea-temp gauge, in one flex row.
3. **Conditions & Position** — weather band [Atmosphere 4 rows | Sea State 4 rows | Forecast 5 cells] +
   `posband` [big map 1.8fr | single-column Sailing & Nav 0.85fr].

### 3.4 Tech — unified systems console (`tech-v3.html`)

Absorbs the existing `Broadcast.vue` monitor + start/stop and `Settings.vue` force-reload into one role
dashboard (the old admin control bits are superseded by it, or kept as deep-dives).

1. **Broadcast (hero)** — horizontal **signal chain**: Boat Encoder → [SRT publish: bitrate/RTT/dropped] →
   belabox relay (de1) → [SRT pull: bitrate/RTT/latency] → MediaMTX (pull ON) → Overlay. Animated flow on
   active links; node state dots (green ok / scarlet live / grey idle). The two **control levers**
   (Stop/Start Stream, Refresh Overlay) dock to the region header. Hero foot = publish-bitrate 1h
   `<TrendChart>` with **dropped-frame markers** + stats (bitrate / dropped-1h / latency).
2. **Tracker · ESP32** — device-health panel: REALTIME/Saver mode pill + battery (V + USB-powered chip) +
   uptime/temp | LTE signal bars (carrier · RAT, RSSI dBm, quality n/31, multi-carrier scan noted) + WiFi
   (ssid, RSSI) + a CPU / free-heap / humidity strip. All from `scarlet_system_*`.
3. **Power** — House + EcoFlow twin **bipolar** power trends (same component as Ops/Main).

**Electrical switch-bank states dropped** (not needed).

## 4. Data sources & gaps

### 4.1 Available now (mapped or directly queryable)

- **Power/physical** — House Battery (SOC/V/W), Fuel `scarlet_signalk_tanks_fuel_0_currentLevel` (or MQTT
  `tanklevel` fallback), Water `scarlet_signalk_tanks_freshWater_0_currentLevel` (or MQTT `watertank`), sea
  temp `scarlet_signalk_environment_water_temperature`, cabin temp/humidity
  `scarlet_mqtt_{temperature,humidity}{topic="zigbee2mqtt/{Forepeak cabin|Quarterberth|Main Cabin}"}`.
- **EcoFlow** — MQTT firehose `scarlet_mqtt_value{topic="ecoflow/<serial>_<report>/<field>"}`; **must be
  curated to a named subset** (SOC, in/out watts, remainTime, V/A, temp) — see `ecoflow-curated-set`.
- **Streaming (Tech)** — `scarlet_srt_*` (publisher + consumer), VM scrapes directly. Read via the service
  (ADR 0005).
- **ESP32 tracker (Tech)** — `scarlet_system_*` (battery/CPU/heap/temp/humidity/LTE/WiFi/mode/uptime/usb).
  Dedupe on `job=boat-tracker` + current `mode`; ignore the `otel-collector` duplicate series.

### 4.2 Gaps — additive data-layer work (ADR 0002 dependency)

SignalK publishes far more nav/steering/current than `config/scarlet.php` maps. Skipper/Main need these
**surfaced into the read path first**: XTE (`navigation_courseGreatCircle_crossTrackError`), bearings
(`nextPoint_bearingTrue`, `bearingTrackTrue`), route leg positions (prev/next/following),
distance/TTG/VMG, tidal current (`environment_current_setTrue`/`_drift`), steering
(`steering_autopilot_state`, `steering_rudderAngle`), `navigation_rateOfTurn`, depth
`_belowTransducer`. Full list in `reference_signalk_unmapped_catalog`. **This dashboard program depends on
that work but does not perform it.**

### 4.3 Gaps — external marine weather API

**Weather forecast + sea state (wave height/period/swell) are NOT in telemetry.** Ops (Sea State /
Forecast) and Main (weather cluster) need an external marine weather API. Current conditions come from the
existing weather service. Flagged in the mockups; the API choice is its own decision (out of scope here,
note as a dependency).

## 5. Cross-cutting rules

1. **Bipolar power trends** — green charging / scarlet discharging, split at zero (§2.3). All four.
2. **Dropped-frame markers** — Tech bitrate trend overlays drop events as scarlet bars (§2.3).
3. **Recency/staleness** — every value uses the read-contract envelope; stale renders aged, dead reads idle
   (§2.2).
4. **No `confirm()`** — styled modals only for control actions (§2.6).
5. **Hero discipline** — each dashboard keeps one organizing principle; varied form per region prevents the
   grid feel.

## 6. Testing strategy

Per repo rules (PHPUnit, test-first, Pint, `search-docs`):

- **Controllers** — feature tests asserting Inertia props (correct read-path service calls; envelope shape;
  stale handling). Mock the metrics service; no live VM in tests.
- **Control actions** — feature tests for `POST` pull-toggle (calls `MediaMtxService::syncLiveSource`,
  validates `enabled`) and force-reload (dispatches `ForceReload`), behind `auth`; unauthenticated →
  redirect.
- **`<TrendChart>` + dashboards** — component/unit tests for the bipolar split (zero-crossing colour),
  water-fill geometry, and event-marker placement; render/smoke tests per page with representative props,
  including stale/idle and empty states.
- **No verification scripts/tinker** where a test proves it (CLAUDE.md).

## 7. Open dependencies (carry into planning)

- Additive data-layer mapping of unmapped SignalK nav/steering/current paths (§4.2) — blocks Skipper/Main
  nav fidelity.
- EcoFlow curated subset (`ecoflow-curated-set`) — blocks Ops/Tech EcoFlow.
- External marine weather API selection — blocks Ops Sea State/Forecast + Main weather.
- Confirm whether the existing `Broadcast.vue`/`Settings.vue` controls are removed or retained as deep-dives
  once Tech absorbs them (§3.4).

## 8. References

- ADRs: `docs/adr/0002`, `0003`, `0004`, `0005`.
- Mockups: `docs/superpowers/mockups/audience-dashboards/` + `INDEX.md`.
- Memory: `project_audience_dashboards`, `project_data_layer`, `reference_signalk_unmapped_catalog`,
  `reference_victoriametrics`, `feedback_no_browser_confirm`.
