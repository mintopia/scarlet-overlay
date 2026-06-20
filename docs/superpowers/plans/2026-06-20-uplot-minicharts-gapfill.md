# uPlot Mini-Charts with Honest Gap-Fill — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the hand-rolled SVG `TrendChart.vue` and `Sparkline.vue` with one uPlot-based compact chart (`MiniChart.vue`) that plots on a real time axis, holds the last value across interior gaps and the trailing gap to *now* (dotted/stale), and adds a hover tooltip + crosshair.

**Architecture:** A pure, unit-tested gap model (`seriesGaps.js`) turns `[{t,v}]` history into aligned `live`/`held` columns (nulls break the solid line; a carry-forward column bridges gaps). A pure `buildSeries()` maps that into a uPlot `data` triple plus styling descriptors. `MiniChart.vue` renders them with uPlot, reusing theme/resize helpers extracted from the existing `UplotChart.vue` into `lib/uplotTheme.js`. Variants are simplified to uPlot-native equivalents. Call sites migrate, old files are deleted, and an ADR records the SVG→uPlot reversal.

**Tech Stack:** Vue 3 `<script setup>`, Inertia v3, uPlot (already bundled), Vitest (ADR 0008), Laravel 12 backend (no changes).

## Global Constraints

- Timestamps are **unix seconds** throughout (`readRange` returns `{t, v}` in seconds; uPlot `scales.x.time = true` expects seconds).
- **No backend changes** — history endpoints already return `{t, v}`; the sample step is inferred client-side from the median consecutive Δt.
- Pure logic lives in `resources/js/lib/*.js` with no Vue/uPlot imports, unit-tested in isolation (mirrors `track.js` / ADR 0008).
- uPlot draws to `<canvas>` and cannot resolve CSS `var()` tokens — always resolve colors via the shared helper before passing to uPlot (existing `UplotChart.vue` convention).
- Vue components must have a single root element.
- Run `vendor/bin/pint --dirty --format agent` only if PHP changes (none expected here).
- Preserve accessibility: chart host keeps `role="img"` + an `aria-label` min/max/latest summary. Respect `prefers-reduced-motion` (no chart animation).
- Variant simplification is intentional (water gradient dropped; bipolar via a zero-keyed gradient) — accepted in the spec.

---

### Task 1: ADR 0009 — adopt uPlot for dashboard mini-charts

**Files:**
- Create: `docs/adr/0009-uplot-for-dashboard-mini-charts.md`
- Modify: `docs/adr/0004-audience-dashboards-custom-svg-charts.md` (add a "Superseded by" note to its Status)

**Interfaces:**
- Produces: the recorded decision other tasks implement. No code dependency.

- [ ] **Step 1: Invoke the adr skill** to scaffold ADR 0009. If unavailable, hand-write the file following the format of `docs/adr/0008-vitest-for-frontend-unit-tests.md`.

- [ ] **Step 2: Write ADR 0009** with this content:

```markdown
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
```

- [ ] **Step 3: Update ADR 0004 Status** — change its `## Status` block from `Accepted` to:

```markdown
## Status

Accepted. Partially superseded by [ADR 0009](0009-uplot-for-dashboard-mini-charts.md)
for the dashboard trend charts (TrendChart/Sparkline → uPlot).
```

- [ ] **Step 4: Commit**

```bash
git add docs/adr/0009-uplot-for-dashboard-mini-charts.md docs/adr/0004-audience-dashboards-custom-svg-charts.md
git commit -m "docs(adr): 0009 adopt uPlot for dashboard mini-charts (revises 0004)"
```

---

### Task 2: Pure gap model — `seriesGaps.js`

**Files:**
- Create: `resources/js/lib/seriesGaps.js`
- Test: `resources/js/__tests__/seriesGaps.test.js`

**Interfaces:**
- Produces:
  - `normalisePoint(item, index) => {t:number, v:number|null}` — accepts `{t,v}`, `{t,value}`, or bare number (bare number ⇒ `t = index`).
  - `seriesGaps(rawPoints, nowSeconds, opts?) => { xs:number[], live:(number|null)[], held:(number|null)[], stepSeconds:number, trailingStale:boolean, ageSeconds:number|null, lastValueTimestamp:number|null }`
  - `opts`: `{ stepSeconds?, gapFactor=2.5, staleAfterSeconds? (default gapFactor*step) }`
  - Constant `DEFAULT_GAP_FACTOR = 2.5`.
  - Invariants: `xs` strictly ascending; `live`/`held` same length as `xs`; `live` is `null` exactly where the solid line must break; `held` is non-null only along bridges (interior gaps + trailing-to-now), holding the pre-gap value.

- [ ] **Step 1: Write the failing tests**

