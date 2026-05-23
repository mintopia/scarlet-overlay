<template>
    <div class="panel p-0 overflow-x-auto">
        <table class="log-table">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Trip Log</th>
                    <th>Wind Dir</th>
                    <th>Wind (Bft)</th>
                    <th>Baro</th>
                    <th>Lat/Long</th>
                    <th>WP Dist</th>
                    <th>WP TTG</th>
                    <th>Batt %</th>
                    <th>Water %</th>
                    <th>Fuel %</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="row in rows" :key="row.timestamp">
                    <td class="whitespace-nowrap">{{ fmtTime(row.timestamp) }}</td>
                    <td>{{ fmtVal(row.trip_log, 1) }}</td>
                    <td>{{ degreesToCompass(row.wind_direction) }}</td>
                    <td>{{ knotsToBeaufort(row.wind_speed) }}</td>
                    <td>—</td>
                    <td class="whitespace-nowrap">{{ fmtCoord(row.latitude, row.longitude) }}</td>
                    <td>{{ fmtVal(row.wp_distance, 1) }}</td>
                    <td class="whitespace-nowrap">{{ fmtTtg(row.wp_ttg) }}</td>
                    <td>{{ fmtPct(row.battery_soc) }}</td>
                    <td>{{ fmtPct(row.water_level) }}</td>
                    <td>{{ fmtPct(row.fuel_level) }}</td>
                </tr>
                <tr v-if="!rows.length">
                    <td colspan="11" class="text-center text-text-dim py-6">No log data for this period.</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<script setup>
const props = defineProps({
    rows: { type: Array, default: () => [] },
    showDate: { type: Boolean, default: false },
});

function fmtTime(ts) {
    const d = new Date(ts * 1000);
    if (props.showDate) {
        return d.toLocaleDateString('en-GB', { day: '2-digit', month: '2-digit' }) + ' ' +
               d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
    }
    return d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
}

function fmtVal(v, decimals = 1) {
    return v != null ? Number(v).toFixed(decimals) : '—';
}

function fmtPct(v) {
    return v != null ? Math.round(v) + '%' : '—';
}

function fmtCoord(lat, lon) {
    if (lat == null || lon == null) return '—';
    return formatDM(lat, 'N', 'S') + ' ' + formatDM(lon, 'E', 'W');
}

function formatDM(decimal, pos, neg) {
    const dir = decimal >= 0 ? pos : neg;
    const abs = Math.abs(decimal);
    const deg = Math.floor(abs);
    const min = ((abs - deg) * 60).toFixed(3);
    return `${deg}°${min}'${dir}`;
}

function fmtTtg(seconds) {
    if (seconds == null || seconds <= 0) return '—';
    const s = Math.floor(seconds);
    const d = Math.floor(s / 86400);
    const h = Math.floor((s % 86400) / 3600);
    const m = Math.floor((s % 3600) / 60);
    if (d > 0) return `${d}d ${h}h`;
    if (h > 0) return `${h}h ${m}m`;
    return `${m}m`;
}

function degreesToCompass(deg) {
    if (deg == null) return '—';
    const dirs = ['N','NNE','NE','ENE','E','ESE','SE','SSE','S','SSW','SW','WSW','W','WNW','NW','NNW'];
    return dirs[Math.round(((deg % 360) + 360) % 360 / 22.5) % 16];
}

function knotsToBeaufort(kn) {
    if (kn == null) return '—';
    if (kn < 1) return '0';
    if (kn <= 3) return '1';
    if (kn <= 6) return '2';
    if (kn <= 10) return '3';
    if (kn <= 16) return '4';
    if (kn <= 21) return '5';
    if (kn <= 27) return '6';
    if (kn <= 33) return '7';
    if (kn <= 40) return '8';
    if (kn <= 47) return '9';
    if (kn <= 55) return '10';
    if (kn <= 63) return '11';
    return '12';
}
</script>

<style scoped>
.log-table {
    width: 100%;
    font-size: 13px;
    font-variant-numeric: tabular-nums;
    border-collapse: collapse;
}

.log-table th {
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--color-text-dim);
    padding: 10px 8px;
    text-align: left;
    white-space: nowrap;
    border-bottom: 1px solid var(--color-border);
    position: sticky;
    top: 0;
    background: var(--color-surface);
    z-index: 1;
}

.log-table td {
    padding: 6px 8px;
    border-bottom: 1px solid var(--color-border-light);
}

.log-table tbody tr:nth-child(even) {
    background: var(--color-bg);
}

.log-table tbody tr:hover {
    background: oklch(0.96 0.006 70);
}
</style>
