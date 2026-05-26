<template>
    <AdminLayout :breadcrumbs="[{ label: 'Explore', href: '/admin/explore' }, { label: metric.label }]">
        <Head :title="metric.label" />

        <!-- Page header / toolbar -->
        <div class="toolbar">
            <div class="toolbar-left">
                <h1 class="metric-title">{{ metric.label }}</h1>
                <span v-if="metric.unit" class="metric-title-unit">{{ metric.unit }}</span>
            </div>

            <div class="toolbar-right">
                <div class="preset-cluster">
                    <button v-for="p in recentPresets" :key="p" class="preset-btn" :class="{ 'preset-btn--active': range === p && !zoomed }" @click="switchRange(p)">{{ p }}</button>
                </div>
                <div class="preset-cluster">
                    <button v-for="p in extendedPresets" :key="p" class="preset-btn" :class="{ 'preset-btn--active': range === p && !zoomed }" @click="switchRange(p)">{{ p }}</button>
                </div>
                <div class="preset-cluster">
                    <button class="preset-btn preset-btn--passage" :class="{ 'preset-btn--active': range === 'passage' && !zoomed, 'preset-btn--disabled': !passage.available }" :disabled="!passage.available" :title="passage.available ? '' : 'No journeys'" @click="switchRange('passage')">&#9875; Passage</button>
                    <button class="preset-btn" :class="{ 'preset-btn--active': range === 'custom' || zoomed }" @click="showCustomPicker = !showCustomPicker">Custom</button>
                </div>

                <span class="toolbar-sep">|</span>

                <select v-model.number="refreshInterval" class="refresh-select" @change="restartRefresh">
                    <option :value="0">Off</option>
                    <option :value="15">15s</option>
                    <option :value="30">30s</option>
                    <option :value="60">1m</option>
                    <option :value="300">5m</option>
                </select>

                <button v-if="zoomed" class="preset-btn preset-btn--reset" @click="resetZoom">Reset zoom</button>
            </div>
        </div>

        <!-- Custom date picker -->
        <div v-if="showCustomPicker" class="custom-picker">
            <label class="custom-picker-label">
                From
                <input type="datetime-local" v-model="customStart" class="custom-picker-input" />
            </label>
            <label class="custom-picker-label">
                To
                <input type="datetime-local" v-model="customEnd" class="custom-picker-input" />
            </label>
            <button class="custom-picker-apply" @click="applyCustomRange">Apply</button>
        </div>

        <!-- Prometheus error banner -->
        <div v-if="fetchError" class="error-banner">
            Metrics unavailable — retrying in {{ retryCountdown }}s
        </div>

        <!-- Chart panel -->
        <div class="panel mt-4">
            <div class="panel-head">
                <div class="chart-legend">
                    <span class="legend-dot" :style="{ background: signedCurrentColor }"></span>
                    <span class="panel-title">{{ metric.label }}</span>
                    <span v-if="stats.current != null" class="chart-stats">
                        <span class="chart-stat-current" :style="{ color: signedCurrentColor }">{{ metric.signed && stats.current > 0 ? '+' : '' }}{{ fmtVal(stats.current) }} {{ metric.unit }}</span>
                        <span class="chart-stat-sep">&middot;</span>
                        <span>{{ fmtVal(stats.min) }} – {{ fmtVal(stats.max) }} {{ metric.unit }}</span>
                        <span class="chart-stat-sep">&middot;</span>
                        <span>avg {{ fmtVal(stats.avg) }}</span>
                    </span>
                </div>
                <button v-if="activeOverlays.length < maxOverlays" class="overlay-btn" @click="showOverlayPicker = !showOverlayPicker">+ Overlay</button>
                <span v-else class="text-[11px] text-text-dim">3 series max</span>
            </div>

            <div ref="chartEl" class="chart-container" tabindex="0">
                <div v-if="!chartData?.length" class="chart-empty">No data for this time range</div>
            </div>

            <!-- Tooltip -->
            <div v-if="tooltipData" class="chart-tooltip" :style="{ left: tooltipData.x + 'px' }">
                <div class="chart-tooltip-time">{{ formatTooltipTime(tooltipData.ts) }}</div>
                <div v-for="entry in tooltipData.entries" :key="entry.label" class="chart-tooltip-row">
                    <span class="legend-dot legend-dot--sm" :style="{ background: entry.color }"></span>
                    <span class="chart-tooltip-val">{{ fmtVal(entry.value) }} {{ entry.unit }}</span>
                </div>
                <div class="chart-tooltip-hint">Drag to zoom &middot; Double-click to reset</div>
            </div>
        </div>

        <!-- Overlay picker -->
        <div v-if="showOverlayPicker" class="panel mt-2 overlay-picker">
            <div class="overlay-picker-head">
                <span class="text-[13px] font-semibold">Add Overlay</span>
                <button class="text-[11px] text-text-dim" @click="showOverlayPicker = false">&times; Close</button>
            </div>
            <div class="overlay-picker-grid">
                <template v-for="(groupMetrics, group) in metrics" :key="group">
                    <div class="overlay-picker-group">{{ groupLabel(group) }}</div>
                    <button
                        v-for="(m, slug) in groupMetrics"
                        :key="slug"
                        class="overlay-picker-item"
                        :class="{
                            'overlay-picker-item--active': slug === metric.slug || activeOverlays.includes(slug),
                            'overlay-picker-item--disabled': activeOverlays.length >= maxOverlays && !activeOverlays.includes(slug),
                        }"
                        :disabled="slug === metric.slug || (activeOverlays.length >= maxOverlays && !activeOverlays.includes(slug))"
                        @click="toggleOverlay(slug)"
                    >
                        <span class="legend-dot legend-dot--sm" :style="{ background: m.color }"></span>
                        {{ m.label }}
                    </button>
                </template>
            </div>
        </div>

        <!-- Active overlays legend bar -->
        <div v-if="overlayData.length" class="panel mt-2 overlay-legend">
            <span class="text-[12px] font-semibold" style="color: var(--color-text-secondary)">Overlays</span>
            <div v-for="ov in overlayData" :key="ov.metric.slug" class="overlay-legend-item">
                <span class="legend-dot legend-dot--sm" :style="{ background: ov.metric.color }"></span>
                <span class="text-[11px] font-medium">{{ ov.metric.label }}</span>
                <button class="overlay-remove" @click="removeOverlay(ov.metric.slug)">&times;</button>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { ref, computed, nextTick, onMounted, onUnmounted } from 'vue';
