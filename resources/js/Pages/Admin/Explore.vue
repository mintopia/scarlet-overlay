<template>
    <AdminLayout :breadcrumbs="[{ label: 'Explore', href: '/admin/explore' }, { label: metric.label }]">
        <Head :title="metric.label" />

        <div class="detail-page">
            <!-- Toolbar -->
            <div class="toolbar">
                <div class="toolbar-left">
                    <h1 class="toolbar-title">{{ metric.label }}</h1>
                    <span class="toolbar-unit">{{ metric.unit }}</span>
                </div>
                <div class="toolbar-right">
                    <div class="range-seg">
                        <button v-for="p in allPresets" :key="p" class="range-btn" :class="{ 'range-btn--active': range === p && !zoomed }" @click="switchRange(p)">{{ p }}</button>
                    </div>

                    <select v-if="journeys.length" v-model="selectedJourney" class="journey-select" @change="applyJourney">
                        <option value="">Journey...</option>
                        <option v-for="j in journeys" :key="j.id" :value="j.id">
                            {{ j.from_port }} → {{ j.to_port }}
                        </option>
                    </select>

                    <div v-if="refreshInterval > 0" class="refresh-indicator">
                        <span class="refresh-dot"></span>
                        <span class="refresh-label">{{ refreshInterval }}s</span>
                    </div>

                    <button v-if="zoomed" class="reset-zoom-btn" @click="resetZoom">Reset zoom</button>
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

            <!-- Error banner -->
            <div v-if="fetchError" class="error-banner">
                Metrics unavailable — retrying in {{ retryCountdown }}s
            </div>

            <!-- Chart panel -->
            <div class="chart-panel">
                <div class="chart-head">
                    <div class="chart-head-left">
                        <!-- Compass rose for compass type -->
                        <div v-if="metric.type === 'compass' && stats.current != null" class="compass-rose">
                            <svg width="40" height="40" viewBox="0 0 40 40">
                                <circle cx="20" cy="20" r="18.5" fill="oklch(0.99 0.01 178 / 0.3)" stroke="var(--color-border)" stroke-width="1.5"/>
                                <text x="20" y="7" text-anchor="middle" font-size="7" font-weight="800" fill="var(--color-scarlet)">N</text>
                                <text x="20" y="37" text-anchor="middle" font-size="6" font-weight="600" fill="var(--color-text-dim)">S</text>
                                <text x="4" y="22" text-anchor="middle" font-size="6" font-weight="600" fill="var(--color-text-dim)">W</text>
                                <text x="36" y="22" text-anchor="middle" font-size="6" font-weight="600" fill="var(--color-text-dim)">E</text>
                                <line
                                    x1="20" y1="20"
                                    :x2="20 + 13 * Math.sin(stats.current * Math.PI / 180)"
                                    :y2="20 - 13 * Math.cos(stats.current * Math.PI / 180)"
                                    :stroke="metric.color"
                                    stroke-width="2"
                                    stroke-linecap="round"
                                />
                            </svg>
                        </div>

                        <!-- Gauge bar for gauge type -->
                        <div v-if="metric.type === 'gauge' && stats.current != null" class="gauge-bar">
                            <div class="gauge-bar-track">
                                <div class="gauge-bar-fill" :style="{ width: Math.min(100, Math.max(0, stats.current)) + '%', background: metric.color }"></div>
                            </div>
                        </div>

                        <div class="chart-stats">
                            <span class="stat-current" :style="{ color: currentValueColor }">{{ fmtCurrentValue }}</span>
                            <span class="stat-unit">{{ currentUnitDisplay }}</span>
                            <template v-if="stats.current != null">
                                <span class="stat-sep">|</span>
                                <span class="stat-range">{{ fmtStatRange }}</span>
                                <span class="stat-sep">&middot;</span>
                                <span class="stat-range">avg {{ fmtVal(stats.avg, metric.type) }}</span>
                            </template>
                        </div>
                    </div>
                    <div class="chart-actions">
                        <span v-if="!metric.related?.length && activeOverlays.length < maxOverlays" class="overlay-link" @click="showOverlayPicker = !showOverlayPicker">+ Overlay</span>
                    </div>
                </div>

                <div ref="chartEl" class="chart-container" tabindex="0">
                    <div v-if="!chartData?.length" class="chart-empty">No data for this time range</div>
                </div>

                <!-- Tooltip -->
                <div v-if="tooltipData" class="chart-tooltip" :style="{ left: tooltipData.x + 'px' }">
                    <div class="chart-tooltip-time">{{ formatTooltipTime(tooltipData.ts) }}</div>
                    <div v-for="entry in tooltipData.entries" :key="entry.label" class="chart-tooltip-row">
                        <span class="legend-dot" :style="{ background: entry.color }"></span>
                        <span class="chart-tooltip-val">{{ entry.value }} {{ entry.unit }}</span>
                    </div>
                    <div class="chart-tooltip-hint">Drag to zoom &middot; Double-click to reset</div>
                </div>

                <!-- Propulsion band legend -->
                <div v-if="hasPropulsion && propulsionStats" class="band-legend">
                    <span class="band-legend-item">
                        <span class="band-swatch band-swatch--sailing"></span>
                        Sailing {{ propulsionStats.sailingPct }}%
                    </span>
                    <span class="band-legend-item">
                        <span class="band-swatch band-swatch--motoring"></span>
                        Motoring {{ propulsionStats.motoringPct }}%
                    </span>
                </div>

                <!-- Stats bar -->
                <div class="stats-bar">
                    <template v-if="metric.type === 'inverted'">
                        <div class="stat-item">
                            <span class="stat-item-label">Shallowest</span>
                            <span class="stat-item-value">{{ fmtVal(stats.min, metric.type) }} <span class="stat-item-unit">{{ metric.unit }}</span></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-item-label">Deepest</span>
                            <span class="stat-item-value">{{ fmtVal(stats.max, metric.type) }} <span class="stat-item-unit">{{ metric.unit }}</span></span>
                        </div>
                    </template>
                    <template v-else-if="metric.type === 'signed'">
                        <div class="stat-item">
                            <span class="stat-item-label" style="color: var(--color-green)">Peak +</span>
                            <span class="stat-item-value" style="color: var(--color-green)">{{ fmtVal(stats.max, 'signed') }} <span class="stat-item-unit">{{ metric.unit }}</span></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-item-label" style="color: var(--color-amber)">Peak −</span>
                            <span class="stat-item-value" style="color: var(--color-amber)">{{ fmtVal(stats.min, 'signed') }} <span class="stat-item-unit">{{ metric.unit }}</span></span>
                        </div>
                    </template>
                    <template v-else-if="metric.type === 'compass'">
                        <div class="stat-item">
                            <span class="stat-item-label">Current</span>
                            <span class="stat-item-value" :style="{ color: metric.color }">{{ fmtVal(stats.current, 'compass') }} <span class="stat-item-unit">{{ bearingToCardinal(stats.current) }}</span></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-item-label">Range</span>
                            <span class="stat-item-value">{{ fmtVal(stats.min, 'compass') }} – {{ fmtVal(stats.max, 'compass') }}</span>
                        </div>
                    </template>
                    <template v-else>
                        <div class="stat-item">
                            <span class="stat-item-label">Min</span>
                            <span class="stat-item-value">{{ fmtVal(stats.min, metric.type) }} <span class="stat-item-unit">{{ metric.unit }}</span></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-item-label">Max</span>
                            <span class="stat-item-value">{{ fmtVal(stats.max, metric.type) }} <span class="stat-item-unit">{{ metric.unit }}</span></span>
                        </div>
                    </template>
                    <div class="stat-item">
                        <span class="stat-item-label">Average</span>
                        <span class="stat-item-value">{{ fmtVal(stats.avg, metric.type) }} <span class="stat-item-unit">{{ metric.type !== 'compass' ? metric.unit : bearingToCardinal(stats.avg) }}</span></span>
                    </div>

                    <!-- Gauge: rate + time-to-target -->
                    <template v-if="metric.type === 'gauge' && gaugeRate != null">
                        <div class="stat-item stat-item--sep">
                            <span class="stat-item-label" :style="{ color: gaugeRate < 0 ? 'var(--color-scarlet)' : 'var(--color-green)' }">{{ gaugeRate < 0 ? 'Time to Empty' : 'Time to Full' }}</span>
                            <span class="stat-item-value" :style="{ color: gaugeRate < 0 ? 'var(--color-scarlet)' : 'var(--color-green)' }">{{ fmtTimeEstimate(gaugeTimeToTarget) }}</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-item-label">Rate</span>
                            <span class="stat-item-value">{{ gaugeRate > 0 ? '+' : '' }}{{ gaugeRate.toFixed(1) }} <span class="stat-item-unit">%/h</span></span>
                        </div>
                    </template>

                    <!-- Propulsion: sailing/motoring averages -->
                    <template v-if="propulsionStats">
                        <div class="stat-item stat-item--sep">
                            <span class="stat-item-label propulsion-sailing">Sailing avg</span>
                            <span class="stat-item-value propulsion-sailing">{{ fmtVal(propulsionStats.sailingAvg) }} <span class="stat-item-unit">{{ metric.unit }}</span></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-item-label propulsion-motoring">Motoring avg</span>
                            <span class="stat-item-value propulsion-motoring">{{ fmtVal(propulsionStats.motoringAvg) }} <span class="stat-item-unit">{{ metric.unit }}</span></span>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Overlay picker (legacy, shown when no related metrics) -->
            <div v-if="showOverlayPicker && !metric.related?.length" class="overlay-picker panel mt-2">
                <div class="overlay-picker-head">
                    <span class="text-[13px] font-semibold">Add Overlay</span>
                    <button class="text-[11px]" style="color: var(--color-text-dim)" @click="showOverlayPicker = false">&times; Close</button>
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
                            <span class="legend-dot" :style="{ background: m.color }"></span>
                            {{ m.label }}
                        </button>
                    </template>
                </div>
            </div>

            <!-- Related metrics chip bar -->
            <div v-if="metric.related?.length" class="related-panel">
                <span class="related-title">Related</span>
                <div class="related-grid">
                    <span class="related-chip related-chip--active">
                        <span class="chip-dot" :style="{ background: metric.color }"></span>
                        {{ metric.label }}
                    </span>
                    <button
                        v-for="slug in metric.related"
                        :key="slug"
                        class="related-chip"
                        :class="{ 'related-chip--active': activeOverlays.includes(slug) }"
                        @click="toggleOverlay(slug)"
                    >
                        <span class="chip-dot" :style="{ background: findMetricColor(slug) }"></span>
                        {{ findMetricLabel(slug) }}
                    </button>
                </div>
            </div>

            <!-- Active overlays legend (when using overlay picker) -->
            <div v-if="overlayData.length && !metric.related?.length" class="overlay-legend panel mt-2">
                <span class="overlay-legend-label">Overlays</span>
                <div v-for="ov in overlayData" :key="ov.metric.slug" class="overlay-legend-item">
                    <span class="legend-dot" :style="{ background: ov.metric.color }"></span>
                    <span class="overlay-legend-name">{{ ov.metric.label }}</span>
                    <button class="overlay-remove" @click="removeOverlay(ov.metric.slug)">&times;</button>
                </div>
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
import { fmtVal, bearingToCardinal, fmtDuration, computeRate, computeTimeToTarget, fmtTimeEstimate } from '@/composables/useFormatters.js';

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
    journeys: { type: Array, default: () => [] },
    propulsionData: { type: Array, default: () => [] },
});

