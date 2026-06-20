/**
 * Canonical point-of-sail derivation, shared by every dashboard so the readout
 * is identical everywhere (it used to be hand-rolled three different ways from
 * three different wind sources).
 *
 * Point of sail is a function of the TRUE wind angle off the bow (TWA). True
 * wind isn't always computed onboard, so when it's absent we fall back to the
 * apparent wind angle (AWA) and flag which source was used.
 */

// Single source of truth for the angle bands (degrees off the bow, 0..180).
const BANDS = [
    { max: 45, text: 'In Irons' },
    { max: 60, text: 'Close Hauled' },
    { max: 80, text: 'Close Reach' },
    { max: 100, text: 'Beam Reach' },
    { max: 150, text: 'Broad Reach' },
    { max: 170, text: 'Running' },
    { max: Infinity, text: 'Dead Run' },
];

const COLORS = {
    'In Irons': 'var(--color-scarlet)',
    'Close Hauled': 'var(--color-teal)',
    'Close Reach': 'var(--color-teal)',
    'Beam Reach': 'var(--color-amber)',
    'Broad Reach': 'var(--color-amber)',
    'Running': 'var(--color-green)',
    'Dead Run': 'var(--color-green)',
};

/** Normalise any signed/0..360 wind angle to its magnitude off the bow, 0..180. */
function offBow(angle) {
    if (angle == null) { return null; }
    let a = ((angle % 360) + 360) % 360;
    if (a > 180) { a = 360 - a; }
    return a;
}

/**
 * Signed true wind angle (−180..180) from true wind DIRECTION (compass bearing)
 * and the boat's heading. Negative = wind on the port bow. Null if either input
 * is missing. Suitable for driving a compass arrow.
 */
export function trueWindAngleSigned(windDirectionTrue, headingTrue) {
    if (windDirectionTrue == null || headingTrue == null) { return null; }
    return ((windDirectionTrue - headingTrue + 540) % 360) - 180;
}

/**
 * Resolve the canonical point of sail. Prefers true wind
 * (windDirectionTrue − headingTrue); falls back to apparent wind angle.
 *
 * @param {{ windDirectionTrue?: number|null, headingTrue?: number|null, windAngleApparent?: number|null }} inputs
 * @returns {{ text: string, color: string, source: 'true'|'apparent'|null, angle: number|null }}
 *          angle is the magnitude off the bow (0..180); source flags which wind was used.
 */
export function pointOfSail({ windDirectionTrue = null, headingTrue = null, windAngleApparent = null } = {}) {
    let angle = offBow(trueWindAngleSigned(windDirectionTrue, headingTrue));
    let source = angle != null ? 'true' : null;

    if (angle == null && windAngleApparent != null) {
        angle = offBow(windAngleApparent);
        source = 'apparent';
    }

    if (angle == null) {
        return { text: '—', color: 'var(--color-text-dim)', source: null, angle: null };
    }

    const text = BANDS.find((b) => angle < b.max).text;
    return { text, color: COLORS[text], angle, source };
}