import uPlot from 'uplot';
import 'uplot/dist/uPlot.min.css';

const props = defineProps({
    metric: Object,
    metrics: Object,
    data: Array,
    overlays: Array,
    range: String,
    start: Number,
    end: Number,
    step: String,
    refresh: Number,
    passage: Object,
});

function cssVar(name) {
    return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
}

const recentPresets = ['1h', '6h', '24h'];
const extendedPresets = ['3d', '7d', '30d'];

const zoomed = ref(false);
const showCustomPicker = ref(false);
const customStart = ref('');
const customEnd = ref('');
const refreshInterval = ref(props.refresh);
let refreshTimer = null;

const chartEl = ref(null);
let chart = null;
const tooltipData = ref(null);
const chartData = ref(props.data);
const stats = ref({ current: null, min: null, max: null, avg: null });

const showOverlayPicker = ref(false);
const activeOverlays = ref(props.overlays?.map(o => o.metric.slug) || []);
const overlayData = ref(props.overlays || []);
const maxOverlays = 3;

const signedCurrentColor = computed(() => {
    if (!props.metric.signed) return props.metric.color;
    if (stats.value.current == null) return props.metric.color;
    return stats.value.current >= 0 ? cssVar('--color-green') : cssVar('--color-amber');
});

const fetchError = ref(false);
const retryCountdown = ref(0);
let countdownTimer = null;

