<template>
    <AdminLayout :wide="true">
        <Head title="Explore" />
        <div class="flex items-center justify-between mb-6">
            <h1 class="font-sans text-xl font-extrabold tracking-tight">Explore</h1>
            <div class="flex items-center gap-1">
                <button v-for="p in presets" :key="p.value" class="range-btn" :class="{ 'range-btn--active': currentRange === p.value, 'range-btn--passage': p.value === 'passage' }" @click="switchRange(p.value)">{{ p.label }}</button>
            </div>
        </div>

        <div class="chart-grid">
            <div v-for="chart in charts" :key="chart.metric.slug" class="chart-card panel" @click="openMetric(chart.metric.slug)">
                <div class="chart-card-head">
                    <div>
                        <div class="chart-card-title">{{ chart.metric.label }}</div>
                        <div class="chart-card-stats">
                            <span v-if="chart.stats.current != null" class="chart-card-current" :style="{ color: chart.metric.color }">{{ fmtVal(chart.stats.current) }}</span>
                            <span v-if="chart.stats.current != null" class="chart-card-unit">{{ chart.metric.unit }}</span>
                            <span v-else class="chart-card-current" style="color: var(--color-text-dim)">—</span>
                        </div>
                    </div>
                    <div v-if="chart.stats.min != null" class="chart-card-range">
                        {{ fmtVal(chart.stats.min) }} – {{ fmtVal(chart.stats.max) }}
                    </div>
                </div>
                <div :ref="el => chartRefs[chart.metric.slug] = el" class="chart-card-canvas"></div>
            </div>
        </div>

        <div class="all-metrics panel mt-6 p-4">
            <div class="text-[13px] font-semibold mb-3" style="color: var(--color-text-secondary)">All Metrics</div>
            <div class="all-metrics-grid">
                <template v-for="(groupMetrics, group) in metrics" :key="group">
                    <div class="all-metrics-group">{{ groupLabel(group) }}</div>
                    <Link
                        v-for="(m, slug) in groupMetrics"
                        :key="slug"
                        :href="`/admin/explore?metric=${slug}&range=${currentRange}`"
                        class="all-metrics-item"
                    >
                        <span class="all-metrics-dot" :style="{ background: m.color }"></span>
                        {{ m.label }}
                    </Link>
                </template>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { ref, onMounted, onUnmounted, nextTick } from 'vue';
import uPlot from 'uplot';
import 'uplot/dist/uPlot.min.css';

const props = defineProps({
    charts: Array,
    metrics: Object,
    range: String,
    start: Number,
    end: Number,
    step: String,
    passage: Object,
});

const RANGE_KEY = 'scarlet_explore_range';

const currentRange = ref(props.range);
const chartRefs = ref({});
const uPlotInstances = [];

const presets = [
    ...(props.passage?.available ? [{ value: 'passage', label: '⚓ Passage' }] : []),
    { value: '1h', label: '1h' },
    { value: '6h', label: '6h' },
    { value: '24h', label: '24h' },
    { value: '3d', label: '3d' },
    { value: '7d', label: '7d' },
];

const groupLabels = {
    navigation: 'Navigation', wind: 'Wind', power: 'Power',
    cabin: 'Cabin', tanks: 'Tanks', tracker: 'Tracker',
};

function groupLabel(group) { return groupLabels[group] || group; }

function fmtVal(v) {
    if (v == null) return '—';
    if (Math.abs(v) >= 100) return v.toFixed(0);
    if (Math.abs(v) >= 10) return v.toFixed(1);
    return v.toFixed(2);
}

function switchRange(range) {
    localStorage.setItem(RANGE_KEY, range);
    currentRange.value = range;
    router.get('/admin/explore', { range }, { preserveState: false });
}

function openMetric(slug) {
    const range = localStorage.getItem(RANGE_KEY) || currentRange.value;
    router.get(`/admin/explore?metric=${slug}&range=${range}`);
}

