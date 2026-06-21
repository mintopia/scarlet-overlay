# 9. Dashboard mini-charts render via uPlot (revising ADR 0004)

Date: 2026-06-20

## Status

Accepted. Partially supersedes [ADR 0004](0004-audience-dashboards-custom-svg-charts.md).

## Context

ADR 0004 chose hand-coded SVG (`TrendChart.vue` extending `Sparkline.vue`) for
the audience-dashboard trend graphics, to retain full control over bespoke marks
(water-fill, bipolar charge/discharge, event markers) and avoid a chart
dependency. Since then two needs have emerged that the hand-rolled renderers
serve poorly:

1. **Honest gaps.** The SVG charts plot in *index space* (timestamps ignored,
   points evenly spaced), so they cannot show the gap between the last sample and
   *now*, nor interior telemetry dropouts. Held/old data looks identical to live
   data — a correctness/trust problem on an operations dashboard.
2. **Duplicated charting logic + no interactivity.** Scaling, gap handling, and
   axis logic are re-implemented by hand, while uPlot — already bundled and used
   by the Metric Explorer (the "explore page" ADR 0004 anticipated) — does this
   well and adds a value/time tooltip and crosshair.

## Decision

The dashboard mini-charts (`TrendChart`, `Sparkline`) are replaced by a single
uPlot-based `MiniChart.vue` on a real time axis, with a pure `seriesGaps` model
that holds the last value across interior gaps and forward to *now* (dotted,
dimmed, amber when stale). Visual variants are **simplified to uPlot-native**
equivalents: line→line, area/water→filled area (water keeps a stronger fill
alpha; gradient dropped), bipolar→a zero-keyed two-tone gradient stroke plus a
dashed zero baseline. Event markers are retained via a uPlot draw hook. The
LCARS station graphs (`LcarsLineGraph`) keep their bespoke SVG aesthetic and are
out of scope.

## Consequences

- Gaps and staleness are shown honestly; held data is visually distinct.
- One charting idiom (uPlot) across dashboards and the explore page; less
  hand-rolled scaling code.
- Mini-charts gain a tooltip + crosshair.
- Bespoke variant fidelity is reduced (water gradient, exact bipolar look) — an
  accepted trade for honesty + maintainability.
- More small canvas instances per dashboard than inline SVG; uPlot is light,
  with lazy-init available if a dashboard proves heavy.

## Supersedes

Partially supersedes ADR 0004 for the dashboard trend charts. ADR 0004's broader
record (instruments stay custom; data vs presentation separation) still stands.