const groupLabels = {
    navigation: 'Navigation',
    wind: 'Wind',
    power: 'Power',
    cabin: 'Cabin',
    tanks: 'Tanks',
    tracker: 'Tracker',
};

function groupLabel(group) {
    return groupLabels[group] || group;
}

function buildUrl(params) {
    const base = '/admin/explore';
    const query = new URLSearchParams();
    for (const [k, v] of Object.entries(params)) {
        if (v != null && v !== '') query.set(k, v);
    }
    return `${base}?${query.toString()}`;
}

function switchRange(range) {
    zoomed.value = false;
    showCustomPicker.value = false;
    localStorage.setItem('scarlet_explore_range', range);
    router.get(buildUrl({ metric: props.metric.slug, range }));
}

function applyCustomRange() {
    if (!customStart.value || !customEnd.value) return;
    const start = Math.floor(new Date(customStart.value).getTime() / 1000);
    const end = Math.floor(new Date(customEnd.value).getTime() / 1000);
    showCustomPicker.value = false;
    router.get(buildUrl({ metric: props.metric.slug, range: 'custom', start, end }));
}

function resetZoom() {
    zoomed.value = false;
    switchRange(props.range === 'custom' ? '24h' : props.range);
}

function fmtVal(v) {
    if (v == null) return '—';
    if (Math.abs(v) >= 100) return v.toFixed(0);
    if (Math.abs(v) >= 10) return v.toFixed(1);
    return v.toFixed(2);
}

function formatTimestamp(ts) {
    const d = new Date(ts * 1000);
    const rangeDuration = props.end - props.start;
    if (rangeDuration <= 86400) {
        return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }
    if (rangeDuration <= 604800) {
        return d.toLocaleDateString([], { day: 'numeric', month: 'short' }) + ' ' +
            d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }
    return d.toLocaleDateString([], { day: 'numeric', month: 'short' });
}