function cssVar(name) {
    return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
}

const allPresets = ['1h', '6h', '24h', '3d', '7d', '30d'];

const zoomed = ref(false);
const showCustomPicker = ref(false);
const customStart = ref('');
const customEnd = ref('');
const refreshInterval = ref(props.refresh);
const selectedJourney = ref('');
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

const fetchError = ref(false);
const retryCountdown = ref(0);
let countdownTimer = null;

const isSigned = computed(() => props.metric.type === 'signed');
const isCompass = computed(() => props.metric.type === 'compass');
const isInverted = computed(() => props.metric.type === 'inverted');
const isGauge = computed(() => props.metric.type === 'gauge');
const isDuration = computed(() => props.metric.type === 'duration');

const currentValueColor = computed(() => {
    if (isSigned.value && stats.value.current != null) {
        return stats.value.current >= 0 ? cssVar('--color-green') : cssVar('--color-amber');
    }
    return props.metric.color;
});

const fmtCurrentValue = computed(() => fmtVal(stats.value.current, props.metric.type));

const currentUnitDisplay = computed(() => {
    if (isCompass.value) return bearingToCardinal(stats.value.current);
    if (isSigned.value && stats.value.current != null) {
        return stats.value.current >= 0 ? `${props.metric.unit} charging` : `${props.metric.unit} discharging`;
    }
    return props.metric.unit;
});

