// Pure track-geometry helpers — no Leaflet/Vue dependencies so they can be unit
// tested in isolation.

// Max distance (degrees, ~1.1 km) between consecutive fixes that still counts as
// one continuous track. Telemetry outages leave gaps in the stored track where
// adjacent points are far apart; drawing a segment across such a gap renders a
// long straight line joining two points the boat never travelled between, so the
// track is split at gaps instead.
export const TRACK_GAP_DEG = 0.01;

/**
 * @param {[number, number]} a  [lat, lng]
 * @param {[number, number]} b  [lat, lng]
 * @returns {boolean} true when a→b spans a telemetry gap (too far to be one leg)
 */
export function isTrackGap(a, b) {
    const dLat = a[0] - b[0];
    const dLng = a[1] - b[1];

    return Math.sqrt(dLat * dLat + dLng * dLng) > TRACK_GAP_DEG;
}

/**
 * Build the drawable track segments, splitting the track at telemetry gaps.
 * Each segment carries the speed of its end point (used to colour it).
 *
 * @param {Array<{pos: [number, number], speed: number}>} points
 * @returns {Array<{from: [number, number], to: [number, number], speed: number}>}
 */
export function trackSegments(points) {
    const segments = [];

    for (let i = 1; i < points.length; i++) {
        if (isTrackGap(points[i - 1].pos, points[i].pos)) {
            continue;
        }

        segments.push({
            from: points[i - 1].pos,
            to: points[i].pos,
            speed: points[i].speed,
        });
    }

    return segments;
}
