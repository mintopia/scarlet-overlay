<template>
    <div class="log-wrap">
        <table class="log-table">
            <thead>
                <tr>
                    <th class="col-time">Date<br>Time <span class="th-unit">({{ tzLabel }})</span></th>
                    <th class="col-num">Crs<br><span class="th-unit">°</span></th>
                    <th class="col-num">Log<br><span class="th-unit">nm</span></th>
                    <th class="col-num">Dist<br><span class="th-unit">nm</span></th>
                    <th class="col-num">Total<br><span class="th-unit">nm</span></th>
                    <th class="col-num">Wind<br><span class="th-unit">dir</span></th>
                    <th class="col-num">Wind<br><span class="th-unit">bft</span></th>
                    <th class="col-num col-baro">Baro<br><span class="th-unit">hPa</span></th>
                    <th class="col-pos">Lat<br>Long</th>
                    <th class="col-num">WP<br><span class="th-unit">nm</span></th>
                    <th class="col-num">DMG<br><span class="th-unit">nm</span></th>
                    <th class="col-num">TTG</th>
                    <th class="col-num">Batt<br><span class="th-unit">%</span></th>
                    <th class="col-num">H₂O<br><span class="th-unit">%</span></th>
                    <th class="col-num">Fuel<br><span class="th-unit">%</span></th>
                    <th class="col-num">+/−<br><span class="th-unit">nm</span></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="row in rows" :key="row.timestamp">
                    <td class="col-time">
                        <div class="cell-date">{{ fmtDate(row.timestamp) }}</div>
                        <div class="cell-time">{{ fmtTime(row.timestamp) }}</div>
                    </td>
                    <td class="col-num">{{ fmtCourse(row.course) }}</td>
                    <td class="col-num">{{ fmtVal(row.trip_log, 1) }}</td>
                    <td class="col-num">{{ fmtVal(row.dist, 1) }}</td>
                    <td class="col-num">{{ fmtVal(row.total_log, 1) }}</td>
                    <td class="col-num">{{ degreesToCompass(row.wind_direction) }}</td>
                    <td class="col-num">{{ knotsToBeaufort(row.wind_speed) }}</td>
                    <td class="col-num col-baro">{{ fmtBaro(row.pressure) }}</td>
                    <td class="col-pos">
                        <div>{{ fmtLat(row.latitude) }}</div>
                        <div>{{ fmtLon(row.longitude) }}</div>
                    </td>
                    <td class="col-num">{{ fmtVal(row.wp_distance, 1) }}</td>
                    <td class="col-num">{{ fmtVal(row.dmg, 1) }}</td>
                    <td class="col-num">{{ fmtTtg(row.wp_ttg) }}</td>
                    <td class="col-num">{{ fmtPct(row.battery_soc) }}</td>
                    <td class="col-num">{{ fmtPct(row.water_level) }}</td>
                    <td class="col-num">{{ fmtPct(row.fuel_level) }}</td>
                    <td class="col-num" :class="diffClass(row.diff)">{{ fmtDiff(row.diff) }}</td>
                </tr>
                <tr v-if="!rows.length">
                    <td colspan="16" class="text-center text-text-dim py-6">No log data for this period.</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div v-if="progressData.length > 1" class="progress-chart panel mt-4">
        <div class="progress-head">
            <span class="progress-title">Passage Efficiency</span>
            <span class="progress-summary" :class="lastDiff >= 0 ? 'progress-good' : 'progress-bad'">
                {{ lastDiff >= 0 ? '+' : '' }}{{ lastDiff.toFixed(1) }} nm {{ lastDiff >= 0 ? 'gained' : 'lost' }}
            </span>
        </div>
        <div ref="chartEl" class="progress-canvas"></div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
import uPlot from 'uplot';
import 'uplot/dist/uPlot.min.css';

const props = defineProps({
    rows: { type: Array, default: () => [] },
    timezone: { type: String, default: undefined },
});

const tzLabel = computed(() => {
    if (!props.timezone) return Intl.DateTimeFormat().resolvedOptions().timeZone.split('/').pop().replace(/_/g, ' ');
    if (props.timezone === 'UTC') return 'UTC';
    return props.timezone.split('/').pop().replace(/_/g, ' ');
});

const chartEl = ref(null);
let chart = null;

const progressData = computed(() => {
    return props.rows.filter(r => r.cum_diff != null);
});

const lastDiff = computed(() => {
    const data = progressData.value;
    if (!data.length) return 0;
    return data[data.length - 1].cum_diff;
});