function formatTooltipTime(ts) {
    const d = new Date(ts * 1000);
    return d.toLocaleDateString([], { day: 'numeric', month: 'short', year: 'numeric' }) + ', ' +
        d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

function computeStats() {
    const vals = (chartData.value || []).map(d => d.value).filter(v => v != null);
    if (!vals.length) {
        stats.value = { current: null, min: null, max: null, avg: null };
        return;
    }
    stats.value = {
        current: vals[vals.length - 1],
        min: Math.min(...vals),
        max: Math.max(...vals),
        avg: vals.reduce((a, b) => a + b, 0) / vals.length,
    };
}

function prepareData() {
    const timestamps = chartData.value.map(d => d.timestamp);
    const values = chartData.value.map(d => d.value);

    if (props.metric.signed) {
        const pos = values.map(v => v != null && v >= 0 ? v : null);
        const neg = values.map(v => v != null && v < 0 ? v : null);
        const series = [timestamps, pos, neg];
        for (const ov of overlayData.value) {
            series.push(ov.data.map(d => d.value));
        }
        return series;
    }

    const series = [timestamps, values];
    for (const ov of overlayData.value) {
        series.push(ov.data.map(d => d.value));
    }
    return series;
}

function drawSignedPath(u, seriesIdx, idx0, idx1) {
    const s = u.series[seriesIdx];
    const xdata = u.data[0];
    const ydata = u.data[seriesIdx];
    const stroke = new Path2D();
    const fill = new Path2D();
    const zeroY = u.valToPos(0, 'y', true);
    let started = false;
    let lastX;

    for (let i = idx0; i <= idx1; i++) {
        const val = ydata[i];
        if (val == null) {
            if (started) {
                fill.lineTo(lastX, zeroY);
                fill.closePath();
                started = false;
            }
            continue;
        }
        const x = u.valToPos(xdata[i], 'x', true);
        const y = u.valToPos(val, 'y', true);
        if (!started) {
            stroke.moveTo(x, y);
            fill.moveTo(x, zeroY);
            fill.lineTo(x, y);
            started = true;
        } else {
            stroke.lineTo(x, y);
            fill.lineTo(x, y);
        }
        lastX = x;
    }
    if (started) {
        fill.lineTo(lastX, zeroY);
        fill.closePath();
    }

    return { stroke, fill, clip: null, band: null, gaps: null, flags: 0 };
}

function buildChartOpts(width) {
    const isSigned = props.metric.signed;
    const fillColor = props.metric.color.replace(')', ' / 0.08)');

    const opts = {
        width,
        height: window.innerWidth < 768 ? 280 : 400,
        cursor: {
            drag: { x: true, y: false, setScale: false },
        },
        select: {
            show: true,
            over: true,
        },
        hooks: {
            draw: isSigned ? [
                (u) => {
                    const ctx = u.ctx;
                    const zeroY = u.valToPos(0, 'y');
                    ctx.save();
                    ctx.strokeStyle = cssVar('--color-text-dim');
                    ctx.lineWidth = 1;
                    ctx.setLineDash([4, 3]);
                    ctx.beginPath();
                    ctx.moveTo(u.bbox.left, zeroY);
                    ctx.lineTo(u.bbox.left + u.bbox.width, zeroY);
                    ctx.stroke();
                    ctx.restore();
                },
            ] : [],
            setSelect: [
                (u) => {
                    const min = u.posToVal(u.select.left, 'x');
                    const max = u.posToVal(u.select.left + u.select.width, 'x');
                    if (max - min < 60) return;
                    zoomed.value = true;
                    const start = Math.floor(min);
                    const end = Math.floor(max);
                    router.get(buildUrl({
                        metric: props.metric.slug,
                        range: 'custom',
                        start,
                        end,
                    }), {}, { preserveState: false });
                },
            ],
            setCursor: [
                (u) => {
                    const idx = u.cursor.idx;
                    if (idx == null) {
                        tooltipData.value = null;
                        return;
                    }
                    const ts = u.data[0][idx];
                    const entries = [];
                    for (let i = 1; i < u.data.length; i++) {
                        const val = u.data[i][idx];
                        if (val != null) {
                            entries.push({
                                label: u.series[i].label,
                                value: val,
                                color: u.series[i].stroke,
                                unit: u.series[i]._unit || '',
                            });
                        }
                    }
                    tooltipData.value = { ts, x: u.cursor.left, entries };
                },
            ],
        },
        axes: [
            {
                stroke: cssVar('--color-text-dim'),
                grid: { stroke: cssVar('--color-border-light'), width: 1 },
                ticks: { stroke: cssVar('--color-border'), width: 1 },
                font: '10px system-ui',
                values: (u, vals) => vals.map(v => formatTimestamp(v)),
            },
            {
                stroke: cssVar('--color-text-dim'),
                grid: { stroke: cssVar('--color-border-light'), width: 1 },
                ticks: { stroke: cssVar('--color-border'), width: 1 },
                font: '10px system-ui',
                size: 50,
                values: (u, vals) => vals.map(v => {
                    if (v == null) return '';
                    return isSigned && v > 0 ? `+${fmtVal(v)}` : fmtVal(v);
                }),
            },
        ],
        series: isSigned ? [
            {},
            {
                label: 'Charging',
                stroke: cssVar('--color-green'),
                fill: cssVar('--color-green-bg'),
                width: 1.5,
                _unit: props.metric.unit,
                paths: drawSignedPath,
            },
            {
                label: 'Discharging',
                stroke: cssVar('--color-amber'),
                fill: cssVar('--color-amber-bg'),
                width: 1.5,
                _unit: props.metric.unit,
                paths: drawSignedPath,
            },
        ] : [
            {},
            {
                label: props.metric.label,
                stroke: props.metric.color,
                fill: fillColor,
                width: 1.5,
                _unit: props.metric.unit,
            },
        ],
        scales: {
            x: { time: true },
            y: isSigned ? {
                range: (u, min, max) => {
                    const absMax = Math.max(Math.abs(min), Math.abs(max)) * 1.15 || 50;
                    return [-absMax, absMax];
                },
            } : {
                range: (u, min, max) => {
                    const pad = (max - min) * 0.1 || 1;
                    return [min - pad, max + pad];
                },
            },
        },
    };

    if (overlayData.value.length) {
        for (const ov of overlayData.value) {
            opts.series.push({
                label: ov.metric.label,
                stroke: ov.metric.color,
                width: 1.5,
                _unit: ov.metric.unit,
                scale: ov.metric.unit === props.metric.unit ? 'y' : 'y2',
            });
        }

        const hasSecondAxis = overlayData.value.some(ov => ov.metric.unit !== props.metric.unit);
        if (hasSecondAxis) {
            opts.axes.push({
                side: 1,
                stroke: cssVar('--color-text-dim'),
                grid: { show: false },
                font: '10px system-ui',
                size: 50,
            });
            opts.scales.y2 = {};
        }
    }

    return opts;
}

function initChart() {
    if (!chartEl.value || !chartData.value?.length) return;
    if (chart) { chart.destroy(); chart = null; }

    chart = new uPlot(buildChartOpts(chartEl.value.offsetWidth), prepareData(), chartEl.value);
}

function rebuildChart() {
    if (chart) { chart.destroy(); chart = null; }
    nextTick(() => initChart());
}

async function toggleOverlay(slug) {
    if (activeOverlays.value.includes(slug)) {
        removeOverlay(slug);
        return;
    }
    if (activeOverlays.value.length >= maxOverlays) return;

    let metricConfig = null;
    for (const group of Object.values(props.metrics)) {
        if (group[slug]) { metricConfig = { ...group[slug], slug }; break; }
    }
    if (!metricConfig) return;

    const url = `/admin/explore/series?metric=${slug}&start=${props.start}&end=${props.end}&step=${props.step}`;
    try {
        const res = await fetch(url);
        if (!res.ok) return;
        const json = await res.json();
        const series = json[0];

        activeOverlays.value.push(slug);
        overlayData.value.push({ metric: metricConfig, data: series.data });
        rebuildChart();
    } catch {
        // Silent
    }
}

function removeOverlay(slug) {
    activeOverlays.value = activeOverlays.value.filter(s => s !== slug);
    overlayData.value = overlayData.value.filter(o => o.metric.slug !== slug);
    rebuildChart();
}

function restartRefresh() {
    clearInterval(refreshTimer);
    clearInterval(countdownTimer);
    retryCountdown.value = refreshInterval.value;

    if (refreshInterval.value > 0) {
        countdownTimer = setInterval(() => {
            retryCountdown.value = Math.max(0, retryCountdown.value - 1);
        }, 1000);
        refreshTimer = setInterval(() => {
            doRefresh();
            retryCountdown.value = refreshInterval.value;
        }, refreshInterval.value * 1000);
    }
}

async function doRefresh() {
    if (zoomed.value) return;
    const end = Math.floor(Date.now() / 1000);
    const duration = props.end - props.start;
    const start = end - duration;

    const slugs = [props.metric.slug, ...activeOverlays.value];
    const url = `/admin/explore/series?metrics=${slugs.join(',')}&start=${start}&end=${end}&step=${props.step}`;

    try {
        const res = await fetch(url);
        if (!res.ok) return;
        const json = await res.json();

        const primary = json.find(s => s.metric === props.metric.slug);
        if (primary) {
            chartData.value = primary.data;
            computeStats();
        }

        for (const ov of overlayData.value) {
            const updated = json.find(s => s.metric === ov.metric.slug);
            if (updated) ov.data = updated.data;
        }

        if (chart) {
            chart.setData(prepareData());
        }

        fetchError.value = false;
    } catch {
        fetchError.value = true;
        retryCountdown.value = refreshInterval.value;
    }
}

let resizeTimeout;
function handleResize() {
    clearTimeout(resizeTimeout);
    resizeTimeout = setTimeout(() => {
        if (chart && chartEl.value) {
            chart.setSize({ width: chartEl.value.offsetWidth, height: window.innerWidth < 768 ? 280 : 400 });
        }
    }, 150);
}

onMounted(() => {
    computeStats();
    nextTick(() => initChart());
    window.addEventListener('resize', handleResize);
    restartRefresh();

    chartEl.value?.addEventListener('dblclick', () => resetZoom());
    chartEl.value?.addEventListener('keydown', (e) => {
        if (!chart || !['ArrowLeft', 'ArrowRight'].includes(e.key)) return;
        e.preventDefault();
        const idx = chart.cursor.idx ?? 0;
        const newIdx = e.key === 'ArrowRight'
            ? Math.min(idx + 1, chart.data[0].length - 1)
            : Math.max(idx - 1, 0);
        chart.setCursor({ left: chart.valToPos(chart.data[0][newIdx], 'x'), top: 0 });
    });
});

onUnmounted(() => {
    if (chart) chart.destroy();
    clearInterval(refreshTimer);
    clearInterval(countdownTimer);
    window.removeEventListener('resize', handleResize);
});
</script>

<style scoped>
.toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 8px;
}

