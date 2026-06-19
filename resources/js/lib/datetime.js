/**
 * Shared date/time formatters for the admin UI, so every page presents dates
 * the same way instead of re-deriving the format per component.
 */

/**
 * Format a date value as "12 Jun 2026" (optionally with "12:30" time appended).
 *
 * @param {string|number|Date|null|undefined} value
 * @param {{ withTime?: boolean }} [options]
 * @returns {string} formatted date, or "—" when the value is missing/invalid
 */
export function formatDate(value, { withTime = false } = {}) {
    if (!value) { return '—'; }
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) { return '—'; }
    const opts = { day: 'numeric', month: 'short', year: 'numeric' };
    if (withTime) {
        opts.hour = '2-digit';
        opts.minute = '2-digit';
    }
    return d.toLocaleDateString('en-GB', opts);
}

/**
 * Format a duration in seconds as a compact "1d 3h 20m" string.
 *
 * @param {number|null|undefined} seconds
 * @returns {string}
 */
export function formatDuration(seconds) {
    if (!seconds) { return '—'; }
    const d = Math.floor(seconds / 86400);
    const h = Math.floor((seconds % 86400) / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const parts = [];
    if (d) { parts.push(`${d}d`); }
    if (h) { parts.push(`${h}h`); }
    if (m) { parts.push(`${m}m`); }
    return parts.join(' ') || '0m';
}
