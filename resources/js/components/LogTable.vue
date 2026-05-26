<template>
    <div class="log-wrap">
        <table class="log-table">
            <thead>
                <tr>
                    <th class="col-time group-end" title="Date and time of log entry">Date<br>Time <span class="th-unit">({{ tzLabel }})</span></th>
                    <th class="col-num" title="Course Over Ground (or heading if COG unavailable)">Crs<br><span class="th-unit">°</span></th>
                    <th class="col-num" title="Overall trip log reading (cumulative)">Total<br><span class="th-unit">nm</span></th>
                    <th class="col-num" title="Journey trip log reading">Log<br><span class="th-unit">nm</span></th>
                    <th class="col-num group-end" title="Distance run this hour (through water)">Dist<br><span class="th-unit">nm</span></th>
                    <th class="col-num" title="True wind direction">Wind<br><span class="th-unit">dir</span></th>
                    <th class="col-num" title="True wind speed (Beaufort scale)">Wind<br><span class="th-unit">bft</span></th>
                    <th class="col-num col-baro group-end" title="Barometric pressure (forecast)">Baro<br><span class="th-unit">hPa</span></th>
                    <th class="col-pos group-end" title="Latitude and longitude">Lat<br>Long</th>
                    <th class="col-num" title="Distance to next waypoint">WP<br><span class="th-unit">nm</span></th>
                    <th class="col-num" title="Distance Made Good toward waypoint this hour">DMG<br><span class="th-unit">nm</span></th>
                    <th class="col-num group-end" title="Time To Go to next waypoint">TTG</th>
                    <th class="col-num" title="House battery state of charge">Batt<br><span class="th-unit">%</span></th>
                    <th class="col-num" title="Fresh water tank level">H₂O<br><span class="th-unit">%</span></th>
                    <th class="col-num group-end" title="Diesel tank level">Fuel<br><span class="th-unit">%</span></th>
                    <th class="col-num" title="Efficiency: DMG minus Dist (positive = gaining on waypoint)">+/−<br><span class="th-unit">nm</span></th>
                    <th class="col-note" title="Log notes"></th>
                </tr>
            </thead>
            <tbody>
                <template v-for="row in rows" :key="row.id || row.timestamp">
                    <tr @click="toggleExpand(row)" class="data-row">
                        <td class="col-time group-end">
                            <div class="cell-date">{{ fmtDate(row.timestamp) }}</div>
                            <div class="cell-time">{{ fmtTime(row.timestamp) }}</div>
                        </td>
                        <td class="col-num">{{ fmtCourse(row.course) }}</td>
                        <td class="col-num">{{ fmtVal(row.total_log, 1) }}</td>
                        <td class="col-num">{{ fmtVal(row.trip_log, 1) }}</td>
                        <td class="col-num group-end">{{ fmtVal(row.dist, 1) }}</td>
                        <td class="col-num">{{ degreesToCompass(row.wind_direction) }}</td>
                        <td class="col-num">{{ knotsToBeaufort(row.wind_speed) }}</td>
                        <td class="col-num col-baro group-end">{{ fmtBaro(row.pressure) }}</td>
                        <td class="col-pos group-end">
                            <div>{{ fmtLat(row.latitude) }}</div>
                            <div>{{ fmtLon(row.longitude) }}</div>
                        </td>
                        <td class="col-num">{{ fmtVal(row.wp_distance, 1) }}</td>
                        <td class="col-num">{{ fmtVal(row.dmg, 1) }}</td>
                        <td class="col-num group-end">{{ fmtTtg(row.wp_ttg) }}</td>
                        <td class="col-num">{{ fmtPct(row.battery_soc) }}</td>
                        <td class="col-num">{{ fmtPct(row.water_level) }}</td>
                        <td class="col-num group-end">{{ fmtPct(row.fuel_level) }}</td>
                        <td class="col-num" :class="diffClass(row.diff)">{{ fmtDiff(row.diff) }}</td>
                        <td class="col-note" @click.stop="toggleExpand(row)">
                            <svg v-if="row.notes" class="note-icon note-icon-filled" viewBox="0 0 16 16" fill="currentColor">
                                <path d="M8 1C4.13 1 1 3.58 1 6.75c0 1.83 1.06 3.44 2.7 4.5L3 14.5l3.56-1.79C7.03 12.9 7.5 13 8 13c3.87 0 7-2.58 7-5.75S11.87 1 8 1z"/>
                            </svg>
                            <svg v-else class="note-icon note-icon-add" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
                                <line x1="8" y1="4" x2="8" y2="12"/>
                                <line x1="4" y1="8" x2="12" y2="8"/>
                            </svg>
                        </td>
                    </tr>
                    <tr v-if="isExpanded(row)" class="note-row">
                        <td :colspan="17">
                            <div class="note-content">
                                <div v-if="editingId === row.id" class="note-edit">
                                    <textarea
                                        ref="noteTextarea"
                                        v-model="editText"
                                        class="note-textarea"
                                        rows="2"
                                        placeholder="Add a note..."
                                        @keydown.enter.meta="saveNote(row)"
                                        @keydown.enter.ctrl="saveNote(row)"
                                        @keydown.escape="cancelEdit"
                                    ></textarea>
                                    <div class="note-actions">
                                        <button class="note-btn note-btn-cancel" @click="cancelEdit">Cancel</button>
                                        <button class="note-btn note-btn-save" @click="saveNote(row)" :disabled="saving">Save</button>
                                    </div>
                                </div>
                                <div v-else class="note-read" @click.stop="startEdit(row)">
                                    <span v-if="row.notes" class="note-text">{{ row.notes }}</span>
                                    <span v-else class="note-placeholder">Click to add a note...</span>
                                </div>
                            </div>
                        </td>
                    </tr>
                </template>
                <tr v-if="!rows.length">
                    <td colspan="17" class="empty-state">
                        No log data for this period. Start a journey from the Dashboard to begin logging, or select a different time period.
                    </td>
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
import { router } from '@inertiajs/vue3';
import uPlot from 'uplot';
import 'uplot/dist/uPlot.min.css';