const fmtStatRange = computed(() => {
    if (isCompass.value) return `${fmtVal(stats.value.min, 'compass')} – ${fmtVal(stats.value.max, 'compass')}`;
    return `${fmtVal(stats.value.min, props.metric.type)} – ${fmtVal(stats.value.max, props.metric.type)} ${props.metric.unit}`;
});

const gaugeRate = computed(() => computeRate(chartData.value));
const gaugeTimeToTarget = computed(() => {
    if (gaugeRate.value == null || stats.value.current == null) return null;
    const target = gaugeRate.value < 0 ? 0 : 100;
    return computeTimeToTarget(stats.value.current, gaugeRate.value, target);
});

const propulsionBands = computed(() => {
    if (!props.propulsionData?.length) return [];
    const bands = [];
    let currentMode = null;
    let bandStart = null;

    for (const point of props.propulsionData) {
        if (point.value == null) continue;
        const mode = point.value > 0 ? 'motoring' : 'sailing';
        if (mode !== currentMode) {
            if (currentMode && bandStart != null) {
                bands.push({ mode: currentMode, start: bandStart, end: point.timestamp });
            }
            currentMode = mode;
            bandStart = point.timestamp;
        }
    }
    if (currentMode && bandStart != null) {
        bands.push({ mode: currentMode, start: bandStart, end: props.propulsionData[props.propulsionData.length - 1].timestamp });
    }
    return bands;
});

