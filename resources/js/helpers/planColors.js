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

export { HUE_FAMILIES };
