// Shared helpers for resolving CSS-variable colours before they reach uPlot's
// <canvas> (which cannot resolve var() tokens), plus the standard palette.

export function cssVar(name, fallback) {
    if (typeof document === 'undefined') return fallback;
    const v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    return v || fallback;
}

/**
 * Resolve a caller-supplied colour to a concrete value. A `var(--x)` token is
 * computed against the document root; any other string is returned unchanged.
 */
export function resolveColor(c) {
    if (typeof c === 'string') {
        const m = c.match(/^var\((--[\w-]+)\)$/);
        if (m) return cssVar(m[1], c);
    }
    return c;
}

export function paletteColors() {
    return [
        cssVar('--color-scarlet', '#c0392b'),
        cssVar('--color-teal', '#1abc9c'),
        cssVar('--color-blue', '#2980b9'),
        cssVar('--color-amber', '#f39c12'),
        cssVar('--color-green', '#27ae60'),
    ];
}