function buildSparkline(el, data, color) {
    if (!el || !data?.length) return null;
    const timestamps = data.map(d => d.timestamp);
    const values = data.map(d => d.value);

    return new uPlot({
        width: el.offsetWidth,
        height: 80,
        cursor: { show: false },
        select: { show: false },
        legend: { show: false },
        axes: [
            { show: false },
            { show: false },
        ],
        series: [
            {},
            {
                stroke: color,
                fill: color.replace(')', ' / 0.06)'),
                width: 1.5,
            },
        ],
        scales: {
            x: { time: true },
            y: {
                range: (u, min, max) => {
                    const pad = (max - min) * 0.15 || 1;
                    return [min - pad, max + pad];
                },
            },
        },
    }, [timestamps, values], el);
}

function initCharts() {
    for (const inst of uPlotInstances) inst.destroy();
    uPlotInstances.length = 0;

    for (const chart of props.charts) {
        const el = chartRefs.value[chart.metric.slug];
        const inst = buildSparkline(el, chart.data, chart.metric.color);
        if (inst) uPlotInstances.push(inst);
    }
}

let resizeTimeout;
function handleResize() {
    clearTimeout(resizeTimeout);
    resizeTimeout = setTimeout(() => {
        for (let i = 0; i < uPlotInstances.length; i++) {
            const chart = props.charts[i];
            const el = chartRefs.value[chart?.metric?.slug];
            if (el && uPlotInstances[i]) {
                uPlotInstances[i].setSize({ width: el.offsetWidth, height: 80 });
            }
        }
    }, 150);
}

onMounted(() => {
    localStorage.setItem(RANGE_KEY, props.range);
    nextTick(() => initCharts());
    window.addEventListener('resize', handleResize);
});

onUnmounted(() => {
    for (const inst of uPlotInstances) inst.destroy();
    window.removeEventListener('resize', handleResize);
});
</script>

<style scoped>
.chart-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
}

@media (min-width: 1024px) {
    .chart-grid { grid-template-columns: repeat(4, 1fr); }
}

.chart-card {
    padding: 12px 14px 0;
    cursor: pointer;
    transition: border-color 0.15s ease-out;
}

.chart-card:hover {
    border-color: var(--color-text-dim);
}

.chart-card-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 4px;
}

.chart-card-title {
    font-size: 11px;
    font-weight: 600;
    color: var(--color-text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.chart-card-stats {
    display: flex;
    align-items: baseline;
    gap: 3px;
    margin-top: 2px;
}

.chart-card-current {
    font-size: 20px;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    line-height: 1;
}

.chart-card-unit {
    font-size: 11px;
    color: var(--color-text-dim);
}

.chart-card-range {
    font-size: 10px;
    color: var(--color-text-dim);
    font-variant-numeric: tabular-nums;
    text-align: right;
    white-space: nowrap;
}

.chart-card-canvas {
    margin: 0 -14px;
    overflow: hidden;
}

.range-btn {
    padding: 4px 10px;
    background: var(--color-bg);
    border: 1px solid var(--color-border);
    border-radius: 5px;
    font-size: 11px;
    color: var(--color-text-secondary);
    cursor: pointer;
    font-weight: 500;
    transition: background 0.1s ease-out, color 0.1s ease-out;
}

.range-btn:hover {
    border-color: var(--color-text-dim);
    color: var(--color-text-primary);
}

.range-btn--active {
    background: var(--color-scarlet);
    border-color: var(--color-scarlet);
    color: white;
}

.range-btn--passage { color: var(--color-scarlet); font-weight: 600; }
.range-btn--passage.range-btn--active { background: var(--color-scarlet); color: white; }

.all-metrics-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}

.all-metrics-group {
    width: 100%;
    font-size: 10px;
    font-weight: 700;
    color: var(--color-text-dim);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    padding: 8px 0 4px;
}

.all-metrics-group:first-child { padding-top: 0; }

.all-metrics-item {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border: 1px solid var(--color-border);
    border-radius: 5px;
    font-size: 11px;
    color: var(--color-text-secondary);
    background: var(--color-bg);
    text-decoration: none;
    transition: border-color 0.1s ease-out;
}

.all-metrics-item:hover {
    border-color: var(--color-text-dim);
    color: var(--color-text-primary);
}

.all-metrics-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    flex-shrink: 0;
}

:deep(.u-wrap) { position: relative !important; }
</style>
