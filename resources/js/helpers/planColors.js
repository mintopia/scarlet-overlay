const HUE_FAMILIES = [
    { name: 'Blues', hue: 245, chroma: 0.14 },
    { name: 'Corals', hue: 25, chroma: 0.18 },
    { name: 'Greens', hue: 150, chroma: 0.16 },
    { name: 'Ambers', hue: 70, chroma: 0.17 },
    { name: 'Teals', hue: 178, chroma: 0.14 },
    { name: 'Pinks', hue: 330, chroma: 0.18 },
];

const LIGHTNESS_STEPS = [0.55, 0.45, 0.35, 0.28];

export function groupColor(colorIndex) {
    const family = HUE_FAMILIES[colorIndex % HUE_FAMILIES.length];
    return `oklch(${LIGHTNESS_STEPS[1]} ${family.chroma} ${family.hue})`;
}

export function routeColor(groupColorIndex, routeColorIndex) {
    const family = HUE_FAMILIES[groupColorIndex % HUE_FAMILIES.length];
    const l = LIGHTNESS_STEPS[routeColorIndex % LIGHTNESS_STEPS.length];
    return `oklch(${l} ${family.chroma} ${family.hue})`;
}

export function routeColorDim(groupColorIndex, routeColorIndex) {
    const family = HUE_FAMILIES[groupColorIndex % HUE_FAMILIES.length];
    const l = LIGHTNESS_STEPS[routeColorIndex % LIGHTNESS_STEPS.length];
    return `oklch(${l} ${family.chroma * 0.4} ${family.hue})`;
}

/**
 * Distinct stroke dash patterns keyed by group index. Used to keep routes
 * tellable apart by line texture (not just hue) — essential in the "night"
 * theme where every hue is mapped to the red spectrum.
 *
 * Index 0 maps to a solid line so the common single-group case is unaffected.
 *
 * @type {Array<string|null>}
 */
const DASH_PATTERNS = [
    null, // solid
    '10 6', // dashed
    '2 6', // dotted
    '12 6 2 6', // dash-dot
    '16 6 2 6 2 6', // dash-dot-dot
    '6 6', // short dash
];

/**
 * Return a Leaflet `dashArray` value derived from a group's colour index so
 * each group's polylines have a distinct line texture in addition to colour.
 *
 * @param {number} groupColorIndex
 * @returns {string|null} A Leaflet dashArray string, or null for a solid line.
 */
export function routeDashPattern(groupColorIndex) {
    return DASH_PATTERNS[groupColorIndex % DASH_PATTERNS.length];
}

export { HUE_FAMILIES, DASH_PATTERNS };
