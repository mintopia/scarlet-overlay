# Audience Dashboards — design mockups (visual-companion phase)

Static HTML mockups from the design session (2026-06-18), copied here from the (gitignored)
visual-companion session dir so they're durable. Each is a self-contained file using the real
Scarlet design tokens (OKLCH, Nunito Sans / DM Sans). Open in a browser, or serve via the
visual companion. The `.sc` block is the dashboard; the outer `.sc-card`/`.sc-tag` and the
`<h2>/.subtitle` are companion-viewer scaffolding, not the design.

Full program context + locked specs: memory `project_audience_dashboards`. Session handoff:
`/tmp/handoff-audience-dashboards-design.md`.

## MAIN — LOCKED ✅
- **`main-v9-polish.html`** ← the locked design. Map-led hero + instrument rail (CompassRose w/
  point-of-sail) + footer of trend graphs; weather cluster on the map; SOG/STW delta + depth-as-water.
- Lineage (superseded): `main-directions.html` (A/B/C directions), `helm-led-v2..v6.html` (early
  instrument-led, de-carding + metric iterations), `main-v7-mapled.html`, `main-v8-mapled.html`.

## SKIPPER — LOCKED ✅
- **`skipper-v4.html`** (shows label "v5") ← the locked design. Instrument-led: compass/POS hero +
  dense sailing block; contoured chart with real route legs + XTE; full nav panel + autopilot/rudder;
  depth + pressure safety trends; House/Engine/Fuel footer.
- Lineage: `skipper-v1.html` (first instrument-led), `v2` (dense + contours), `v3` (set/drift, route legs, rudder).

## OPS — LOCKED ✅
- **`ops-v8.html`** (shows label "v11") ← the locked design. **Balanced multi-zone** (NOT a single hero):
  Endurance (power trends + tank graphs, runtime/days) · Climate (current wx + 3 cabin gauges + sea gauge)
  · weather band (Atmosphere / Sea State / Forecast) + big map + single-column nav.
- Lineage (instructive — shows the wall-of-cards failures and the reset): `ops-v1` (systems-led, became a
  wall of cards), `ops-v2` (de-carded, "even worse"), `ops-v3` (power-instrument hero — good diagnosis,
  but Ops has no natural hero), `ops-v4/v7` (graphs/weather/temps iterations). v8 came after resetting
  from a confirmed design brief via `impeccable shape`.

## TECH — LOCKED ✅
- **`tech-v3.html`** ← the locked design. **Unified systems console** (absorbs the existing Broadcast monitor +
  start/stop and Settings force-reload). HERO = **broadcast signal-chain** (Boat Encoder → SRT publish → relay de1
  → SRT pull → MediaMTX pull-ON → Overlay) with animated flow + the two control levers (Stop/Start Stream =
  MediaMTX pull toggle; Refresh Overlay = ForceReload) docked to the header, both via styled-modal confirms. Hero
  foot = publish-bitrate 1h trend with **dropped-frame markers** (scarlet bars, height ∝ increase() of dropped
  packets) + stats. Region 2 = ESP32 tracker device-health (mode/battery/uptime · LTE+WiFi signal bars · CPU/heap/
  humidity). Region 3 = House + EcoFlow bipolar power trends.
- Lineage: `tech-v1` (signal-chain hero + tracker + power), `tech-v2` (added dropped-frame markers on bitrate).

## CROSS-CUTTING — bipolar power trends (all four) ✅
- Every House Battery + EcoFlow power trend colours by flow sign: **green above the zero line (charging), scarlet
  below (discharging)**, split at zero crossings. The `<TrendChart>` signed variant's required behaviour; supersedes
  the single-colour sparks shown in the earlier Main/Skipper/Ops mockups. **All four dashboards now LOCKED.**

## Key learnings captured here
- A dashboard escapes "wall of cards" with a **hero object** (Main=map, Skipper=compass) OR, when there's
  no natural hero (Ops), with a **balanced multi-zone overview**: grouping + varied form + clear regions.
- A big number + sparkline is the *banned hero-metric template*, not a hero. De-carding alone just scatters
  widgets. Variety of FORM down the page is what kills the grid feel.
- Render with **custom SVG, no chart lib** (reusable `<TrendChart>` ext. `Sparkline.vue`); ADR pending.
- Data: most nav/steering/current paths and EcoFlow exist in VM but aren't in `config/scarlet.php`
  (additive data-layer work); weather forecast + wave/sea-state need an external marine API. See memory
  `reference_signalk_unmapped_catalog`.
