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
