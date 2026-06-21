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

    it('sorts unsorted input so xs is strictly ascending', () => {
        const r = seriesGaps([{ t: 120, v: 3 }, { t: 100, v: 1 }, { t: 110, v: 2 }], 120, { stepSeconds: 10, staleAfterSeconds: 999 });
        for (let i = 1; i < r.xs.length; i++) expect(r.xs[i]).toBeGreaterThan(r.xs[i - 1]);
        expect(r.live.slice(0, 3)).toEqual([1, 2, 3]);
    });

    it('dedupes duplicate timestamps keeping the last value', () => {
        const r = seriesGaps([{ t: 100, v: 1 }, { t: 100, v: 9 }, { t: 110, v: 2 }], 110, { stepSeconds: 10, staleAfterSeconds: 999 });
        const i = r.xs.indexOf(100);
        expect(r.live[i]).toBe(9);
        for (let j = 1; j < r.xs.length; j++) expect(r.xs[j]).toBeGreaterThan(r.xs[j - 1]);
    });
});