const hasPropulsion = computed(() => propulsionBands.value.length > 0);

const propulsionStats = computed(() => {
    if (!hasPropulsion.value || !chartData.value?.length) return null;
    const bands = propulsionBands.value;
    let sailSum = 0, sailN = 0, motorSum = 0, motorN = 0;
    let bi = 0;

    for (const point of chartData.value) {
        if (point.value == null) continue;
        while (bi < bands.length - 1 && point.timestamp > bands[bi].end) bi++;
        const band = bands[bi];
        if (band && point.timestamp >= band.start && point.timestamp <= band.end) {
            if (band.mode === 'sailing') { sailSum += point.value; sailN++; }
            else { motorSum += point.value; motorN++; }
        }
    }

    const total = sailN + motorN;
    if (!total) return null;
    return {
        sailingAvg: sailN > 0 ? sailSum / sailN : null,
        motoringAvg: motorN > 0 ? motorSum / motorN : null,
        sailingPct: Math.round(sailN / total * 100),
        motoringPct: Math.round(motorN / total * 100),
    };
});

const groupLabels = {
    navigation: 'Navigation', wind: 'Wind', power: 'Power',
    cabin: 'Cabin', environment: 'Environment', tanks: 'Tanks', tracker: 'Tracker',
};

function groupLabel(group) { return groupLabels[group] || group; }

function findMetricLabel(slug) {
    for (const group of Object.values(props.metrics)) {
        if (group[slug]) return group[slug].label;
    }
    return slug;
}

function findMetricColor(slug) {
    for (const group of Object.values(props.metrics)) {
        if (group[slug]) return group[slug].color;
    }
    return 'var(--color-text-dim)';
}

function buildUrl(params) {
    const query = new URLSearchParams();
    for (const [k, v] of Object.entries(params)) {
        if (v != null && v !== '') query.set(k, v);
    }
    return `/admin/explore?${query.toString()}`;
}

function switchRange(range) {
    zoomed.value = false;
    showCustomPicker.value = false;
    localStorage.setItem('scarlet_explore_range', range);
    router.get(buildUrl({ metric: props.metric.slug, range }));
}

