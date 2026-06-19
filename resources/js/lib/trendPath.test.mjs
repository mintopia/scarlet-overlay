import assert from 'node:assert';
import { buildSegments } from './trendPath.js';

const pts = [{ x: 0, v: -10 }, { x: 100, v: 10 }]; // crosses zero at x=50
const { above, below } = buildSegments(pts, { width: 100, height: 40, zeroValue: 0 });

assert.ok(above.length === 1, 'one above-zero segment');
assert.ok(below.length === 1, 'one below-zero segment');
assert.ok(above[0].includes('50'), 'segment splits at the x=50 zero crossing');
console.log('trendPath OK');