.toolbar-left {
    display: flex;
    align-items: center;
    gap: 8px;
}

.toolbar-right {
    display: flex;
    align-items: center;
    gap: 4px;
    flex-wrap: wrap;
}

.toolbar-sep { color: var(--color-border); }

.metric-title {
    font-weight: 700;
    font-size: 15px;
    color: var(--color-text-primary);
    margin: 0;
    line-height: 1;
}

.metric-title-unit {
    font-size: 12px;
    color: var(--color-text-dim);
    font-weight: 500;
}

.preset-cluster { display: flex; gap: 3px; }

.preset-btn {
    padding: 4px 8px;
    background: var(--color-bg);
    border: 1px solid var(--color-border);
    border-radius: 5px;
    font-size: 11px;
    color: var(--color-text-secondary);
    cursor: pointer;
    font-weight: 500;
    transition: background 0.1s ease-out, color 0.1s ease-out;
}

.preset-btn:hover {
    border-color: var(--color-text-dim);
    color: var(--color-text-primary);
}

.preset-btn--active {
    background: var(--color-scarlet);
    border-color: var(--color-scarlet);
    color: white;
}

.preset-btn--passage { color: var(--color-scarlet); font-weight: 600; }
.preset-btn--passage.preset-btn--active { background: var(--color-scarlet); color: white; }
.preset-btn--disabled { opacity: 0.4; cursor: default; }