function applyJourney() {
    if (!selectedJourney.value) return;
    const journey = props.journeys.find(j => j.id === Number(selectedJourney.value));
    if (!journey) return;
    const start = Math.floor(new Date(journey.started_at).getTime() / 1000);
    const end = journey.ended_at ? Math.floor(new Date(journey.ended_at).getTime() / 1000) : Math.floor(Date.now() / 1000);
    router.get(buildUrl({ metric: props.metric.slug, range: 'custom', start, end }));
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

function formatTimestamp(ts) {
    const d = new Date(ts * 1000);
    const rangeDuration = props.end - props.start;
    if (rangeDuration <= 86400) return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    if (rangeDuration <= 604800) return d.toLocaleDateString([], { day: 'numeric', month: 'short' }) + ' ' + d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    return d.toLocaleDateString([], { day: 'numeric', month: 'short' });
}

function formatTooltipTime(ts) {
    const d = new Date(ts * 1000);
    return d.toLocaleDateString([], { day: 'numeric', month: 'short', year: 'numeric' }) + ', ' + d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
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

    if (isSigned.value) {
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

function drawSignedPath(u, seriesIdx) {
    const xdata = u.data[0];
    const ydata = u.data[seriesIdx];
    const stroke = new Path2D();
    const fill = new Path2D();
    const zeroY = u.valToPos(0, 'y', true);
    let started = false;
    let lastX;

    for (let i = 0; i < xdata.length; i++) {
        const val = ydata[i];
        if (val == null) {
            if (started) { fill.lineTo(lastX, zeroY); fill.closePath(); started = false; }
            continue;
        }
        const x = u.valToPos(xdata[i], 'x', true);
        const y = u.valToPos(val, 'y', true);
        if (!started) {
            stroke.moveTo(x, y); fill.moveTo(x, zeroY); fill.lineTo(x, y); started = true;
        } else {
            stroke.lineTo(x, y); fill.lineTo(x, y);
        }
        lastX = x;
    }
    if (started) { fill.lineTo(lastX, zeroY); fill.closePath(); }

    return { stroke, fill, clip: null, band: null, gaps: null, flags: 0 };
}

function buildChartOpts(width) {
    const fillColor = props.metric.color.replace(')', ' / 0.08)');
    const chartHeight = window.innerWidth < 768 ? 280 : 400;

    const yAxisValues = (u, vals) => {
        if (isCompass.value) {
            const cardinals = ['N','E','S','W'];
            return vals.map(v => {
                const c = cardinals[Math.round(v / 90) % 4] || '';
                return `${Math.round(v)}° ${c}`;
            });
        }
        if (isDuration.value) return vals.map(v => fmtDuration(v));
        if (isSigned.value) return vals.map(v => v > 0 ? `+${fmtVal(v)}` : fmtVal(v));
        if (isGauge.value) return vals.map(v => `${Math.round(v)}%`);
        return vals.map(v => fmtVal(v));
    };

    let scales = { x: { time: true } };
    if (isCompass.value) {
        scales.y = { range: [0, 360] };
    } else if (isInverted.value) {
        scales.y = {
            range: (u, min, max) => {
                const pad = (max - min) * 0.1 || 1;
                return [max + pad, Math.max(0, min - pad)];
            },
        };
    } else if (isGauge.value) {
        scales.y = { range: [0, 100] };
    } else if (isSigned.value) {
        scales.y = {
            range: (u, min, max) => {
                const absMax = Math.max(Math.abs(min), Math.abs(max)) * 1.15 || 50;
                return [-absMax, absMax];
            },
        };
    } else {
        scales.y = {
            range: (u, min, max) => {
                const pad = (max - min) * 0.1 || 1;
                return [min - pad, max + pad];
            },
        };
    }

    const hooks = {
        setSelect: [
            (u) => {
                const min = u.posToVal(u.select.left, 'x');
                const max = u.posToVal(u.select.left + u.select.width, 'x');
                if (max - min < 60) return;
                zoomed.value = true;
                router.get(buildUrl({ metric: props.metric.slug, range: 'custom', start: Math.floor(min), end: Math.floor(max) }), {}, { preserveState: false });
            },
        ],
        setCursor: [
            (u) => {
                const idx = u.cursor.idx;
                if (idx == null) { tooltipData.value = null; return; }
                const ts = u.data[0][idx];
                const entries = [];
                for (let i = 1; i < u.data.length; i++) {
                    const val = u.data[i][idx];
                    if (val != null) {
                        entries.push({
                            label: u.series[i].label,
                            value: fmtVal(val, u.series[i]._type || props.metric.type),
                            color: typeof u.series[i].stroke === 'function' ? props.metric.color : u.series[i].stroke,
                            unit: u.series[i]._unit || '',
                        });
                    }
                }
                tooltipData.value = { ts, x: u.cursor.left, entries };
            },
        ],
        drawAxes: [],
        draw: [],
    };

    if (hasPropulsion.value) {
        hooks.drawAxes.push((u) => {
            const ctx = u.ctx;
            ctx.save();
            for (const band of propulsionBands.value) {
                const x0 = Math.max(u.bbox.left, u.valToPos(band.start, 'x'));
                const x1 = Math.min(u.bbox.left + u.bbox.width, u.valToPos(band.end, 'x'));
                if (x1 <= x0) continue;
                ctx.fillStyle = band.mode === 'sailing' ? 'rgba(30, 165, 155, 0.07)' : 'rgba(205, 155, 35, 0.07)';
                ctx.fillRect(x0, u.bbox.top, x1 - x0, u.bbox.height);
            }
            ctx.restore();
        });
    }

    if (isSigned.value) {
        hooks.draw.push((u) => {
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
        });
    }

    let series;
    if (isSigned.value) {
        series = [
            {},
            {
                label: 'Charging',
                stroke: cssVar('--color-green'),
                fill: cssVar('--color-green') + '14',
                width: 1.5,
                _unit: props.metric.unit,
                _type: 'signed',
                paths: drawSignedPath,
            },
            {
                label: 'Discharging',
                stroke: cssVar('--color-amber'),
                fill: cssVar('--color-amber') + '14',
                width: 1.5,
                _unit: props.metric.unit,
                _type: 'signed',
                paths: drawSignedPath,
            },
        ];
    } else {
        series = [
            {},
            {
                label: props.metric.label,
                stroke: props.metric.color,
                fill: fillColor,
                width: 1.5,
                _unit: props.metric.unit,
                _type: props.metric.type,
            },
        ];
    }

    if (overlayData.value.length) {
        for (const ov of overlayData.value) {
            series.push({
                label: ov.metric.label,
                stroke: ov.metric.color,
                width: 1.5,
                _unit: ov.metric.unit,
                _type: ov.metric.type || 'standard',
                scale: ov.metric.unit === props.metric.unit ? 'y' : 'y2',
            });
        }
    }

    const opts = {
        width,
        height: chartHeight,
        cursor: { drag: { x: true, y: false, setScale: false } },
        select: { show: true, over: true },
        hooks,
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
                size: 55,
                values: yAxisValues,
            },
        ],
        series,
        scales,
    };

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
    if (activeOverlays.value.includes(slug)) { removeOverlay(slug); return; }
    if (activeOverlays.value.length >= maxOverlays) return;

    let metricConfig = null;
    for (const group of Object.values(props.metrics)) {
        if (group[slug]) { metricConfig = { ...group[slug], slug }; break; }
    }
    if (!metricConfig) return;

    try {
        const res = await fetch(`/admin/explore/series?metric=${slug}&start=${props.start}&end=${props.end}&step=${props.step}`);
        if (!res.ok) return;
        const json = await res.json();
        activeOverlays.value.push(slug);
        overlayData.value.push({ metric: metricConfig, data: json[0].data });
        rebuildChart();
    } catch { /* silent */ }
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
        countdownTimer = setInterval(() => { retryCountdown.value = Math.max(0, retryCountdown.value - 1); }, 1000);
        refreshTimer = setInterval(() => { doRefresh(); retryCountdown.value = refreshInterval.value; }, refreshInterval.value * 1000);
    }
}

