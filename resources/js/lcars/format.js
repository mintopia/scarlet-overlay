/* LCARS value formatters — humanize raw telemetry for display. */

export function fmtNumber(v, dp = 1) {
    if (v == null || Number.isNaN(v)) return '--';
    return Number(v).toFixed(dp);
}

/** Seconds → compact duration: 147414 → "1d 16h", 3720 → "1h 02m", 45 → "45s". */
export function fmtDuration(seconds) {
    if (seconds == null || Number.isNaN(seconds)) return '--';
    const s = Math.max(0, Math.round(seconds));
    const d = Math.floor(s / 86400);
    const h = Math.floor((s % 86400) / 3600);
    const m = Math.floor((s % 3600) / 60);
    if (d > 0) return `${d}d ${String(h).padStart(2, '0')}h`;
    if (h > 0) return `${h}h ${String(m).padStart(2, '0')}m`;
    if (m > 0) return `${m}m ${String(s % 60).padStart(2, '0')}s`;
    return `${s}s`;
}

/** Bytes → "75.0 KB" / "1.2 MB". */
export function fmtBytes(bytes) {
    if (bytes == null || Number.isNaN(bytes)) return '--';
    const u = ['B', 'KB', 'MB', 'GB'];
    let v = Math.abs(bytes); let i = 0;
    while (v >= 1024 && i < u.length - 1) { v /= 1024; i++; }
    return `${v.toFixed(v < 10 && i > 0 ? 1 : 0)} ${u[i]}`;
}

/** Decimal degrees → "50°59.2'N" style. */
export function fmtCoord(v, isLat) {
    if (v == null || Number.isNaN(v)) return '--';
    const hemi = isLat ? (v >= 0 ? 'N' : 'S') : (v >= 0 ? 'E' : 'W');
    const a = Math.abs(v);
    const deg = Math.floor(a);
    const min = ((a - deg) * 60).toFixed(2);
    const pad = isLat ? 2 : 3;
    return `${String(deg).padStart(pad, '0')}°${min.padStart(5, '0')}'${hemi}`;
}

/**
 * Apply a contract metric's display formatting. Returns { text, unit } where unit
 * may be '' when the formatter folds it in (durations, bytes, coords).
 */
export function fmtMetric(m, v) {
    if (v == null) return { text: '--', unit: m.unit ?? '' };
    switch (m.fmt) {
        case 'duration': return { text: fmtDuration(v), unit: '' };
        case 'bytes': return { text: fmtBytes(v), unit: '' };
        case 'lat': return { text: fmtCoord(v, true), unit: '' };
        case 'lon': return { text: fmtCoord(v, false), unit: '' };
        default: return { text: typeof v === 'number' ? v.toFixed(m.dp ?? 1) : String(v), unit: m.unit ?? '' };
    }
}