.preset-btn--reset {
    color: var(--color-scarlet);
    border-color: var(--color-scarlet);
    background: transparent;
}

.refresh-select {
    font-size: 11px;
    border: 1px solid var(--color-border);
    border-radius: 5px;
    padding: 4px 6px;
    background: var(--color-bg);
    color: var(--color-text-secondary);
    cursor: pointer;
}

.custom-picker {
    display: flex;
    gap: 12px;
    align-items: flex-end;
    padding: 12px 16px;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: 10px;
    margin-bottom: 8px;
    flex-wrap: wrap;
}

.custom-picker-label {
    font-size: 11px;
    font-weight: 600;
    color: var(--color-text-dim);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.custom-picker-input {
    font-size: 13px;
    border: 1px solid var(--color-border);
    border-radius: 6px;
    padding: 6px 10px;
    background: var(--color-bg);
    color: var(--color-text-primary);
}

.custom-picker-input:focus { outline: 2px solid var(--color-scarlet); outline-offset: -1px; }

.custom-picker-apply {
    padding: 6px 16px;
    background: var(--color-scarlet);
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
}

.custom-picker-apply:hover { background: var(--color-scarlet-hover); }

.error-banner {
    padding: 8px 16px;
    background: var(--color-amber-bg);
    border: 1px solid var(--color-amber);
    border-radius: 8px;
    font-size: 12px;
    color: var(--color-amber);
    margin-bottom: 8px;
}

.panel {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: 10px;
    padding: 16px;
}

.panel-head {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    margin-bottom: 8px;
}

.panel-title { font-size: 15px; font-weight: 600; }

.chart-legend { display: flex; align-items: baseline; gap: 6px; }

.legend-dot {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}

.legend-dot--sm { width: 6px; height: 6px; }

.chart-stats {
    font-size: 11px;
    color: var(--color-text-dim);
    font-variant-numeric: tabular-nums;
    margin-left: 8px;
}

.chart-stat-current { font-weight: 600; }
.chart-stat-sep { color: var(--color-border); margin: 0 4px; }

.overlay-btn {
    font-size: 11px;
    color: var(--color-scarlet);
    cursor: pointer;
    font-weight: 500;
    background: none;
    border: none;
    padding: 0;
}

.overlay-btn:hover { text-decoration: underline; }

.chart-container {
    position: relative;
    min-height: 280px;
    outline: none;
}

.chart-empty {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 280px;
    color: var(--color-text-dim);
    font-size: 13px;
}

.chart-tooltip {
    position: absolute;
    top: 48px;
    transform: translateX(-50%);
    background: rgba(0, 0, 0, 0.92);
    color: white;
    padding: 8px 12px;
    border-radius: 8px;
    font-size: 11px;
    white-space: nowrap;
    pointer-events: none;
    z-index: 10;
    backdrop-filter: blur(6px);
}

.chart-tooltip-time {
    color: var(--color-text-dim);
    font-size: 10px;
    margin-bottom: 4px;
}

.chart-tooltip-row {
    display: flex;
    align-items: center;
    gap: 6px;
    font-variant-numeric: tabular-nums;
}

.chart-tooltip-val { font-weight: 700; font-size: 14px; }

.chart-tooltip-hint {
    color: var(--color-text-secondary);
    font-size: 9px;
    margin-top: 4px;
}

.overlay-picker { padding: 12px 16px; }

.overlay-picker-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.overlay-picker-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}

