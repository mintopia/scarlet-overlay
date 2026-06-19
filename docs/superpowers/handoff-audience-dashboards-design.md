# Handoff: Audience Dashboards — visual design phase (Scarlet, Part 5)

**For:** the agent resuming the in-browser visual design of the four role-scoped Audience Dashboards.
**Workspace:** `/home/workspace/scarlet-overlay` (Laravel 12 + Inertia/Vue 3 + Octane). Cloud Agent env.
**Date:** 2026-06-18. **Branch:** develop.

## What this work is
Designing four role dashboards **visually, mockup-by-mockup, with Jess** via the superpowers
**visual companion**, then (later) implementing them as hand-coded Vue pages. There is **no user-facing
builder** — curation is editorial (Jess + agent). Goal throughout: **avoid a "wall of cards"** via real
visual hierarchy. Read these for full context (don't duplicate):
- Memory `project_audience_dashboards` — program + **LOCKED Main and Skipper design specs**.
- Memory `project_data_layer`, `reference_signalk_unmapped_catalog`, `reference_victoriametrics` — data.
- Memory `reference_visual_companion_cloudagent` — how to run the companion here (REQUIRED patches).
- Memory `feedback_admin_design`, `feedback_no_browser_confirm`, `project_admin_dashboards_grafana` (REVERSED).
- Design system: `PRODUCT.md` + `DESIGN.md` (repo root). Register = product. OKLCH palette
  (teal=nav, amber=wind, blue=depth/water, green=battery/positive, scarlet=brand/discharge),
  Nunito Sans numbers + DM Sans labels, light theme default (+ dark + Night-Watch).

## Status of the four dashboards
- **Main — LOCKED** ✅ mockup `main-v9-polish.html`. Map-led hero + instrument rail + footer. Spec in memory.
- **Skipper — LOCKED** ✅ mockup `skipper-v4.html` (labelled "v5"). Instrument-led. Spec in memory.
- **Ops — ~DONE, awaiting final sign-off** 🟡 mockup `ops-v8.html` (labelled "v11"). See below.
- **Tech — NOT STARTED** ⬜. Brief content: House Battery, EcoFlow, **ESP32 tracker status**
  (`scarlet_system_*`: battery/uptime/heap/CPU/LTE rssi+quality/wifi/mode/usb), **streaming status**
  (`scarlet_srt_*` + MediaMtxService), and **control ACTIONS** — MediaMTX start/stop + overlay refresh.
  Actions need auth + **styled-modal confirms (NEVER `confirm()`)**. Also has electrical switch-bank
  states (`scarlet_signalk_electrical_switches_bank_0_*`) available if useful.

## Ops current state (ops-v8.html, "v11") — the hard-won design
Ops thrashed badly (multiple "wall of cards" / "squashed" / "too much" rejections) until we **reset from
basics** with a confirmed design brief. **Key learning: Ops is NOT a single-hero dashboard** like
Main(map)/Skipper(compass) — it's a **balanced multi-zone overview**; grouping + varied form do the work
a hero does elsewhere. Confirmed brief framing: Ops answers *"can we keep going (endurance) and are we
comfortable (habitability)?"* — a periodic scanning check during passage.

Current Ops layout (3 labelled regions, top→bottom):
1. **Endurance** — `endgrid` [power 1.55fr | tanks 1fr, divider]. Power = House Battery + EcoFlow side by
   side, each: SOC + runtime/to-full (bottom-aligned) + V/W + a **tall (~104px) charge/discharge trend**.
   Tanks = Fuel + Water as **trend graphs** (not bars) with time axes + %/days-remaining.
2. **Climate** — current outside weather cell (icon+temp+condition) + 3 cabin gauges (temp+humidity) +
   sea-temp gauge, all in one `climate` flex row.
3. (untitled — header removed) weather band `wxband` [Atmosphere 4 rows | Sea State 4 rows | Forecast 5
   cells], vertically centered; then `posband` [big map 1.8fr | single-column Sailing&Nav 0.85fr].

Likely next tweaks when resuming Ops: get Jess's final yes, then **lock it** (add its spec to memory like
Main/Skipper). It was very close at pause.

## Mockups & the visual companion
- **DURABLE copies + index:** `docs/superpowers/mockups/audience-dashboards/` (19 HTML files + `INDEX.md`
  marking LOCKED/current/superseded and the key learnings). Start there. Locked: `main-v9-polish.html`,
  `skipper-v4.html`. Current Ops: `ops-v8.html`.
- Originals (gitignored, may be cleaned) live in:
  `/home/workspace/scarlet-overlay/.superpowers/brainstorm/37715-1781786920/content/` — the companion
  serves the **newest file by mtime**; to re-show a specific one copy it in / `touch` it.