const props = defineProps({
    rows: { type: Array, default: () => [] },
    timezone: { type: String, default: undefined },
    showAllNotes: { type: Boolean, default: false },
});

const expandedIds = ref(new Set());
const editingId = ref(null);
const editText = ref('');
const saving = ref(false);
const noteTextarea = ref(null);

function isExpanded(row) {
    if (!row.id) return false;
    if (props.showAllNotes && row.notes) return true;
    return expandedIds.value.has(row.id);
}

function toggleExpand(row) {
    if (!row.id) return;
    if (expandedIds.value.has(row.id)) {
        if (editingId.value === row.id) {
            cancelEdit();
        }
        expandedIds.value.delete(row.id);
    } else {
        expandedIds.value.add(row.id);
    }
}

function startEdit(row) {
    if (editingId.value && editingId.value !== row.id) {
        cancelEdit();
    }
    editingId.value = row.id;
    editText.value = row.notes || '';
    if (!expandedIds.value.has(row.id)) {
        expandedIds.value.add(row.id);
    }
    nextTick(() => {
        if (noteTextarea.value) {
            const el = Array.isArray(noteTextarea.value) ? noteTextarea.value[0] : noteTextarea.value;
            el?.focus();
        }
    });
}

function cancelEdit() {
    const wasId = editingId.value;
    editingId.value = null;
    editText.value = '';
    const row = props.rows.find(r => r.id === wasId);
    if (row && !row.notes && !props.showAllNotes) {
        expandedIds.value.delete(wasId);
    }
}

function saveNote(row) {
    if (saving.value) return;
    saving.value = true;
    router.patch(`/admin/ship-log/${row.id}`, {
        notes: editText.value || null,
    }, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            row.notes = editText.value || null;
            editingId.value = null;
            editText.value = '';
            saving.value = false;
        },
        onError: () => {
            saving.value = false;
        },
    });
}

const tzLabel = computed(() => {
    if (!props.timezone) return Intl.DateTimeFormat().resolvedOptions().timeZone.split('/').pop().replace(/_/g, ' ');
    if (props.timezone === 'UTC') return 'UTC';
    return props.timezone.split('/').pop().replace(/_/g, ' ');
});

function cssVar(name) {
    return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
}

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

function tzOpts() {
    return props.timezone ? { timeZone: props.timezone } : {};
}