async function doRefresh() {
    if (zoomed.value) return;
    const end = Math.floor(Date.now() / 1000);
    const duration = props.end - props.start;
    const start = end - duration;
    const slugs = [props.metric.slug, ...activeOverlays.value];

    try {
        const res = await fetch(`/admin/explore/series?metrics=${slugs.join(',')}&start=${start}&end=${end}&step=${props.step}`);
        if (!res.ok) return;
        const json = await res.json();

        const primary = json.find(s => s.metric === props.metric.slug);
        if (primary) { chartData.value = primary.data; computeStats(); }

        for (const ov of overlayData.value) {
            const updated = json.find(s => s.metric === ov.metric.slug);
            if (updated) ov.data = updated.data;
        }

        if (chart) chart.setData(prepareData());
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
});

onUnmounted(() => {
    if (chart) chart.destroy();
    clearInterval(refreshTimer);
    clearInterval(countdownTimer);
    window.removeEventListener('resize', handleResize);
});
</script>

<style scoped>
.detail-page { max-width: 800px; }

.toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 12px;
}
.toolbar-left { display: flex; align-items: baseline; gap: 8px; }
.toolbar-title { font-size: 16px; font-weight: 800; color: var(--color-text-primary); margin: 0; line-height: 1; letter-spacing: -0.02em; }
.toolbar-unit { font-size: 11px; color: var(--color-text-dim); font-weight: 500; }
.toolbar-right { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }

