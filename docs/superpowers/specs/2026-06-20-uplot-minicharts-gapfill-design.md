# Design: uPlot mini-charts with honest gap-fill

**Date:** 2026-06-20
**Status:** Approved (pending spec review)

## Problem

Two things:

1. **Gaps aren't shown honestly.** Dashboard trend charts stop at the last data
   point and say nothing about the gap between that point and *now*, nor about
   interior gaps where telemetry dropped. Held/old data is indistinguishable
   from live data.
2. **Charts are hand-rolled SVG.** `TrendChart.vue` and `Sparkline.vue` draw
   paths by hand in *index space* (timestamps ignored, points evenly spaced),
   duplicating scaling/gap logic the bundled chart library (uPlot) already does
   well. We want them rendered by uPlot instead, on a real time axis.

## Goal

Replace `TrendChart.vue` and `Sparkline.vue` with a single uPlot-based compact
chart component that:

- plots on an **honest time axis** (using the `{t, v}` data the dashboards
  already pass — currently ignored),
- **fills gaps** — both interior gaps and the trailing gap from the last sample
  to *now* — with a dotted, dimmed, "held last value" line, styled as **stale**
  when the last sample is older than a threshold,
- adds a **hover tooltip + crosshair** (no drag-zoom),
- maps the old visual `variant`s to **uPlot-native/simplified** equivalents.

**Out of scope (left unchanged):** `LcarsLineGraph.vue` (bespoke LCARS
aesthetic), `UplotChart.vue` + `MetricExplorer` (already uPlot, full-size),
`JourneyView`/`LogTable` (direct uPlot use).

## Decisions (from brainstorming)

- Migrate **TrendChart + Sparkline** to uPlot. Leave LCARS + MetricExplorer.
- Gap-fill applies to **interior gaps + trailing-to-now + stale styling** (all).
- Variants **simplified to uPlot-native** (water gradient & exact bipolar look
  may shift — accepted).
- Mini-charts gain **hover tooltip + crosshair** (no drag-zoom).
- **Keep** the event-bar `markers` feature (used by SignalChain).
- Roll out **all call sites at once**.

## Architecture

### 1. `resources/js/lib/seriesGaps.js` — pure, unit-tested gap model

Renderer-agnostic. Mirrors the ADR-0008 `track.js` pattern (pure logic extracted
for Vitest).

- **Input:** `points: [{t, v}]` (unix **seconds**, ascending), `nowSeconds`,
  options `{ stepSeconds?, gapFactor = 2.5, staleAfterSeconds? }`.
- **Step inference:** if `stepSeconds` not given, infer from the **median of
  consecutive `t` deltas** → no backend change needed.
- **Gap rule:** a gap exists between consecutive points when
  `Δt > gapFactor × stepSeconds`.
- **Outputs** (shape consumed by `MiniChart`):
  - `xs: number[]` — the time axis (seconds), extended to include `nowSeconds`.
  - `live: (number|null)[]` — values aligned to `xs`, with `null` at gap spans so
    uPlot breaks the live line there.
  - `held: (number|null)[]` — carry-forward of the last known value across each
    interior gap **and** from the final real sample forward to `nowSeconds`
    (the dotted "stale bridge"). `null` everywhere the live line is solid.
  - `trailingStale: boolean` — `ageSeconds > staleAfterSeconds`.
  - `ageSeconds`, `lastValueTimestamp`.
- Edge cases handled explicitly: empty, single point, all-null, every-point-a-gap.

### 2. `resources/js/components/Admin/MiniChart.vue` — compact uPlot renderer

- **Props:** `data` (`[{t,v}]`; also accepts `{t,value}` / plain number via a
  small normaliser), `variant` (`line|area|water|bipolar`), `color`,
  `colorPositive`, `colorNegative`, `zeroValue` (default 0), `height`
  (default 88; 36 for sparkline use), `showTooltip` (default true),
  `staleAfterSeconds`, `markers` (`[{x, magnitude}]`), `markerColor`.
- **Series:** built from `seriesGaps` via a **pure exported `buildSeries(data,
  props, now)`** function (the unit-test seam). Two uPlot series:
  - **live** — solid themed stroke; `area`/`water` add a filled area (water uses
    a stronger fill alpha; gradient dropped); `bipolar` uses a zero-keyed
    two-tone fill/stroke (`colorPositive` above `zeroValue`, `colorNegative`
    below) plus a dashed zero baseline.
  - **held** — dashed stroke, dimmed; themed **stale** color (amber, esp. Night
    Watch) when `trailingStale`. Drawn beneath the live series.
  - x-domain extended to `now` so the trailing held line reaches the right edge.