```js
// resources/js/__tests__/seriesGaps.test.js
import { describe, it, expect } from 'vitest';
import { seriesGaps, normalisePoint, DEFAULT_GAP_FACTOR } from '../lib/seriesGaps.js';

const at = (xs, live, held, t) => {
    const i = xs.indexOf(t);
    return i === -1 ? undefined : { live: live[i], held: held[i] };
};

describe('normalisePoint', () => {
    it('accepts {t,v}, {t,value} and bare numbers', () => {
        expect(normalisePoint({ t: 10, v: 3 }, 0)).toEqual({ t: 10, v: 3 });
        expect(normalisePoint({ t: 10, value: 4 }, 0)).toEqual({ t: 10, v: 4 });
        expect(normalisePoint(5, 7)).toEqual({ t: 7, v: 5 });
        expect(normalisePoint(null, 2)).toEqual({ t: 2, v: null });
    });
});

describe('seriesGaps', () => {
    it('returns empty structure for no points', () => {
        const r = seriesGaps([], 1000);
        expect(r.xs).toEqual([]);
        expect(r.live).toEqual([]);
        expect(r.held).toEqual([]);
        expect(r.lastValueTimestamp).toBeNull();
    });

    it('infers step from the median delta and keeps a contiguous series solid', () => {
        // step 10s, last at t=130, now=135 (within step → no trailing bridge yet)
        const pts = [100, 110, 120, 130].map((t, i) => ({ t, v: i + 1 }));
        const r = seriesGaps(pts, 135, { staleAfterSeconds: 999 });
        expect(r.stepSeconds).toBe(10);
        // No interior nulls in live across a contiguous series (only the trailing now-point).
        expect(r.live.slice(0, 4)).toEqual([1, 2, 3, 4]);
    });

    it('bridges an interior gap with a held horizontal line and breaks the solid line', () => {
        // gap between t=120 and t=200 (Δ80 > 2.5*10)
        const pts = [{ t: 100, v: 1 }, { t: 110, v: 2 }, { t: 120, v: 3 }, { t: 200, v: 9 }, { t: 210, v: 8 }];
        const r = seriesGaps(pts, 210, { stepSeconds: 10, staleAfterSeconds: 999 });
        // The pre-gap point anchors the held bridge at its value.
        expect(at(r.xs, r.live, r.held, 120)).toEqual({ live: 3, held: 3 });
        // Synthetic bridge points carry the held value and null live.
        expect(at(r.xs, r.live, r.held, 125)).toEqual({ live: null, held: 3 }); // 120 + step/2
        expect(at(r.xs, r.live, r.held, 195)).toEqual({ live: null, held: 3 }); // 200 - step/2
        // Solid line resumes at the next real point.
        expect(at(r.xs, r.live, r.held, 200)).toEqual({ live: 9, held: null });
        // xs strictly ascending.
        for (let i = 1; i < r.xs.length; i++) expect(r.xs[i]).toBeGreaterThan(r.xs[i - 1]);
    });

    it('holds the last value forward to now and flags staleness past the threshold', () => {
        const pts = [{ t: 100, v: 5 }, { t: 110, v: 6 }];
        const r = seriesGaps(pts, 160, { stepSeconds: 10, staleAfterSeconds: 25 });
        // Trailing bridge: last real point anchors held; a now-point holds the value.
        expect(at(r.xs, r.live, r.held, 110)).toEqual({ live: 6, held: 6 });
        expect(at(r.xs, r.live, r.held, 160)).toEqual({ live: null, held: 6 });
        expect(r.ageSeconds).toBe(50);
        expect(r.trailingStale).toBe(true);
    });

    it('does not flag staleness when the last sample is recent', () => {
        const pts = [{ t: 100, v: 5 }, { t: 110, v: 6 }];
        const r = seriesGaps(pts, 118, { stepSeconds: 10, staleAfterSeconds: 25 });
        expect(r.trailingStale).toBe(false);
    });

    it('drops nulls and handles a single point (trailing bridge only)', () => {
        const r = seriesGaps([{ t: 100, v: 5 }, { t: 110, v: null }], 130, { stepSeconds: 10, staleAfterSeconds: 999 });
        expect(r.lastValueTimestamp).toBe(100);
        expect(at(r.xs, r.live, r.held, 100).live).toBe(5);
        expect(at(r.xs, r.live, r.held, 130)).toEqual({ live: null, held: 5 });
    });

    it('exposes the default gap factor', () => {
        expect(DEFAULT_GAP_FACTOR).toBe(2.5);
    });
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `npx vitest run resources/js/__tests__/seriesGaps.test.js`
Expected: FAIL — `Failed to resolve import '../lib/seriesGaps.js'`.

- [ ] **Step 3: Implement `seriesGaps.js`**

```js
// resources/js/lib/seriesGaps.js
// Pure, renderer-agnostic gap model for time-series mini-charts. No uPlot/Vue
// deps so it can be unit-tested in isolation (cf. track.js, ADR 0008).

/** Default multiple of the sample step beyond which a gap is declared. */
export const DEFAULT_GAP_FACTOR = 2.5;

/**
 * Normalise a raw data item to {t, v}. Accepts {t,v}, {t,value}, or a bare
 * number (no timestamp → t = index). Missing/invalid value → v: null.
 *
 * @param {{t:number,v?:number}|{t:number,value?:number}|number|null} item
 * @param {number} index
 * @returns {{t:number, v:number|null}}
 */
export function normalisePoint(item, index) {
    if (item == null) return { t: index, v: null };
    if (typeof item === 'number') return { t: index, v: item };
    const t = item.t ?? index;
    const v = item.v != null ? item.v : (item.value != null ? item.value : null);
    return { t, v };
}

