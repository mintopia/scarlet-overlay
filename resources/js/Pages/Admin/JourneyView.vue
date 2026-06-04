<template>
    <AdminLayout :breadcrumbs="[{ label: 'Journeys', href: '/admin/journeys' }, { label: props.journey.title }]">
        <Head :title="props.journey.title" />

        <div class="journey-view">
            <div class="journey-header">
                <div class="journey-header-left">
                    <div class="journey-title-row">
                        <h1 class="journey-title">{{ props.journey.title }}</h1>
                        <span class="status-badge" :class="`status-badge--${props.journey.status}`">{{ props.journey.status }}</span>
                    </div>
                    <div class="journey-meta">
                        <span>{{ fmtDate(props.journey.started_at) }}</span>
                        <template v-if="props.journey.ended_at">
                            <span class="meta-sep">→</span>
                            <span>{{ fmtDate(props.journey.ended_at) }}</span>
                        </template>
                        <template v-if="props.journey.distance != null">
                            <span class="meta-dot">·</span>
                            <span>{{ props.journey.distance }} nm</span>
                        </template>
                        <template v-if="props.journey.duration != null">
                            <span class="meta-dot">·</span>
                            <span>{{ fmtDuration(props.journey.duration) }}</span>
                        </template>
                    </div>
                </div>
                <div class="journey-header-actions">
                    <a
                        v-if="props.journey.status !== 'planned'"
                        :href="`/journey/${props.journey.slug}`"
                        target="_blank"
                        class="btn btn--ghost"
                    >View Public</a>
                    <Link :href="route('admin.journeys.edit', props.journey.id)" class="btn btn--primary">Edit</Link>
                </div>
            </div>

            <div class="section">
                <div class="map-container" ref="mapEl">
                    <div v-if="!props.gpsTrack.length" class="map-empty">No track data</div>
                    <MapLayerControl
                        v-model:base="base"
                        v-model:seamark="seamark"
                        v-model:contours="contours"
                    />
                </div>
                <div class="speed-legend">
                    <div class="legend-gradient"></div>
                    <span>0</span><span>7 kn</span>
                </div>
            </div>

            <div v-if="chartKeys.length" class="chart-grid">
                <div
                    v-for="key in chartKeys"
                    :key="key"
                    class="chart-panel"
                    :ref="el => { if (el) chartPanels[key] = el; }"
                >
                    <div class="chart-label">{{ props.chartMeta[key]?.label ?? key }}</div>
                    <div
                        :ref="el => { if (el) chartEls[key] = el; }"
                        class="chart-area"
                    >
                        <div v-if="!props.charts[key]?.length" class="chart-empty">No data</div>
                    </div>
                </div>
            </div>

            <div v-if="props.logRows?.length" class="panel log-panel">
                <div class="log-panel-header">
                    <h2 class="log-panel-title">Ship's Log</h2>
                </div>
                <LogTable :rows="props.logRows" />
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import uPlot from 'uplot';
import 'uplot/dist/uPlot.min.css';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import LogTable from '@/components/LogTable.vue';
import MapLayerControl from '@/components/MapLayerControl.vue';
import { speedToColor, addRouteLayer } from '../../scarlet.js';
import { useMapLayers } from '@/composables/useMapLayers.js';

const props = defineProps({
    journey: Object,
    charts: Object,
    chartMeta: Object,
    gpsTrack: Array,
    logRows: Array,
});

const mapEl = ref(null);
const { base, seamark, contours, attach } = useMapLayers();
let map = null;

const chartKeys = Object.keys(props.charts ?? {});
const chartEls = {};
const chartPanels = {};
const chartInstances = {};

