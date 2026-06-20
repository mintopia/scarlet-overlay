import { describe, it, expect } from 'vitest';
import { buildSegments } from './trendPath.js';

describe('buildSegments', () => {
    it('splits a zero-crossing trend into above/below segments at the crossing', () => {
        const pts = [{ x: 0, v: -10 }, { x: 100, v: 10 }]; // crosses zero at x=50
        const { above, below } = buildSegments(pts, { width: 100, height: 40, zeroValue: 0 });

        expect(above).toHaveLength(1);
        expect(below).toHaveLength(1);
        expect(above[0]).toContain('50'); // segment splits at the x=50 zero crossing
    });
});