/** Median of an array of numbers (sorted copy). Returns 0 for empty. */
function median(nums) {
    if (nums.length === 0) return 0;
    const s = [...nums].sort((a, b) => a - b);
    const mid = Math.floor(s.length / 2);
    return s.length % 2 ? s[mid] : (s[mid - 1] + s[mid]) / 2;
}

/**
 * Build the gap model for a time series.
 *
 * @param {Array} rawPoints  array of {t,v} | {t,value} | number
 * @param {number} nowSeconds  current wall-clock time (unix seconds)
 * @param {{stepSeconds?:number, gapFactor?:number, staleAfterSeconds?:number}} [opts]
 * @returns {{xs:number[], live:(number|null)[], held:(number|null)[], stepSeconds:number,
 *   trailingStale:boolean, ageSeconds:number|null, lastValueTimestamp:number|null}}
 */
export function seriesGaps(rawPoints, nowSeconds, opts = {}) {
    const pts = (rawPoints ?? [])
        .map((item, i) => normalisePoint(item, i))
        .filter((p) => p.v != null && Number.isFinite(p.t));

    const gapFactor = opts.gapFactor ?? DEFAULT_GAP_FACTOR;

    if (pts.length === 0) {
        return {
            xs: [], live: [], held: [], stepSeconds: opts.stepSeconds ?? 0,
            trailingStale: false, ageSeconds: null, lastValueTimestamp: null,
        };
    }

    let step = opts.stepSeconds;
    if (!step || step <= 0) {
        const deltas = [];
        for (let i = 1; i < pts.length; i++) deltas.push(pts[i].t - pts[i - 1].t);
        step = median(deltas) || 1;
    }
    const staleAfter = opts.staleAfterSeconds ?? gapFactor * step;
    const gapThreshold = gapFactor * step;
    const eps = step / 2;

    const xs = [];
    const live = [];
    const held = [];
    const push = (t, lv, hv) => { xs.push(t); live.push(lv); held.push(hv); };

    for (let i = 0; i < pts.length; i++) {
        const p = pts[i];
        const next = pts[i + 1];
        const gapAhead = next != null && next.t - p.t > gapThreshold;
        const isLast = i === pts.length - 1;
        const trailingAhead = isLast && nowSeconds - p.t > 0;

        // Real point: solid live value; anchor the held bridge here when one starts.
        push(p.t, p.v, gapAhead || trailingAhead ? p.v : null);

        if (gapAhead) {
            // Two synthetic, live-null points carry the held value across the gap.
            // gap > gapFactor*step guarantees these stay strictly between p.t and next.t.
            push(p.t + eps, null, p.v);
            push(next.t - eps, null, p.v);
        }
    }

    const last = pts[pts.length - 1];
    const ageSeconds = Math.max(0, nowSeconds - last.t);
    const trailingStale = ageSeconds > staleAfter;

    if (nowSeconds > last.t) {
        push(nowSeconds, null, last.v); // hold the last value forward to now
    }

    return { xs, live, held, stepSeconds: step, trailingStale, ageSeconds, lastValueTimestamp: last.t };
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `npx vitest run resources/js/__tests__/seriesGaps.test.js`
Expected: PASS (all cases).

- [ ] **Step 5: Commit**

```bash
git add resources/js/lib/seriesGaps.js resources/js/__tests__/seriesGaps.test.js
git commit -m "feat(charts): pure seriesGaps gap model (interior + trailing-to-now)"
```

---

### Task 3: Shared uPlot theme/resize helper — `uplotTheme.js`

**Files:**
- Create: `resources/js/lib/uplotTheme.js`
- Test: `resources/js/__tests__/uplotTheme.test.js`
- Modify: `resources/js/components/Admin/UplotChart.vue` (use the shared helper; behaviour unchanged)

**Interfaces:**
- Produces:
  - `resolveColor(c) => string` — resolves `var(--x)` against `document.documentElement`; passes other strings through.
  - `cssVar(name, fallback) => string` — computed style of a CSS var, or `fallback`.
  - `paletteColors() => string[]` — the resolved scarlet/teal/blue/amber/green palette.
- Consumes: nothing from earlier tasks.

- [ ] **Step 1: Write the failing test**

```js
// resources/js/__tests__/uplotTheme.test.js
import { describe, it, expect, beforeEach } from 'vitest';
import { resolveColor, cssVar } from '../lib/uplotTheme.js';

describe('uplotTheme', () => {
    beforeEach(() => {
        document.documentElement.style.setProperty('--color-teal', '#0aa');
    });

    it('resolves a var() token to its computed value', () => {
        expect(resolveColor('var(--color-teal)')).toBe('#0aa');
    });

    it('passes through a plain color', () => {
        expect(resolveColor('#123456')).toBe('#123456');
    });

    it('returns the fallback for an undefined var', () => {
        expect(cssVar('--nope', '#fff')).toBe('#fff');
    });
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `npx vitest run resources/js/__tests__/uplotTheme.test.js`
Expected: FAIL — cannot resolve `../lib/uplotTheme.js`.

- [ ] **Step 3: Implement `uplotTheme.js`** (lifted verbatim from the logic currently in `UplotChart.vue`)

```js
// resources/js/lib/uplotTheme.js
// Shared helpers for resolving CSS-variable colours before they reach uPlot's
// <canvas> (which cannot resolve var() tokens), plus the standard palette.

export function cssVar(name, fallback) {
    if (typeof document === 'undefined') return fallback;
    const v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    return v || fallback;
}

/**
 * Resolve a caller-supplied colour to a concrete value. A `var(--x)` token is
 * computed against the document root; any other string is returned unchanged.
 */
export function resolveColor(c) {
    if (typeof c === 'string') {
        const m = c.match(/^var\((--[\w-]+)\)$/);
        if (m) return cssVar(m[1], c);
    }
    return c;
}

export function paletteColors() {
    return [
        cssVar('--color-scarlet', '#c0392b'),
        cssVar('--color-teal', '#1abc9c'),
        cssVar('--color-blue', '#2980b9'),
        cssVar('--color-amber', '#f39c12'),
        cssVar('--color-green', '#27ae60'),
    ];
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `npx vitest run resources/js/__tests__/uplotTheme.test.js`
Expected: PASS.

- [ ] **Step 5: Refactor `UplotChart.vue` to use the shared helper**

In `resources/js/components/Admin/UplotChart.vue`:
- Add to the imports block: `import { cssVar, resolveColor, paletteColors } from '@/lib/uplotTheme.js';`
- Delete the local `cssVar` function (lines ~21-24) and the local `resolveColor` function (lines ~26-37).
- In `buildOptions()`, replace the inline palette array with `const palette = paletteColors();`.

- [ ] **Step 6: Verify the Metric Explorer still builds and its test passes**

Run: `npx vitest run && npm run build 2>&1 | tail -3`
Expected: existing tests PASS; build succeeds.

- [ ] **Step 7: Commit**

```bash
git add resources/js/lib/uplotTheme.js resources/js/__tests__/uplotTheme.test.js resources/js/components/Admin/UplotChart.vue
git commit -m "refactor(charts): extract shared uplotTheme helper from UplotChart"
```

---

### Task 4: Pure series builder — `buildMiniSeries.js`

**Files:**
- Create: `resources/js/lib/buildMiniSeries.js`
- Test: `resources/js/__tests__/buildMiniSeries.test.js`

**Interfaces:**
- Consumes: `seriesGaps`, `normalisePoint` from `lib/seriesGaps.js` (Task 2).
- Produces:
  - `buildMiniSeries(rawData, props, nowSeconds) => { data, gaps, markers, lastPoint }` where:
    - `data = [xs, live, held]` (uPlot data triple).
    - `gaps` = the full `seriesGaps(...)` result (carries `trailingStale`, `ageSeconds`, etc.).
    - `markers = [{ t:number, magnitude:number }]` — input `props.markers` (`[{x:index, magnitude}]`, `x` in original-data index space) mapped to timestamps via the normalised data; markers whose index is out of range or whose point is null are dropped.
    - `lastPoint = { t, v } | null` — last real (non-null) sample, for the end dot.
  - `props` fields read: `markers?`, `stepSeconds?`, `gapFactor?`, `staleAfterSeconds?`.

- [ ] **Step 1: Write the failing tests**

```js
// resources/js/__tests__/buildMiniSeries.test.js
import { describe, it, expect } from 'vitest';
import { buildMiniSeries } from '../lib/buildMiniSeries.js';

describe('buildMiniSeries', () => {
    const data = [{ t: 100, v: 1 }, { t: 110, v: 2 }, { t: 120, v: 3 }];

    it('produces a uPlot data triple aligned to the gap model', () => {
        const r = buildMiniSeries(data, { stepSeconds: 10, staleAfterSeconds: 999 }, 125);
        expect(r.data).toHaveLength(3);
        const [xs, live, held] = r.data;
        expect(xs.length).toBe(live.length);
        expect(xs.length).toBe(held.length);
        expect(live.slice(0, 3)).toEqual([1, 2, 3]);
    });

    it('reports the last real point for the end dot', () => {
        const r = buildMiniSeries(data, { stepSeconds: 10 }, 125);
        expect(r.lastPoint).toEqual({ t: 120, v: 3 });
    });

    it('maps index-space markers to timestamps and drops out-of-range ones', () => {
        const markers = [{ x: 1, magnitude: 4 }, { x: 9, magnitude: 1 }];
        const r = buildMiniSeries(data, { markers, stepSeconds: 10 }, 125);
        expect(r.markers).toEqual([{ t: 110, magnitude: 4 }]);
    });

    it('passes staleness through from the gap model', () => {
        const r = buildMiniSeries(data, { stepSeconds: 10, staleAfterSeconds: 1 }, 200);
        expect(r.gaps.trailingStale).toBe(true);
    });

    it('handles empty data without throwing', () => {
        const r = buildMiniSeries([], {}, 100);
        expect(r.data).toEqual([[], [], []]);
        expect(r.lastPoint).toBeNull();
        expect(r.markers).toEqual([]);
    });
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `npx vitest run resources/js/__tests__/buildMiniSeries.test.js`
Expected: FAIL — cannot resolve `../lib/buildMiniSeries.js`.

- [ ] **Step 3: Implement `buildMiniSeries.js`**

```js
// resources/js/lib/buildMiniSeries.js
// Pure bridge between the gap model and uPlot. Produces the uPlot data triple
// [xs, live, held], the gap metadata, the end-dot point, and time-mapped markers.
// No uPlot/Vue deps — unit-tested in isolation.

import { seriesGaps, normalisePoint } from './seriesGaps.js';

/**
 * @param {Array} rawData  [{t,v}] | [{t,value}] | number[]
 * @param {{markers?:Array<{x:number,magnitude:number}>, stepSeconds?:number, gapFactor?:number, staleAfterSeconds?:number}} props
 * @param {number} nowSeconds
 * @returns {{data:[number[], (number|null)[], (number|null)[]], gaps:object, markers:Array<{t:number,magnitude:number}>, lastPoint:{t:number,v:number}|null}}
 */
export function buildMiniSeries(rawData, props, nowSeconds) {
    const gaps = seriesGaps(rawData, nowSeconds, {
        stepSeconds: props.stepSeconds,
        gapFactor: props.gapFactor,
        staleAfterSeconds: props.staleAfterSeconds,
    });

    // Normalised real points (for marker index→time lookup and the end dot).
    const norm = (rawData ?? []).map((item, i) => normalisePoint(item, i));

    const markers = (props.markers ?? [])
        .map((m) => {
            const p = norm[m.x];
            return p && p.v != null ? { t: p.t, magnitude: m.magnitude } : null;
        })
        .filter(Boolean);

    const lastPoint = gaps.lastValueTimestamp != null
        ? { t: gaps.lastValueTimestamp, v: gaps.live[gaps.xs.indexOf(gaps.lastValueTimestamp)] }
        : null;

    return { data: [gaps.xs, gaps.live, gaps.held], gaps, markers, lastPoint };
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `npx vitest run resources/js/__tests__/buildMiniSeries.test.js`
Expected: PASS.

> Note: `lastPoint.v` is read from `live` at the `lastValueTimestamp` index. In the trailing-bridge case the last real point's `live` value is the real value (the now-point is appended *after* it with `live=null`), so `indexOf(lastValueTimestamp)` returns the real point. Verified by the "reports the last real point" test.

- [ ] **Step 5: Commit**

```bash
git add resources/js/lib/buildMiniSeries.js resources/js/__tests__/buildMiniSeries.test.js
git commit -m "feat(charts): pure buildMiniSeries (uPlot data triple + markers + end dot)"
```

---

### Task 5: `MiniChart.vue` component

**Files:**
- Create: `resources/js/components/Admin/MiniChart.vue`
- Test: `resources/js/__tests__/MiniChart.test.js`

**Interfaces:**
- Consumes: `buildMiniSeries` (Task 4), `seriesGaps` indirectly, `resolveColor`/`cssVar` (Task 3), `uplot`.
- Props: `data:Array` (required), `variant:'line'|'area'|'water'|'bipolar'='line'`, `color='var(--color-teal)'`, `colorPositive='var(--color-green)'`, `colorNegative='var(--color-scarlet)'`, `zeroValue=0`, `height=88`, `showTooltip=true`, `staleAfterSeconds?`, `stepSeconds?`, `gapFactor?`, `markers:Array=[]` (`[{x,magnitude}]`), `markerColor='var(--color-scarlet)'`.
- Produces: a self-contained chart. Single root element (`<div>` host).
- Note: `nowSeconds` is read live via `Date.now()/1000` at render time (not unit-tested — the smoke test stubs uPlot).

- [ ] **Step 1: Write the smoke test** (mounts each variant with uPlot mocked, asserting it builds data without throwing)

```js
// resources/js/__tests__/MiniChart.test.js
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';

// uPlot needs a real canvas; mock it so the component's wiring (not canvas
// pixels) is exercised under jsdom.
const instances = [];
vi.mock('uplot', () => {
    const ctor = vi.fn(function (opts, data) {
        this.opts = opts; this.data = data;
        this.setData = vi.fn(); this.setSize = vi.fn(); this.destroy = vi.fn();
        instances.push(this);
    });
    return { default: ctor };
});

import uPlot from 'uplot';
import MiniChart from '../components/Admin/MiniChart.vue';

const data = [{ t: 1000, v: 1 }, { t: 1010, v: 2 }, { t: 1020, v: 3 }];

describe('MiniChart', () => {
    beforeEach(() => { instances.length = 0; uPlot.mockClear(); });

    it.each(['line', 'area', 'water', 'bipolar'])('mounts the %s variant and builds uPlot data', async (variant) => {
        const wrapper = mount(MiniChart, { props: { data, variant, stepSeconds: 10 } });
        await wrapper.vm.$nextTick();
        expect(uPlot).toHaveBeenCalledTimes(1);
        const built = instances[0].data;
        expect(built).toHaveLength(3);        // [xs, live, held]
        expect(built[1].slice(0, 3)).toEqual([1, 2, 3]);
        expect(wrapper.find('[role="img"]').exists()).toBe(true);
    });

    it('renders an aria summary with min/max/latest', () => {
        const wrapper = mount(MiniChart, { props: { data, stepSeconds: 10 } });
        expect(wrapper.find('[role="img"]').attributes('aria-label')).toMatch(/latest 3/i);
    });
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `npx vitest run resources/js/__tests__/MiniChart.test.js`
Expected: FAIL — cannot resolve `../components/Admin/MiniChart.vue`.

- [ ] **Step 3: Implement `MiniChart.vue`**

```vue
<template>
    <div
        ref="hostRef"
        class="mini-chart"
        :style="{ height: height + 'px' }"
        role="img"
        :aria-label="ariaSummary"
    >
        <div v-if="showTooltip" ref="tipRef" class="mini-chart__tip" hidden></div>
    </div>
</template>

<script setup>
import { ref, shallowRef, computed, onMounted, onBeforeUnmount, watch, nextTick } from 'vue';
import uPlot from 'uplot';
import 'uplot/dist/uPlot.min.css';
import { resolveColor, cssVar } from '@/lib/uplotTheme.js';
import { buildMiniSeries } from '@/lib/buildMiniSeries.js';

const props = defineProps({
    data: { type: Array, required: true },
    variant: { type: String, default: 'line' }, // line | area | water | bipolar
    color: { type: String, default: 'var(--color-teal)' },
    colorPositive: { type: String, default: 'var(--color-green)' },
    colorNegative: { type: String, default: 'var(--color-scarlet)' },
    zeroValue: { type: Number, default: 0 },
    height: { type: Number, default: 88 },
    showTooltip: { type: Boolean, default: true },
    staleAfterSeconds: { type: Number, default: undefined },
    stepSeconds: { type: Number, default: undefined },
    gapFactor: { type: Number, default: undefined },
    markers: { type: Array, default: () => [] },
    markerColor: { type: String, default: 'var(--color-scarlet)' },
});

const hostRef = ref(null);
const tipRef = ref(null);
const chart = shallowRef(null);
let resizeObserver = null;
let themeObserver = null;

const nowS = () => Date.now() / 1000;

const ariaSummary = computed(() => {
    const vals = (props.data ?? [])
        .map((d) => (typeof d === 'number' ? d : (d?.v ?? d?.value)))
        .filter((v) => v != null);
    if (vals.length === 0) return 'Trend chart. No data.';
    const min = Math.min(...vals), max = Math.max(...vals), last = vals[vals.length - 1];
    return `Trend chart. Min ${min.toFixed(2)}, max ${max.toFixed(2)}, latest ${last.toFixed(2)}.`;
});

/** Apply the per-variant stroke/fill onto the live uPlot series descriptor. */
function styleLiveSeries(s, liveColor) {
    if (props.variant === 'bipolar') {
        // Zero-keyed two-tone gradient: positive colour above zeroValue, negative below.
        const pos = resolveColor(props.colorPositive);
        const neg = resolveColor(props.colorNegative);
        s.stroke = (u) => {
            const yPos = u.valToPos(props.zeroValue, 'y', true);
            const grad = u.ctx.createLinearGradient(0, u.bbox.top, 0, u.bbox.top + u.bbox.height);
            const stop = Math.max(0, Math.min(1, (yPos - u.bbox.top) / u.bbox.height));
            grad.addColorStop(0, pos);
            grad.addColorStop(stop, pos);
            grad.addColorStop(stop, neg);
            grad.addColorStop(1, neg);
            return grad;
        };
        s.fill = (u) => {
            const yPos = u.valToPos(props.zeroValue, 'y', true);
            const grad = u.ctx.createLinearGradient(0, u.bbox.top, 0, u.bbox.top + u.bbox.height);
            const stop = Math.max(0, Math.min(1, (yPos - u.bbox.top) / u.bbox.height));
            grad.addColorStop(0, withAlpha(resolveColor(props.colorPositive), 0.15));
            grad.addColorStop(stop, withAlpha(resolveColor(props.colorPositive), 0.15));
            grad.addColorStop(stop, withAlpha(resolveColor(props.colorNegative), 0.15));
            grad.addColorStop(1, withAlpha(resolveColor(props.colorNegative), 0.15));
            return grad;
        };
    } else {
        s.stroke = liveColor;
        if (props.variant === 'area') s.fill = withAlpha(liveColor, 0.08);
        if (props.variant === 'water') s.fill = withAlpha(liveColor, 0.18);
    }
}

/** Convert a hex/rgb colour to an rgba() with the given alpha (best-effort). */
function withAlpha(color, alpha) {
    if (typeof color !== 'string') return color;
    if (color.startsWith('#')) {
        const h = color.slice(1);
        const f = h.length === 3 ? h.split('').map((c) => c + c).join('') : h;
        const n = parseInt(f, 16);
        return `rgba(${(n >> 16) & 255}, ${(n >> 8) & 255}, ${n & 255}, ${alpha})`;
    }
    return color; // oklch()/var-resolved tokens: pass through (fill still drawn, no alpha)
}

/** uPlot plugin: draw event-marker bars + the end dot. */
function marksPlugin(getBuilt, liveColor) {
    return {
        hooks: {
            draw: (u) => {
                const built = getBuilt();
                const ctx = u.ctx;
                // Event-marker bars (height ∝ magnitude, normalised to the tallest).
                const maxMag = Math.max(1, ...built.markers.map((m) => m.magnitude));
                ctx.save();
                ctx.globalAlpha = 0.55;
                ctx.fillStyle = resolveColor(props.markerColor);
                for (const m of built.markers) {
                    const x = u.valToPos(m.t, 'x', true);
                    const h = (m.magnitude / maxMag) * u.bbox.height;
                    ctx.fillRect(x - 1, u.bbox.top + u.bbox.height - h, 2, h);
                }
                ctx.restore();
                // End dot at the last real sample.
                if (built.lastPoint) {
                    const x = u.valToPos(built.lastPoint.t, 'x', true);
                    const y = u.valToPos(built.lastPoint.v, 'y', true);
                    ctx.save();
                    ctx.fillStyle = liveColor;
                    ctx.strokeStyle = cssVar('--color-surface', '#fff');
                    ctx.lineWidth = 1.5;
                    ctx.beginPath();
                    ctx.arc(x, y, 3, 0, Math.PI * 2);
                    ctx.fill();
                    ctx.stroke();
                    ctx.restore();
                }
            },
        },
    };
}

/** uPlot cursor hook: position the value/time tooltip. */
function tooltipSetCursor(u) {
    const tip = tipRef.value;
    if (!tip) return;
    const { idx, left, top } = u.cursor;
    if (idx == null) { tip.hidden = true; return; }
    const t = u.data[0][idx];
    const v = u.data[1][idx] ?? u.data[2][idx];
    if (v == null) { tip.hidden = true; return; }
    const ageMin = Math.round((nowS() - t) / 60);
    tip.hidden = false;
    tip.textContent = `${Number(v).toFixed(1)} · ${ageMin <= 0 ? 'now' : ageMin + 'm ago'}`;
    tip.style.left = `${left}px`;
    tip.style.top = `${top}px`;
}

let built = { data: [[], [], []], gaps: {}, markers: [], lastPoint: null };

function buildOptions(liveColor, staleColor) {
    const live = { label: 'value', width: 2, points: { show: false } };
    styleLiveSeries(live, liveColor);
    const held = {
        label: 'held', stroke: staleColor, width: 1.5,
        dash: [3, 3], points: { show: false },
    };
    return {
        width: hostRef.value.clientWidth || 300,
        height: props.height,
        scales: { x: { time: true }, y: {} },
        axes: [{ show: false }, { show: false }],
        legend: { show: false },
        cursor: { show: props.showTooltip, x: props.showTooltip, y: false,
            points: { show: false }, setCursor: props.showTooltip ? tooltipSetCursor : undefined },
        series: [{}, live, held],
        plugins: [marksPlugin(() => built, liveColor)],
    };
}

function render() {
    if (chart.value) { chart.value.destroy(); chart.value = null; }
    if (!hostRef.value || !props.data || props.data.length === 0) return;
    const liveColor = resolveColor(props.color);
    const staleColor = cssVar('--color-amber', '#f39c12');
    built = buildMiniSeries(props.data, props, nowS());
    chart.value = new uPlot(buildOptions(liveColor, staleColor), built.data, hostRef.value);
}

onMounted(async () => {
    await nextTick();
    render();
    resizeObserver = new ResizeObserver(() => {
        if (chart.value && hostRef.value) {
            chart.value.setSize({ width: hostRef.value.clientWidth, height: props.height });
        }
    });
    resizeObserver.observe(hostRef.value);
    themeObserver = new MutationObserver(() => render());
    themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
});

watch(() => props.data, render, { deep: true });

onBeforeUnmount(() => {
    resizeObserver?.disconnect();
    themeObserver?.disconnect();
    chart.value?.destroy();
    chart.value = null;
});
</script>

<style scoped>
.mini-chart { position: relative; width: 100%; }
.mini-chart__tip {
    position: absolute;
    transform: translate(-50%, -130%);
    pointer-events: none;
    background: var(--color-surface);
    color: var(--color-text);
    border: 1px solid var(--color-border);
    border-radius: 6px;
    padding: 2px 6px;
    font-size: 11px;
    white-space: nowrap;
    z-index: 5;
}
@media (prefers-reduced-motion: reduce) {
    .mini-chart * { transition: none !important; animation: none !important; }
}
</style>
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `npx vitest run resources/js/__tests__/MiniChart.test.js`
Expected: PASS (4 variant mounts + aria summary).

- [ ] **Step 5: Commit**

```bash
git add resources/js/components/Admin/MiniChart.vue resources/js/__tests__/MiniChart.test.js
git commit -m "feat(charts): MiniChart uPlot component (variants, gap-fill, tooltip, markers)"
```

---

### Task 6: Migrate call sites, delete old renderers, verify

**Files:**
- Modify: `resources/js/Pages/Admin/Dash/Main.vue`, `resources/js/Pages/Admin/Dash/Tech.vue`, `resources/js/Pages/Admin/Dash/Ops.vue`, `resources/js/Pages/Admin/Dash/Skipper.vue`
- Modify: `resources/js/components/Dash/SignalChain.vue`
- Modify: `resources/js/Pages/Admin/Dashboard.vue`
- Delete: `resources/js/components/Admin/TrendChart.vue`, `resources/js/components/Admin/Sparkline.vue`, `resources/js/lib/trendPath.js`, `resources/js/lib/trendPath.test.mjs`

**Interfaces:**
- Consumes: `MiniChart` (Task 5). `MiniChart` is a drop-in for `<TrendChart>`: same `data` (`{t,v}`), `variant`, `color`, `colorPositive`, `colorNegative`, `zeroValue`, `height`, `markers`, `markerColor` props. Drop the `width` prop (uPlot auto-sizes to the host) and the `timeAxis` prop (always time-based now).

- [ ] **Step 1: Migrate the four dashboards + SignalChain.** In each of `Main.vue`, `Tech.vue`, `Ops.vue`, `Skipper.vue`, `SignalChain.vue`:
  - Change the import `import TrendChart from '@/components/Admin/TrendChart.vue';` → `import MiniChart from '@/components/Admin/MiniChart.vue';`
  - Rename every `<TrendChart ... />` tag to `<MiniChart ... />`.
  - Remove any `:width="…"` and `:timeAxis` / `time-axis` attributes from those tags (uPlot sizes to the host; axis is always time).
  - Keep `:data`, `variant`, `:markers`, `marker-color`, `color`, `:height`, `color-positive`, `color-negative`, `:zero-value` attributes as-is.

- [ ] **Step 2: Migrate the Sparkline on `Admin/Dashboard.vue`.**
  - Change import `import Sparkline from '@/components/Admin/Sparkline.vue';` → `import MiniChart from '@/components/Admin/MiniChart.vue';`
  - Replace the tag (line ~175):

```html
<MiniChart :data="props.powerHistory ?? []" variant="area" color="var(--color-green)" :height="36" :zero-value="0" />
```

  - Delete the now-unused `powerData` computed (line ~393: `const powerData = computed(...)`). (It stripped timestamps; `MiniChart` consumes the raw `{t,v}` `powerHistory` directly.)

- [ ] **Step 3: Verify no stale importers remain**

Run: `grep -rn "TrendChart\|Sparkline\|trendPath" resources/js`
Expected: no matches (every reference migrated).

- [ ] **Step 4: Delete the obsolete files**

```bash
git rm resources/js/components/Admin/TrendChart.vue \
       resources/js/components/Admin/Sparkline.vue \
       resources/js/lib/trendPath.js \
       resources/js/lib/trendPath.test.mjs
```

- [ ] **Step 5: Run the full frontend suite + build**

Run: `npx vitest run && npm run build 2>&1 | tail -3`
Expected: all tests PASS (including `seriesGaps`, `uplotTheme`, `buildMiniSeries`, `MiniChart`, `track`); build succeeds with no "Unable to locate file in Vite manifest" or unresolved-import errors.

- [ ] **Step 6: Manual visual check (per spec risk #1).** With the dev server running, open Main and confirm: each chart plots against time; the trailing dotted held line reaches the right edge and turns amber when the metric is stale; interior gaps show a dotted bridge; hover shows the value/time tooltip; the SignalChain bitrate chart still shows dropped-frame marker bars. Capture a before/after screenshot of Main. If any variant reads wrong, note it and stop for review before finalizing.

- [ ] **Step 7: Commit**

```bash
git add resources/js/Pages/Admin/Dash/Main.vue resources/js/Pages/Admin/Dash/Tech.vue \
        resources/js/Pages/Admin/Dash/Ops.vue resources/js/Pages/Admin/Dash/Skipper.vue \
        resources/js/components/Dash/SignalChain.vue resources/js/Pages/Admin/Dashboard.vue
git commit -m "feat(charts): migrate dashboards to MiniChart; remove SVG TrendChart/Sparkline"
```

---

## Self-Review

**Spec coverage:**
- uPlot mini-chart replacing TrendChart + Sparkline → Tasks 5, 6. ✓
- Honest time axis → `scales.x.time` in MiniChart (Task 5); data already `{t,v}`. ✓
- Interior gap fill + trailing-to-now + stale styling → `seriesGaps.js` (Task 2), rendered via the dashed `held` series + amber stale colour (Task 5). ✓
- Hover tooltip + crosshair → `cursor` + `tooltipSetCursor` (Task 5). ✓
- Variants simplified to uPlot-native → `styleLiveSeries` (Task 5). ✓
- Keep markers → `marksPlugin` + index→time mapping in `buildMiniSeries` (Tasks 4, 5); SignalChain migrated with `:markers` (Task 6). ✓
- Leave LcarsLineGraph + UplotChart/MetricExplorer → not touched (UplotChart only refactored to share helper, behaviour unchanged, Task 3). ✓
- Shared `uplotTheme.js` → Task 3. ✓
- No backend change → step inferred client-side (Task 2). ✓
- Delete TrendChart/Sparkline/trendPath(+test) → Task 6. ✓
- ADR → Task 1 (0009 supersedes part of 0004). ✓
- Testing rigor (ADR 0008) → pure-module Vitest in Tasks 2-4, smoke test Task 5. ✓

**Placeholder scan:** No TBD/TODO; all code blocks complete; test code shown.

**Type consistency:** `seriesGaps()` return shape (`xs/live/held/stepSeconds/trailingStale/ageSeconds/lastValueTimestamp`) is consumed identically in `buildMiniSeries` (Task 4) and `MiniChart` (Task 5). `buildMiniSeries` returns `{data, gaps, markers, lastPoint}` — consumed by `MiniChart`'s `built` (Task 5). Marker shape `{x:index,magnitude}` in → `{t,magnitude}` out, consistent across Tasks 4-6. `resolveColor`/`cssVar` signatures consistent across Tasks 3, 5.

**Known approximation:** `withAlpha()` only adds true alpha to hex colours; `oklch()`/var-resolved tokens pass through without alpha (fill still draws). Acceptable for the simplified variants; flagged for the visual check (Task 6, Step 6).