function initChart() {
    if (!chartEl.value || progressData.value.length < 2) return;
    if (chart) { chart.destroy(); chart = null; }

    const timestamps = progressData.value.map(d => d.timestamp);
    const values = progressData.value.map(d => d.cum_diff);

    chart = new uPlot({
        width: chartEl.value.offsetWidth,
        height: 160,
        cursor: { show: true, drag: { x: false, y: false } },
        select: { show: false },
        legend: { show: false },
        hooks: {
            draw: [
                (u) => {
                    const ctx = u.ctx;
                    const zeroY = u.valToPos(0, 'y');
                    if (zeroY >= u.bbox.top && zeroY <= u.bbox.top + u.bbox.height) {
                        ctx.save();
                        ctx.strokeStyle = 'oklch(0.50 0.005 40)';
                        ctx.lineWidth = 1;
                        ctx.setLineDash([4, 3]);
                        ctx.beginPath();
                        ctx.moveTo(u.bbox.left, zeroY);
                        ctx.lineTo(u.bbox.left + u.bbox.width, zeroY);
                        ctx.stroke();
                        ctx.restore();
                    }
                },
            ],
        },
        axes: [
            {
                stroke: 'oklch(0.65 0.005 40)',
                grid: { stroke: 'oklch(0.94 0.003 70)', width: 1 },
                font: '10px system-ui',
                values: (u, vals) => vals.map(v => {
                    const d = new Date(v * 1000);
                    return d.toLocaleDateString('en-GB', { day: '2-digit', month: '2-digit' }) + '\n' +
                           d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
                }),
            },
            {
                stroke: 'oklch(0.65 0.005 40)',
                grid: { stroke: 'oklch(0.94 0.003 70)', width: 1 },
                font: '10px system-ui',
                size: 45,
                values: (u, vals) => vals.map(v => (v > 0 ? '+' : '') + v.toFixed(1)),
            },
        ],
        series: [
            {},
            {
                stroke: (u) => {
                    const last = u.data[1][u.data[1].length - 1];
                    return last >= 0 ? 'oklch(0.55 0.16 155)' : 'oklch(0.55 0.18 27)';
                },
                fill: (u) => {
                    const last = u.data[1][u.data[1].length - 1];
                    return last >= 0 ? 'oklch(0.55 0.16 155 / 0.08)' : 'oklch(0.55 0.18 27 / 0.08)';
                },
                width: 2,
            },
        ],
        scales: {
            x: { time: true },
            y: {
                range: (u, min, max) => {
                    const absMax = Math.max(Math.abs(min), Math.abs(max)) * 1.2 || 1;
                    return [-absMax, absMax];
                },
            },
        },
    }, [timestamps, values], chartEl.value);
}

let resizeTimeout;
function handleResize() {
    clearTimeout(resizeTimeout);
    resizeTimeout = setTimeout(() => {
        if (chart && chartEl.value) {
            chart.setSize({ width: chartEl.value.offsetWidth, height: 160 });
        }
    }, 150);
}

onMounted(() => {
    nextTick(() => initChart());
    window.addEventListener('resize', handleResize);
});

onUnmounted(() => {
    if (chart) chart.destroy();
    window.removeEventListener('resize', handleResize);
});

function tzOpts() {
    return props.timezone ? { timeZone: props.timezone } : {};
}

function fmtDate(ts) {
    const d = new Date(ts * 1000);
    return d.toLocaleDateString('en-GB', { day: '2-digit', month: '2-digit', year: '2-digit', ...tzOpts() });
}

function fmtTime(ts) {
    const d = new Date(ts * 1000);
    return d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', ...tzOpts() });
}

function fmtVal(v, decimals = 1) {
    return v != null ? Number(v).toFixed(decimals) : '—';
}

function fmtPct(v) {
    return v != null ? Math.round(v) + '' : '—';
}

function fmtCourse(v) {
    if (v == null) return '—';
    return Math.round(v) + '°';
}

function fmtBaro(v) {
    if (v == null) return '—';
    return Math.round(v);
}

function fmtDiff(v) {
    if (v == null) return '—';
    const sign = v > 0 ? '+' : '';
    return sign + v.toFixed(1);
}

function diffClass(v) {
    if (v == null) return '';
    if (v > 0.05) return 'diff-good';
    if (v < -0.05) return 'diff-bad';
    return '';
}

function fmtLat(lat) {
    if (lat == null) return '—';
    return formatDM(lat, 'N', 'S');
}

function fmtLon(lon) {
    if (lon == null) return '';
    return formatDM(lon, 'E', 'W');
}

function formatDM(decimal, pos, neg) {
    const dir = decimal >= 0 ? pos : neg;
    const abs = Math.abs(decimal);
    const deg = Math.floor(abs);
    const min = ((abs - deg) * 60).toFixed(1).padStart(4, '0');
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
.log-wrap {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.log-table {
    width: 100%;
    font-size: 12px;
    font-variant-numeric: tabular-nums;
    border-collapse: collapse;
    white-space: nowrap;
}

.log-table th {
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: var(--color-text-dim);
    padding: 6px 6px;
    text-align: right;
    border-bottom: 2px solid var(--color-border);
    position: sticky;
    top: 0;
    background: var(--color-surface);
    z-index: 1;
    line-height: 1.3;
}

.th-unit {
    font-weight: 500;
    text-transform: none;
    letter-spacing: 0;
    opacity: 0.6;
}

.log-table td {
    padding: 4px 6px;
    border-bottom: 1px solid var(--color-border-light);
}

.col-time { text-align: left !important; }
.col-num { text-align: right; }
.col-pos { text-align: right; font-size: 11px; }
.col-baro { font-style: italic; opacity: 0.6; }

.cell-date {
    font-size: 10px;
    color: var(--color-text-dim);
    line-height: 1.2;
}

.cell-time {
    font-weight: 600;
    line-height: 1.2;
}

.log-table tbody tr:nth-child(even) {
    background: var(--color-bg);
}

.log-table tbody tr:hover {
    background: oklch(0.96 0.006 70);
}

.diff-good {
    color: oklch(0.55 0.16 155);
    font-weight: 600;
}

.diff-bad {
    color: oklch(0.55 0.18 27);
    font-weight: 600;
}

.progress-chart {
    padding: 14px 16px 0;
}

.progress-head {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    margin-bottom: 8px;
}

.progress-title {
    font-size: 13px;
    font-weight: 600;
}

.progress-summary {
    font-size: 12px;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
}

.progress-good { color: oklch(0.55 0.16 155); }
.progress-bad { color: oklch(0.55 0.18 27); }

.progress-canvas {
    margin: 0 -16px;
}

:deep(.u-wrap) { position: relative !important; }
</style>