function fmtDuration(seconds) {
    if (!seconds) return '—';
    const d = Math.floor(seconds / 86400);
    const h = Math.floor((seconds % 86400) / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const parts = [];
    if (d) parts.push(`${d}d`);
    if (h) parts.push(`${h}h`);
    if (m) parts.push(`${m}m`);
    return parts.join(' ') || '0m';
}

function fmtDate(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function drawGapLines(color) {
    return (u) => {
        const ctx = u.ctx;
        const ydata = u.data[1];
        const xdata = u.data[0];
        ctx.save();
        ctx.strokeStyle = color;
        ctx.lineWidth = 1;
        ctx.setLineDash([4, 4]);
        ctx.globalAlpha = 0.35;
        ctx.beginPath();
        let lastReal = null;
        for (let i = 0; i < ydata.length; i++) {
            if (ydata[i] != null) {
                if (lastReal != null && i - lastReal > 1) {
                    ctx.moveTo(u.valToPos(xdata[lastReal], 'x', true), u.valToPos(ydata[lastReal], 'y', true));
                    ctx.lineTo(u.valToPos(xdata[i], 'x', true), u.valToPos(ydata[i], 'y', true));
                }
                lastReal = i;
            }
        }
        ctx.stroke();
        ctx.restore();
    };
}

function buildChart(key, el) {
    const data = props.charts[key];
    if (!data?.length || !el) return;

    const meta = props.chartMeta?.[key] ?? {};
    const color = meta.color ?? 'oklch(0.54 0.22 27)';
    const fillColor = color.includes('oklch(')
        ? color.replace(')', ' / 0.08)')
        : color + '14';

    const timestamps = data.map(d => d.timestamp);
    const values = data.map(d => d.value ?? null);

    const opts = {
        width: el.offsetWidth,
        height: 200,
        cursor: { show: false },
        select: { show: false },
        legend: { show: false },
        hooks: {
            draw: [drawGapLines(color)],
        },
        axes: [
            {
                stroke: 'oklch(0.55 0.01 70)',
                grid: { stroke: 'oklch(0.93 0.005 70)', width: 1 },
                ticks: { stroke: 'oklch(0.90 0.005 70)', width: 1 },
                font: '10px system-ui',
            },
            {
                stroke: 'oklch(0.55 0.01 70)',
                grid: { stroke: 'oklch(0.93 0.005 70)', width: 1 },
                ticks: { stroke: 'oklch(0.90 0.005 70)', width: 1 },
                font: '10px system-ui',
                size: 50,
            },
        ],
        series: [
            {},
            {
                label: meta.label ?? key,
                stroke: color,
                fill: fillColor,
                width: 1.5,
                spanGaps: false,
            },
        ],
        scales: {
            x: { time: true },
            y: {
                range: (u, min, max) => {
                    const pad = (max - min) * 0.1 || 1;
                    return [min - pad, max + pad];
                },
            },
        },
    };

    if (chartInstances[key]) {
        chartInstances[key].destroy();
    }

    chartInstances[key] = new uPlot(opts, [timestamps, values], el);
}

function buildMap() {
    if (!mapEl.value) return;

    if (!map) {
        map = L.map(mapEl.value, { zoomControl: true, attributionControl: false }).setView([0, 0], 2);
        attach(map);
    }

    if (!props.gpsTrack?.length) return;

    for (let i = 1; i < props.gpsTrack.length; i++) {
        L.polyline(
            [
                [props.gpsTrack[i - 1][0], props.gpsTrack[i - 1][1]],
                [props.gpsTrack[i][0], props.gpsTrack[i][1]],
            ],
            { color: speedToColor(props.gpsTrack[i][2]), weight: 3, opacity: 0.85 }
        ).addTo(map);
    }

    const bounds = L.latLngBounds(props.gpsTrack.map(p => [p[0], p[1]]));
    map.fitBounds(bounds, { padding: [40, 40] });

    if (props.journey.route_waypoints?.length) {
        addRouteLayer(map, props.journey.route_waypoints);
    }
}

const resizeObservers = [];

function setupChartResize(key, el) {
    const ro = new ResizeObserver(() => {
        if (chartInstances[key] && el.offsetWidth > 0) {
            chartInstances[key].setSize({ width: el.offsetWidth, height: el.offsetHeight || 160 });
        }
    });
    ro.observe(el);
    resizeObservers.push(ro);
}

onMounted(() => {
    buildMap();

    for (const key of chartKeys) {
        const el = chartEls[key];
        if (el) {
            buildChart(key, el);
            setupChartResize(key, el);
        }
    }
});

onUnmounted(() => {
    map?.remove();
    map = null;

    for (const key of Object.keys(chartInstances)) {
        chartInstances[key]?.destroy();
    }

    for (const ro of resizeObservers) {
        ro.disconnect();
    }
});
</script>

<style scoped>
.journey-view {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.journey-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
}

.journey-header-left {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.journey-title-row {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.journey-title {
    font-size: 22px;
    font-weight: 800;
    color: var(--color-text-primary);
    margin: 0;
    letter-spacing: -0.02em;
    line-height: 1.2;
}

.status-badge {
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.03em;
    padding: 3px 8px;
    border-radius: 4px;
    text-transform: capitalize;
}

.status-badge--active {
    color: oklch(0.55 0.15 155);
    background: oklch(0.55 0.15 155 / 0.1);
}

.status-badge--completed {
    color: oklch(0.52 0.15 255);
    background: oklch(0.52 0.15 255 / 0.1);
}

.status-badge--planned {
    color: oklch(0.55 0.01 70);
    background: oklch(0.55 0.01 70 / 0.1);
}

.status-badge--abandoned {
    color: oklch(0.55 0.01 70);
    background: oklch(0.90 0.005 70);
}

.journey-meta {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 13px;
    color: var(--color-text-secondary);
    flex-wrap: wrap;
}

.meta-sep {
    color: var(--color-text-dim);
    font-size: 11px;
}

.meta-dot {
    color: var(--color-text-dim);
}

.journey-header-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
}

.section {
    position: relative;
}

.map-container {
    height: 300px;
    border-radius: 8px;
    overflow: hidden;
    border: 1px solid oklch(0.90 0.005 70);
    position: relative;
}

.map-empty {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    color: var(--color-text-dim);
}

.speed-legend {
    position: absolute;
    bottom: 16px;
    left: 16px;
    z-index: 1000;
    display: flex;
    align-items: center;
    gap: 6px;
    background: var(--color-surface);
    border-radius: 8px;
    padding: 6px 12px;
    font-size: 11px;
    color: var(--color-text-secondary);
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.1);
}

.legend-gradient {
    width: 60px;
    height: 8px;
    border-radius: 4px;
    background: linear-gradient(to right, oklch(0.50 0.14 265), oklch(0.64 0.20 155), oklch(0.54 0.24 27));
}

.chart-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 16px;
}

@media (min-width: 768px) {
    .chart-grid {
        grid-template-columns: 1fr 1fr;
    }
}

.chart-panel {
    border: 1px solid var(--color-border);
    border-radius: 8px;
    padding: 16px;
    background: var(--color-surface);
}

.chart-label {
    font-size: 12px;
    font-weight: 600;
    color: var(--color-text-secondary);
    margin-bottom: 8px;
}

.chart-area {
    min-height: 200px;
    position: relative;
}

.chart-empty {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 200px;
    font-size: 13px;
    color: oklch(0.55 0.01 70);
}

.log-panel {
    overflow: hidden;
}

.log-panel-header {
    padding: 20px 24px 12px;
}

.log-panel-title {
    font-size: 15px;
    font-weight: 600;
    margin: 0;
}

:deep(.u-wrap) {
    position: relative !important;
}
</style>
