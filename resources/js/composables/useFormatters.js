const CARDINALS_16 = ['N','NNE','NE','ENE','E','ESE','SE','SSE','S','SSW','SW','WSW','W','WNW','NW','NNW'];

export function fmt(val, decimals = 1) {
    if (val == null || isNaN(val)) return '—';
    return Number(val).toFixed(decimals);
}

export function fmtVal(v, type = 'standard') {
    if (v == null) return '—';
    switch (type) {
        case 'compass':
            return `${Math.round(v)}°`;
        case 'gauge':
            return Math.round(v).toString();
        case 'signed': {
            const abs = Math.abs(v);
            const str = abs >= 100 ? v.toFixed(0) : abs >= 10 ? v.toFixed(1) : v.toFixed(2);
            return v > 0 ? `+${abs >= 100 ? Math.round(v) : (abs >= 10 ? v.toFixed(1) : v.toFixed(2))}` : str;
        }
        case 'duration':
            return fmtDuration(v);
        default:
            if (Math.abs(v) >= 100) return v.toFixed(0);
            if (Math.abs(v) >= 10) return v.toFixed(1);
            return v.toFixed(2);
    }
}

export function bearingToCardinal(deg) {
    if (deg == null) return '—';
    return CARDINALS_16[Math.round(((deg % 360) + 360) % 360 / 22.5) % 16];
}

export function fmtDuration(seconds) {
    if (seconds == null) return '—';
    const s = Math.abs(seconds);
    const d = Math.floor(s / 86400);
    const h = Math.floor((s % 86400) / 3600);
    const m = Math.floor((s % 3600) / 60);
    if (d > 0) return `${d}d ${h}h`;
    if (h > 0) return `${h}h ${m}m`;
    return `${m}m`;
}

export function computeRate(data, windowMinutes = 60) {
    if (!data || data.length < 2) return null;
    const now = data[data.length - 1];
    const cutoff = now.timestamp - windowMinutes * 60;
    let oldest = null;
    for (let i = data.length - 1; i >= 0; i--) {
        if (data[i].timestamp < cutoff) { oldest = data[i]; break; }
    }
    if (!oldest || oldest.value == null || now.value == null) return null;
    const hours = (now.timestamp - oldest.timestamp) / 3600;
    if (hours < 0.01) return null;
    return (now.value - oldest.value) / hours;
}

export function computeTimeToTarget(currentValue, ratePerHour, target) {
    if (currentValue == null || ratePerHour == null || ratePerHour === 0) return null;
    if ((target > currentValue && ratePerHour < 0) || (target < currentValue && ratePerHour > 0)) return null;
    const hours = Math.abs((target - currentValue) / ratePerHour);
    return hours * 3600;
}

export function fmtTimeEstimate(seconds) {
    if (seconds == null) return '—';
    const h = Math.floor(seconds / 3600);
    if (h >= 48) return `~${Math.round(h / 24)} days`;
    if (h >= 1) return `~${h}h`;
    return `<1h`;
}
