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
