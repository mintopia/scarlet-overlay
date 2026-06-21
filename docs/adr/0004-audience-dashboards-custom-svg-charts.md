# 4. Audience Dashboards render charts as custom SVG (no chart library)

Date: 2026-06-18

## Status

Accepted. Partially superseded by [ADR 0009](0009-uplot-for-dashboard-mini-charts.md)
for the dashboard trend charts (TrendChart/Sparkline → uPlot).

## Context

The Audience Dashboards program ([[project-audience-dashboards]], Part 5) designs four bespoke,
hand-coded role dashboards (Main, Skipper, Ops, Tech) consuming the canonical read contract. The
visual design phase (locked 2026-06-18, mockups in `docs/superpowers/mockups/audience-dashboards/`)
produced trend/gauge graphics that are deliberately non-standard:

- inverted **water-fill** depth columns (fills from the top, seabed line, tan below),
- signed **bipolar charge/discharge** power trends — green above the zero line (charging), scarlet
  below (discharging), split cleanly at zero crossings,
- clock-time axes with median smoothing,
- event markers overlaid on a line — e.g. dropped-frame bars on Tech's publish-bitrate trend, height
  proportional to `increase(scarlet_srt_publisher_dropped_packets_total[bucket])`.

These are presentation concerns owned by this program (per [[data-catalog-vs-presentation-config]]),
not the data layer. The question: adopt a charting library (uPlot, ApexCharts) for axes/scales, or
hand-code the rendering. A library accelerates *standard* charts but fights every bespoke variant above
— each would remain custom regardless — while adding a dependency, bundle weight, and SSR wrapping under
Octane. The repo already has a custom `Sparkline.vue` and custom instruments (`CompassRose`, gauges).

## Decision

The four Audience Dashboards render all trend and gauge graphics with **hand-coded SVG**, via a reusable
`<TrendChart>` component extending `Sparkline.vue`. `<TrendChart>` supports the variants the design
requires: line/area, inverted water-fill, signed bipolar charge/discharge (green ≥ 0 / scarlet < 0,
clipped at the zero baseline), time-axis, and overlaid event markers. Instruments stay custom
(`CompassRose` with point-of-sail, `LevelBar`, `TemperatureGauge`). **No charting library is added for
these dashboards.**

This decision is **scoped to the Audience Dashboards**. The separate, future **explore-page phase** will
adopt a standard charting library with chart functions for its exploratory/ad-hoc graphs; this ADR does
not preclude that and should not be read as a project-wide ban on chart libraries.

## Consequences

- **Full control over the bespoke marks** — water-fill, bipolar fills, and event-marker overlays are
  expressible directly in SVG with no library to fight; styling uses the existing OKLCH design tokens.
- **Zero new dependency, small bundle, SSR/Octane-safe** — no client-only chart runtime to wrap.
- **We own accessibility and responsiveness** for the bespoke graphics (reduced-motion, stacking
  breakpoints, screen-reader summaries) — accepted cost, partially mitigated by reusing `Sparkline.vue`
  conventions.
- **One rendering idiom across the four dashboards** — no mixing of library output and custom SVG within
  a single dashboard, keeping the hand-crafted look consistent.
- **The explore page will run a different idiom by design** — a deliberate, scoped divergence, recorded
  here so it is not mistaken for inconsistency.

## Supersedes

None.