- **End dot:** single-point renderer on the last live value (cheap reproduction
  of the existing end dot).
- **Markers:** event-bar overlay reproduced via a uPlot `draw` hook / plugin
  (thin vertical bars; `x` given in data-index space, mapped to the time axis).
- **Tooltip + crosshair:** lightweight uPlot cursor + a small hover readout
  (value + relative time, e.g. "12.4 kn · 3m ago"). No drag-zoom.
- **Chrome-less:** no axis labels / legend by default — preserves the glanceable
  card look. Honest time-x spaces sparse data correctly. `role="img"` +
  `aria-label` summary (min/max/latest) preserved on the host.
- **Theme + resize:** extract the CSS-var resolution + `ResizeObserver` + theme
  `MutationObserver` logic currently in `UplotChart.vue` into a shared
  `resources/js/lib/uplotTheme.js`; both components consume it (no duplication).

### 3. Call-site migration (all at once)

Replace and pass real `{t,v}` history:

- `Pages/Admin/Dash/Main.vue` — speed (1h), depth (3h), house power (6h,
  bipolar), fuel/water (24h).
- `Pages/Admin/Dash/Tech.vue`, `Ops.vue`, `Skipper.vue` — their TrendCharts.
- `components/Dash/SignalChain.vue` — TrendChart **with `:markers`**.
- `Pages/Admin/Dashboard.vue` — Sparkline → MiniChart, passing **raw
  `powerHistory`** (un-stripped, so timestamps survive) instead of the
  timestamp-stripped `powerData` computed.

Then **delete** `TrendChart.vue`, `Sparkline.vue`, `lib/trendPath.js`, and
`lib/trendPath.test.mjs` (trendPath is used only by TrendChart + its test).

### 4. Backend

**No change.** History endpoints already return `{t, v}` with real timestamps;
step is inferred client-side.

## Testing

- **`seriesGaps.test.js` (Vitest):** gap-by-step-factor; interior held bridge;
  trailing-to-now held segment; stale boundary (just under / just over);
  median-step inference; single-point / empty / all-null. Same rigor as
  `track.test.js` (ADR 0008).
- **`MiniChart` `buildSeries()`:** unit-test the pure series/options builder
  (uPlot canvas can't be pixel-tested in jsdom) — assert live/held columns,
  x-domain extended to now, variant → fill/stroke wiring, marker mapping.
- **Smoke mount** per variant (renders without throwing under jsdom).
- Existing `track.test.js` stays green. Full `vitest` + `npm run build` green.
- Remove `trendPath.test.mjs` with `trendPath.js`.

## ADR

Switching dashboard micro-charts from hand-rolled SVG to uPlot/canvas is an
architecture/tooling decision → record an ADR (next number in `docs/adr/`),
referencing ADR 0008 (frontend test rigor). Notes the accepted visual shift on
simplified variants and the "no backend change / client-side step inference"
choice.

## Risks / caveats

1. **Visual shift** on `water`/`bipolar` (simplified). Mitigation: although
   rollout is all-at-once, capture before/after screenshots of Main during
   implementation and flag any variant that reads wrong before finalizing.
2. **Many small canvas instances** per dashboard cost more than inline SVG.
   uPlot is light; if a dashboard feels heavy, lazy-init off-screen charts
   (IntersectionObserver) — deferred unless observed.
3. **Accessibility/motion:** preserve `aria-label` summary; respect
   `prefers-reduced-motion` (no chart animation).

## File summary

**New:** `lib/seriesGaps.js`, `lib/seriesGaps.test.js`,
`components/Admin/MiniChart.vue`, `lib/uplotTheme.js`, `docs/adr/NNNN-*.md`,
MiniChart `buildSeries` test.
**Edited:** `UplotChart.vue` (use shared `uplotTheme.js`), Main/Tech/Ops/Skipper
dashboards, `SignalChain.vue`, `Admin/Dashboard.vue`.
**Deleted:** `TrendChart.vue`, `Sparkline.vue`, `lib/trendPath.js`,
`lib/trendPath.test.mjs`.
