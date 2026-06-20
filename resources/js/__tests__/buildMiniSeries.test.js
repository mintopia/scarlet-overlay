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