function initChart() {
    if (!chartEl.value || progressData.value.length < 2) return;
    if (chart) { chart.destroy(); chart = null; }

    const timestamps = progressData.value.map(d => d.timestamp);
    const values = progressData.value.map(d => d.cum_diff);
    const tz = tzOpts();

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
                        ctx.strokeStyle = cssVar('--color-text-dim');
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
                stroke: cssVar('--color-text-dim'),
                grid: { stroke: cssVar('--color-border-light'), width: 1 },
                font: '10px system-ui',
                values: (u, vals) => vals.map(v => {
                    const d = new Date(v * 1000);
                    return d.toLocaleDateString('en-GB', { day: '2-digit', month: '2-digit', ...tz }) + '\n' +
                           d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', ...tz });
                }),
            },
            {
                stroke: cssVar('--color-text-dim'),
                grid: { stroke: cssVar('--color-border-light'), width: 1 },
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
                    return last >= 0 ? cssVar('--color-green') : cssVar('--color-scarlet');
                },
                fill: (u) => {
                    const last = u.data[1][u.data[1].length - 1];
                    return last >= 0 ? cssVar('--color-green-bg') : cssVar('--color-error-bg');
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

watch(() => props.rows, () => nextTick(() => initChart()), { deep: true });

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
    cursor: help;
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

.group-end {
    border-right: 2px solid var(--color-border) !important;
}

.col-time { text-align: left !important; cursor: default; }
.col-num { text-align: right; }
.col-pos { text-align: right; font-size: 11px; }
.col-baro { font-style: italic; opacity: 0.6; }

.col-note {
    width: 28px;
    text-align: center !important;
    padding: 4px 2px;
    cursor: pointer;
}

.note-icon {
    width: 14px;
    height: 14px;
    display: inline-block;
    vertical-align: middle;
}

.note-icon-filled {
    color: var(--color-text-dim);
}

.note-icon-add {
    color: var(--color-border);
    opacity: 0.6;
}

.data-row {
    cursor: pointer;
}

.data-row:hover .note-icon-add {
    opacity: 1;
    color: var(--color-text-dim);
}

.cell-date {
    font-size: 10px;
    color: var(--color-text-dim);
    line-height: 1.2;
}

.cell-time {
    font-weight: 600;
    line-height: 1.2;
}

.log-table tbody .data-row:nth-child(even of .data-row) {
    background: var(--color-bg);
}

.log-table tbody .data-row:hover {
    background: var(--color-scarlet-light);
}

.note-row td {
    padding: 0;
    border-bottom: 1px solid var(--color-border-light);
    background: var(--color-bg);
}

.note-content {
    padding: 8px 12px;
}

.note-read {
    cursor: pointer;
    padding: 4px 0;
    min-height: 24px;
}

.note-text {
    font-size: 12px;
    color: var(--color-text-secondary);
    white-space: pre-wrap;
    line-height: 1.5;
}

.note-placeholder {
    font-size: 12px;
    color: var(--color-text-dim);
    opacity: 0.5;
    font-style: italic;
}

.note-edit {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.note-textarea {
    width: 100%;
    font-size: 12px;
    font-family: inherit;
    line-height: 1.5;
    padding: 6px 8px;
    border: 1px solid var(--color-border);
    border-radius: 6px;
    background: var(--color-surface);
    color: var(--color-text-primary);
    resize: vertical;
    min-height: 48px;
}

.note-textarea:focus {
    outline: none;
    border-color: var(--color-text-dim);
}

.note-actions {
    display: flex;
    justify-content: flex-end;
    gap: 6px;
}

.note-btn {
    font-size: 11px;
    font-weight: 600;
    padding: 4px 12px;
    border-radius: 4px;
    border: none;
    cursor: pointer;
    transition: background 0.1s ease;
}

.note-btn-cancel {
    background: transparent;
    color: var(--color-text-dim);
}

.note-btn-cancel:hover {
    background: var(--color-border-light);
}

.note-btn-save {
    background: var(--color-text-primary);
    color: var(--color-surface);
}

.note-btn-save:hover {
    opacity: 0.9;
}

.note-btn-save:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.empty-state {
    text-align: center;
    color: var(--color-text-dim);
    padding: 32px 16px;
    font-size: 13px;
}

.diff-good {
    color: var(--color-green);
    font-weight: 600;
}

.diff-bad {
    color: var(--color-scarlet);
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

.progress-good { color: var(--color-green); }
.progress-bad { color: var(--color-scarlet); }

.progress-canvas {
    margin: 0 -16px;
}

:deep(.u-wrap) { position: relative !important; }
</style>