.range-seg {
    display: flex;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: 7px;
    overflow: hidden;
}
.range-btn {
    padding: 5px 10px;
    font-size: 10px;
    font-weight: 600;
    color: var(--color-text-dim);
    border: none;
    background: none;
    cursor: pointer;
    border-right: 1px solid var(--color-border-light);
    transition: background 0.1s, color 0.1s;
}
.range-btn:last-child { border-right: none; }
.range-btn:hover { color: var(--color-text-primary); }
.range-btn--active { background: var(--color-scarlet); color: white; }

.journey-select {
    font-size: 10px;
    font-weight: 600;
    color: var(--color-teal);
    border: 1px solid var(--color-border);
    border-radius: 7px;
    padding: 5px 8px;
    background: var(--color-surface);
    cursor: pointer;
}

.refresh-indicator { display: flex; align-items: center; gap: 3px; margin-left: 4px; }
.refresh-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--color-green); }
.refresh-label { font-size: 9px; color: var(--color-text-dim); }

.reset-zoom-btn {
    font-size: 10px;
    font-weight: 600;
    color: var(--color-scarlet);
    border: 1px solid var(--color-scarlet);
    border-radius: 7px;
    padding: 5px 10px;
    background: transparent;
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
.custom-picker-label { font-size: 11px; font-weight: 600; color: var(--color-text-dim); text-transform: uppercase; letter-spacing: 0.04em; display: flex; flex-direction: column; gap: 4px; }
.custom-picker-input { font-size: 13px; border: 1px solid var(--color-border); border-radius: 6px; padding: 6px 10px; background: var(--color-bg); color: var(--color-text-primary); }
.custom-picker-input:focus { outline: 2px solid var(--color-scarlet); outline-offset: -1px; }
.custom-picker-apply { padding: 6px 16px; background: var(--color-scarlet); color: white; border: none; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; }

.error-banner {
    padding: 8px 16px;
    background: var(--color-amber-bg);
    border: 1px solid var(--color-amber);
    border-radius: 8px;
    font-size: 12px;
    color: var(--color-amber);
    margin-bottom: 8px;
}

.chart-panel {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: 12px;
    overflow: hidden;
}

.chart-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 14px 18px 0;
}
.chart-head-left { display: flex; align-items: center; gap: 12px; }

.compass-rose { flex-shrink: 0; }
.gauge-bar { width: 80px; flex-shrink: 0; }
.gauge-bar-track { height: 10px; background: var(--color-border-light); border-radius: 5px; overflow: hidden; }
.gauge-bar-fill { height: 100%; border-radius: 5px; transition: width 0.3s; }

.chart-stats { display: flex; align-items: baseline; gap: 4px; }
.stat-current { font-family: 'Nunito Sans', system-ui; font-size: 28px; font-weight: 700; letter-spacing: -1px; font-variant-numeric: tabular-nums; }
.stat-unit { font-size: 12px; color: var(--color-text-dim); font-weight: 500; margin-left: 2px; }
.stat-sep { color: var(--color-border); margin: 0 6px; font-size: 11px; }
.stat-range { font-size: 11px; color: var(--color-text-dim); font-variant-numeric: tabular-nums; }

.chart-actions { display: flex; align-items: center; gap: 8px; }
.overlay-link { font-size: 11px; color: var(--color-scarlet); cursor: pointer; font-weight: 600; }
.overlay-link:hover { text-decoration: underline; }

.chart-container { position: relative; min-height: 280px; outline: none; padding: 8px 18px 18px; }
.chart-empty { display: flex; align-items: center; justify-content: center; height: 280px; color: var(--color-text-dim); font-size: 13px; }

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
.chart-tooltip-time { color: var(--color-text-dim); font-size: 10px; margin-bottom: 4px; }
.chart-tooltip-row { display: flex; align-items: center; gap: 6px; font-variant-numeric: tabular-nums; }
.chart-tooltip-val { font-weight: 700; font-size: 14px; }
.chart-tooltip-hint { color: rgba(255,255,255,0.5); font-size: 9px; margin-top: 4px; }

.legend-dot { display: inline-block; width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }

/* Stats bar */
.stats-bar {
    display: flex;
    gap: 12px;
    padding: 12px 18px;
    border-top: 1px solid var(--color-border-light);
    flex-wrap: wrap;
}
.stat-item { display: flex; flex-direction: column; gap: 1px; }
.stat-item--sep { border-left: 1px solid var(--color-border-light); padding-left: 12px; }
.stat-item-label { font-size: 9px; font-weight: 700; color: var(--color-text-dim); text-transform: uppercase; letter-spacing: 0.05em; }
.stat-item-value { font-size: 14px; font-weight: 700; color: var(--color-text-primary); font-family: 'Nunito Sans', system-ui; font-variant-numeric: tabular-nums; }
.stat-item-unit { font-size: 10px; color: var(--color-text-dim); font-weight: 500; }