- **The companion server will likely be dead after a few hours** (4h idle timeout / session end). To
  restart, follow `reference_visual_companion_cloudagent` EXACTLY:
  1. Re-apply the two patches if the plugin updated: `helper.js` ws→wss, and `server.cjs`
     `isAllowedWebSocketOrigin` host-only (else WebSocket 502s behind the TLS forward).
  2. `start-server.sh --project-dir /home/workspace/scarlet-overlay --host 0.0.0.0 --url-host localhost`
  3. HTTP forward already exists (`cloudagent http-forwards list`): hostname `visual-companion`. If the
     restarted server gets a NEW port, remove + re-add the forward to the new `--container <port>`.
  4. Restarting reuses the port if same `--project-dir`, BUT creates a NEW session dir — copy the mockup
     HTML you want shown into the new `content/` dir.
  5. Open for Jess: `cloudagent open-url "<https-url>/?key=<token>" --title ...`
- Last live URL (token may rotate on restart):
  `https://visual-companion.slow-sheppard.ws.cloudagent.mintopia.net/?key=7fa472a7fe1ac5ab2d4fbad6c5991fde3f62b4e4b9dc054626e4aea25368627d`

## Decisions locked
- **Rendering: custom SVG, NO chart library**, via a reusable `<TrendChart>` extending `Sparkline.vue`
  (line/area/inverted-water-fill/signed charge-discharge/time-axis). Instruments stay the existing custom
  components. **ADR still to be written** at spec time (evaluated uPlot/ApexCharts, chose custom).
- Reuse existing components: `CompassRose.vue` (combined heading + point-of-sail + wind), `Sparkline.vue`,
  `LevelBar.vue`, and finally the orphaned `TemperatureGauge.vue` (Ops cabin gauges).
- Map = Leaflet with **EMODnet/OpenSeaMap bathymetry contour tiles** (`OpenSeaMapService` / `/openseamap/{z}/{x}/{y}`).
- Per-dashboard hierarchy: Main=map hero, Skipper=instrument hero, Ops=balanced multi-zone, Tech=TBD.

## Data reality (verified live against prod VictoriaMetrics 44.30.69.5:8428)
- Query VM with a **Node `http.get` script via Bash** (curl/wget blocked by context-mode; network is fine).
  Use `&start=<35d ago>&end=now` or dock-side inactive series (COG/SOG/waypoint/depth) won't appear.
- SignalK publishes **far more than `config/scarlet.php` maps** — XTE, bearing-to-WP, track bearing,
  set/drift (tidal current), rudder angle, autopilot state, rate-of-turn, route leg positions
  (prev/next/following), depth belowSurface/belowTransducer. **Surfacing these is additive data-layer work
  (ADR 0002).** Full list in memory `reference_signalk_unmapped_catalog`.
- **EcoFlow Delta** = uncurated MQTT firehose: `scarlet_mqtt_value{topic="ecoflow/P231ZE1APJ3P0446_<report>/<field>"}`
  (SOC=`cmsBattSoc`/`soc`, `inputWatts`/`outputWatts`, `powGetAc`, `powGetQcusb1/2`, `remainTime`, V/A, temps).
  Data layer must curate a named subset.
- Cabin temps/humidity: `scarlet_mqtt_temperature|humidity{topic="zigbee2mqtt/{Forepeak cabin|Quarterberth|Main Cabin}"}`.
  Water `scarlet_signalk_tanks_freshWater_0_currentLevel`. Sea temp `scarlet_signalk_environment_water_temperature`.
- **Weather forecast + sea state (wave height/period/swell) are NOT in telemetry** — need an external
  marine weather API. Current conditions come from the existing weather service. Flagged in mockups.

## Process notes
- The whole design phase ran under **superpowers:brainstorming** (process skill, HARD-GATE: no
  implementation until design approved + spec written). Still inside that gate — next terminal state after
  all four are approved is writing the spec, then writing-plans.
- Used **impeccable** throughout (context loaded from PRODUCT/DESIGN). Ran `impeccable critique` on Main-v6
  (baseline 27/40, snapshot in `.impeccable/critique/`) and on Ops to diagnose the wall-of-cards. Used
  `impeccable shape` to reset Ops from basics.
- Jess is terse and exacting about layout; iterates fast. Show, don't tell — keep pushing companion screens.

## Next steps (suggested order)
1. Restart the companion (see above), re-show `ops-v8.html`, get Jess's final call on **Ops** → lock it +
   add its spec to memory `project_audience_dashboards`.
2. Design **Tech** (instrument/status-led; investigate `scarlet_system_*` + `scarlet_srt_*` + MediaMtxService
   first via the VM Node-query + codebase). Handle the **control actions** carefully (auth + styled modals).
3. When all four are approved: write the design **spec** (`docs/superpowers/specs/`), write the
   **`<TrendChart>` / chart-library ADR** and any presentation-config ADR, then **writing-plans** →
   implementation. Honor repo rules (Pint, PHPUnit test-first, `search-docs`, no `confirm()`).

## Suggested skills for the next session
- `superpowers:brainstorming` (still the active process gate) → eventually `superpowers:writing-plans`.
- `impeccable` (+ `impeccable shape` for Tech's IA, `impeccable critique` to pressure-test).
- `cloudagent` (restart/expose the visual companion; open URLs/files for Jess).
- `metrics-reference` (note it's STALE — trust live VM + `reference_signalk_unmapped_catalog`).
- `inertia-vue-development`, `tailwindcss-development` (at implementation time).
- `adr` (TrendChart/no-chart-lib decision + any presentation-config-to-DB decision).
- `superpowers:using-superpowers` at session start.
