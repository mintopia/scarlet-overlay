/**
 * trendPath.js — Pure SVG path builders for bipolar and water-fill trend charts.
 *
 * No imports, no DOM, no side-effects. All functions are pure.
 *
 * Coordinate mapping
 * ------------------
 * Given N points [{x, v}, …]:
 *   - SVG x = point.x  (callers supply x in the same range as `width`; for N-point
 *     series callers typically pass x = index * (width / (N-1)) so the last point
 *     lands exactly at `width`)
 *   - SVG y is computed from the value range of ALL points (min v → height, max v → 0),
 *     i.e. higher values sit higher (smaller y) as expected on a chart.
 *     When building the zero baseline we substitute `zeroValue` into the same formula.
 */

/**
 * Map a value to an SVG y coordinate.
 * @param {number} v      - the data value
 * @param {number} minV   - minimum value across all points
 * @param {number} maxV   - maximum value across all points
 * @param {number} height - SVG viewport height
 * @returns {number}
 */
function toY(v, minV, maxV, height) {
    if (maxV === minV) {
        return height / 2;
    }
    return height * (maxV - v) / (maxV - minV);
}

/**
 * Format a number for an SVG path string, rounding to 4 decimal places.
 * @param {number} n
 * @returns {string}
 */
function fmt(n) {
    const r = Math.round(n * 10000) / 10000;
    // Remove trailing zeros after decimal point for cleaner output
    return String(r);
}

/**
 * Build a polyline SVG path string from an array of {x, y} screen coords.
 * @param {{x: number, y: number}[]} coords
 * @returns {string}
 */
function polylinePath(coords) {
    if (coords.length === 0) {
        return '';
    }
    const [head, ...tail] = coords;
    let d = `M ${fmt(head.x)} ${fmt(head.y)}`;
    for (const pt of tail) {
        d += ` L ${fmt(pt.x)} ${fmt(pt.y)}`;
    }
    return d;
}

/**
 * buildSegments — split a value series at a zero baseline into above/below segments.
 *
 * Handles zero-crossing interpolation: when consecutive points straddle the
 * zeroValue, the exact crossing x is computed by linear interpolation and
 * injected as a shared endpoint of the two segments (so they meet precisely at
 * the baseline).
 *
 * @param {{x: number, v: number}[]} points   - data points; x already in SVG space
 * @param {{width: number, height: number, zeroValue: number}} opts
 * @returns {{ above: string[], below: string[], aboveFill: string[], belowFill: string[], line: string }}
 *   above      — array of "M…L…" path strings, one per contiguous run above zeroValue
 *   below      — array of "M…L…" path strings, one per contiguous run below zeroValue
 *   aboveFill  — array of closed area paths for above-zero segments (closed along zero baseline)
 *   belowFill  — array of closed area paths for below-zero segments (closed along zero baseline)
 *   line       — full polyline path string (unsplit)
 */
export function buildSegments(points, { width, height, zeroValue = 0 }) {
    if (points.length === 0) {
        return { above: [], below: [], aboveFill: [], belowFill: [], line: '' };
    }

    // Compute value range for y mapping
    const values = points.map((p) => p.v);
    const minV = Math.min(zeroValue, ...values);
    const maxV = Math.max(zeroValue, ...values);

    // Convert data points to screen coords
    const screenPts = points.map((p) => ({
        x: p.x,
        y: toY(p.v, minV, maxV, height),
        v: p.v,
    }));

    // y coordinate of the zero baseline
    const zeroY = toY(zeroValue, minV, maxV, height);

    // Build the unsplit full-line path
    const line = polylinePath(screenPts);

    // Walk the points and split into above/below segments,
    // inserting interpolated zero-crossings where sign changes.
    const aboveSegments = [];
    const belowSegments = [];
    const aboveFillSegments = [];
    const belowFillSegments = [];

    let currentSegment = null; // { side: 'above'|'below', coords: [{x,y}] }

    function closedFillPath(coords) {
        if (coords.length < 2) {
            return '';
        }
        const first = coords[0];
        const last = coords[coords.length - 1];
        // Polyline of the segment, then close along the zero baseline back to start
        return polylinePath(coords)
            + ` L ${fmt(last.x)} ${fmt(zeroY)}`
            + ` L ${fmt(first.x)} ${fmt(zeroY)}`
            + ' Z';
    }

    function commitSegment() {
        if (currentSegment && currentSegment.coords.length >= 1) {
            const path = polylinePath(currentSegment.coords);
            const fill = closedFillPath(currentSegment.coords);
            if (path) {
                if (currentSegment.side === 'above') {
                    aboveSegments.push(path);
                    if (fill) {
                        aboveFillSegments.push(fill);
                    }
                } else {
                    belowSegments.push(path);
                    if (fill) {
                        belowFillSegments.push(fill);
                    }
                }
            }
        }
        currentSegment = null;
    }

    function sideOf(v) {
        return v >= zeroValue ? 'above' : 'below';
    }

    for (let i = 0; i < screenPts.length; i++) {
        const pt = screenPts[i];
        const side = sideOf(pt.v);

        if (i === 0) {
            currentSegment = { side, coords: [{ x: pt.x, y: pt.y }] };
            continue;
        }

        const prev = screenPts[i - 1];
        const prevSide = sideOf(prev.v);

        if (side !== prevSide) {
            // Zero crossing between prev and pt — interpolate the exact x
            // t is how far along [prev → pt] the zeroValue is crossed
            const t = (zeroValue - prev.v) / (pt.v - prev.v);
            const crossX = prev.x + t * (pt.x - prev.x);
            const crossY = zeroY; // should equal toY(zeroValue, …) by construction

            // Close the current segment at the crossing point
            currentSegment.coords.push({ x: crossX, y: crossY });
            commitSegment();

            // Start new segment on the other side, beginning at the crossing
            currentSegment = { side, coords: [{ x: crossX, y: crossY }, { x: pt.x, y: pt.y }] };
        } else {
            currentSegment.coords.push({ x: pt.x, y: pt.y });
        }
    }

    commitSegment();

    return { above: aboveSegments, below: belowSegments, aboveFill: aboveFillSegments, belowFill: belowFillSegments, line };
}

/**
 * waterFillPath — inverted column fill from the top of the SVG down to the trend line.
 *
 * Useful for depth-as-water-column visualisations: the filled area represents
 * "water above the sensor" and grows downward as depth increases.
 *
 * The path traces: top-left corner → top-right corner → trend line (reversed) →
 * back to start, creating a closed polygon.
 *
 * @param {{x: number, v: number}[]} points   - data points; x already in SVG space
 * @param {{width: number, height: number}} opts
 * @returns {string} SVG closed path ("M…L…Z")
 */
export function waterFillPath(points, { width, height }) {
    if (points.length === 0) {
        return '';
    }

    const values = points.map((p) => p.v);
    const minV = Math.min(...values);
    const maxV = Math.max(...values);

    const screenPts = points.map((p) => ({
        x: p.x,
        y: toY(p.v, minV, maxV, height),
    }));

    // Start at top-left
    let d = `M 0 0`;
    // Top edge to top-right
    d += ` L ${fmt(width)} 0`;
    // Drop down the right edge to the last trend point
    const last = screenPts[screenPts.length - 1];
    d += ` L ${fmt(last.x)} ${fmt(last.y)}`;
    // Walk the trend line in reverse back to the first point
    for (let i = screenPts.length - 2; i >= 0; i--) {
        const pt = screenPts[i];
        d += ` L ${fmt(pt.x)} ${fmt(pt.y)}`;
    }
    // Close path (returns to M 0 0)
    d += ' Z';

    return d;
}
