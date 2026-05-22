export function fmt(val, decimals = 1) {
    if (val == null || isNaN(val)) return '—';
    return Number(val).toFixed(decimals);
}

export function fmtDuration(seconds) {
    if (seconds == null) return '—';
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    return h > 0 ? `${h}h ${m}m` : `${m}m`;
}