.overlay-picker-group {
    width: 100%;
    font-size: 10px;
    font-weight: 700;
    color: var(--color-text-dim);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    padding: 8px 0 4px;
}

.overlay-picker-group:first-child { padding-top: 0; }

.overlay-picker-item {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border: 1px solid var(--color-border);
    border-radius: 5px;
    font-size: 11px;
    color: var(--color-text-secondary);
    background: var(--color-bg);
    cursor: pointer;
}

.overlay-picker-item:hover:not(:disabled) { border-color: var(--color-text-dim); }
.overlay-picker-item--active { background: var(--color-border-light); color: var(--color-text-dim); cursor: default; }
.overlay-picker-item--disabled { opacity: 0.35; cursor: default; }

.overlay-legend {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 10px 16px;
    flex-wrap: wrap;
}

.overlay-legend-item {
    display: flex;
    align-items: center;
    gap: 5px;
    font-variant-numeric: tabular-nums;
}

.overlay-remove {
    color: var(--color-scarlet);
    cursor: pointer;
    font-size: 14px;
    background: none;
    border: none;
    padding: 0 2px;
    line-height: 1;
}

:deep(.u-wrap) { position: relative !important; }
:deep(.u-select) { background: var(--color-scarlet-light) !important; }
:deep(.u-cursor-x) { border-right: 1px dashed var(--color-scarlet) !important; }

@media (max-width: 767px) {
    .toolbar { flex-direction: column; align-items: stretch; }
    .toolbar-right {
        overflow-x: auto;
        flex-wrap: nowrap;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
    }
    .toolbar-right::-webkit-scrollbar { display: none; }
    .custom-picker { flex-direction: column; }
    .overlay-legend { flex-direction: column; align-items: flex-start; gap: 8px; }
}
</style>