/* Propulsion bands */
.band-legend {
    display: flex;
    gap: 14px;
    padding: 6px 18px;
    border-top: 1px solid var(--color-border-light);
    font-size: 10px;
    color: var(--color-text-dim);
}
.band-legend-item { display: flex; align-items: center; gap: 5px; }
.band-swatch { width: 12px; height: 8px; border-radius: 2px; }
.band-swatch--sailing { background: rgba(30, 165, 155, 0.25); }
.band-swatch--motoring { background: rgba(205, 155, 35, 0.25); }
.propulsion-sailing { color: oklch(0.55 0.14 178); }
.propulsion-motoring { color: oklch(0.65 0.15 65); }

/* Related panel */
.related-panel {
    display: flex;
    align-items: center;
    gap: 10px;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: 12px;
    padding: 12px 18px;
    margin-top: 10px;
    flex-wrap: wrap;
}
.related-title { font-size: 10px; font-weight: 800; color: var(--color-text-dim); text-transform: uppercase; letter-spacing: 0.06em; }
.related-grid { display: flex; gap: 6px; flex-wrap: wrap; }
.related-chip {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 5px 10px;
    background: var(--color-bg);
    border: 1px solid var(--color-border);
    border-radius: 6px;
    font-size: 11px;
    color: var(--color-text-secondary);
    cursor: pointer;
    font-weight: 500;
}
.related-chip:hover { border-color: var(--color-text-dim); }
.related-chip--active { background: var(--color-teal-bg, oklch(0.42 0.14 178 / 0.08)); border-color: var(--color-teal, oklch(0.42 0.14 178)); color: var(--color-teal, oklch(0.42 0.14 178)); font-weight: 600; cursor: default; }
.chip-dot { width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }

/* Overlay picker */
.panel { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: 10px; padding: 16px; }
.overlay-picker { padding: 12px 16px; }
.overlay-picker-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
.overlay-picker-grid { display: flex; flex-wrap: wrap; gap: 4px; }
.overlay-picker-group { width: 100%; font-size: 10px; font-weight: 700; color: var(--color-text-dim); text-transform: uppercase; letter-spacing: 0.04em; padding: 8px 0 4px; }
.overlay-picker-group:first-child { padding-top: 0; }
.overlay-picker-item { display: flex; align-items: center; gap: 5px; padding: 4px 10px; border: 1px solid var(--color-border); border-radius: 5px; font-size: 11px; color: var(--color-text-secondary); background: var(--color-bg); cursor: pointer; }
.overlay-picker-item:hover:not(:disabled) { border-color: var(--color-text-dim); }
.overlay-picker-item--active { background: var(--color-border-light); color: var(--color-text-dim); cursor: default; }
.overlay-picker-item--disabled { opacity: 0.35; cursor: default; }

.overlay-legend { display: flex; align-items: center; gap: 16px; padding: 10px 16px; flex-wrap: wrap; }
.overlay-legend-label { font-size: 12px; font-weight: 600; color: var(--color-text-secondary); }
.overlay-legend-item { display: flex; align-items: center; gap: 5px; font-variant-numeric: tabular-nums; }
.overlay-legend-name { font-size: 11px; font-weight: 500; }
.overlay-remove { color: var(--color-scarlet); cursor: pointer; font-size: 14px; background: none; border: none; padding: 0 2px; line-height: 1; }

:deep(.u-wrap) { position: relative !important; }
:deep(.u-select) { background: oklch(0.48 0.22 25 / 0.1) !important; }
:deep(.u-cursor-x) { border-right: 1px dashed var(--color-scarlet) !important; }

@media (max-width: 767px) {
    .toolbar { flex-direction: column; align-items: stretch; }
    .toolbar-right { overflow-x: auto; flex-wrap: nowrap; -webkit-overflow-scrolling: touch; scrollbar-width: none; }
    .toolbar-right::-webkit-scrollbar { display: none; }
    .custom-picker { flex-direction: column; }
    .stats-bar { gap: 8px; }
    .related-panel { flex-direction: column; align-items: flex-start; }
}
</style>
