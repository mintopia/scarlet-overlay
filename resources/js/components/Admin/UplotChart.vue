<template>
    <div ref="hostRef" class="uplot-host"></div>
</template>

<script setup>
import { ref, shallowRef, onMounted, onBeforeUnmount, watch, nextTick } from 'vue';
import uPlot from 'uplot';
import 'uplot/dist/uPlot.min.css';

const props = defineProps({
    // series: [{ key, label, unit, axis: 'left'|'right', color, data: [{t, value}] }]
    series: { type: Array, default: () => [] },
    height: { type: Number, default: 260 },
});

const hostRef = ref(null);
const chart = shallowRef(null);
let resizeObserver = null;

function cssVar(name, fallback) {
    const v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    return v || fallback;
}

/** Merge all series onto one shared, sorted timestamp axis. */
function toAlignedData(series) {
    const times = new Set();
    for (const s of series) {
        for (const p of s.data) { times.add(p.t); }
    }
    const xs = [...times].sort((a, b) => a - b);
    const index = new Map(xs.map((t, i) => [t, i]));
    const cols = series.map((s) => {
        const col = new Array(xs.length).fill(null);
        for (const p of s.data) { col[index.get(p.t)] = p.value; }
        return col;
    });
    return [xs, ...cols];
}

function buildOptions() {
    const axisColor = cssVar('--color-text-dim', '#888');
    const gridColor = cssVar('--color-border-light', '#eee');
    const hasRight = props.series.some((s) => s.axis === 'right');
    const palette = [
        cssVar('--color-scarlet', '#c0392b'),
        cssVar('--color-teal', '#1abc9c'),
        cssVar('--color-blue', '#2980b9'),
        cssVar('--color-amber', '#f39c12'),
        cssVar('--color-green', '#27ae60'),
    ];

    const axes = [
        { stroke: axisColor, grid: { stroke: gridColor, width: 1 }, ticks: { stroke: gridColor } },
        { scale: 'left', stroke: axisColor, grid: { stroke: gridColor, width: 1 },
          label: leftLabel(), labelSize: 24 },
    ];
    if (hasRight) {
        axes.push({ scale: 'right', side: 1, stroke: axisColor, grid: { show: false },
            label: rightLabel(), labelSize: 24 });
    }

    const uSeries = [{}];
    props.series.forEach((s, i) => {
        uSeries.push({
            label: `${s.label}${s.unit ? ' (' + s.unit + ')' : ''}`,
            stroke: s.color || palette[i % palette.length],
            width: 2,
            scale: s.axis === 'right' ? 'right' : 'left',
            points: { show: false },
        });
    });

    return {
        width: hostRef.value.clientWidth,
        height: props.height,
        scales: { x: { time: true }, left: {}, ...(hasRight ? { right: {} } : {}) },
        axes,
        series: uSeries,
        legend: { show: true },
        cursor: { drag: { x: true, y: false } },
    };
}

function leftLabel() {
    const left = props.series.filter((s) => s.axis !== 'right');
    return left.length ? (left[0].unit || left[0].label) : '';
}

function rightLabel() {
    const right = props.series.filter((s) => s.axis === 'right');
    return right.length ? (right[0].unit || right[0].label) : '';
}

function render() {
    if (chart.value) { chart.value.destroy(); chart.value = null; }
    if (!hostRef.value || props.series.length === 0) { return; }
    const data = toAlignedData(props.series);
    chart.value = new uPlot(buildOptions(), data, hostRef.value);
}

onMounted(async () => {
    await nextTick();
    render();
    resizeObserver = new ResizeObserver(() => {
        if (chart.value && hostRef.value) {
            chart.value.setSize({ width: hostRef.value.clientWidth, height: props.height });
        }
    });
    resizeObserver.observe(hostRef.value);
});

watch(() => props.series, render, { deep: true });

onBeforeUnmount(() => {
    resizeObserver?.disconnect();
    chart.value?.destroy();
    chart.value = null;
});
</script>

<style scoped>
.uplot-host { width: 100%; }
:deep(.u-legend) { font-size: 12px; }
</style>
