<template>
    <div
        ref="hostRef"
        class="mini-chart"
        :style="{ height: height + 'px' }"
        role="img"
        :aria-label="ariaSummary"
    >
        <div v-if="showTooltip" ref="tipRef" class="mini-chart__tip" hidden></div>
    </div>
</template>

<script setup>
import { ref, shallowRef, computed, onMounted, onBeforeUnmount, watch, nextTick } from 'vue';
import uPlot from 'uplot';
import 'uplot/dist/uPlot.min.css';
import { resolveColor, cssVar } from '@/lib/uplotTheme.js';
import { buildMiniSeries } from '@/lib/buildMiniSeries.js';

const props = defineProps({
    data: { type: Array, required: true },
    variant: { type: String, default: 'line' }, // line | area | water | bipolar
    color: { type: String, default: 'var(--color-teal)' },
    colorPositive: { type: String, default: 'var(--color-green)' },
    colorNegative: { type: String, default: 'var(--color-scarlet)' },
    zeroValue: { type: Number, default: 0 },
    height: { type: Number, default: 88 },
    showTooltip: { type: Boolean, default: true },
    staleAfterSeconds: { type: Number, default: undefined },
    stepSeconds: { type: Number, default: undefined },
    gapFactor: { type: Number, default: undefined },
    markers: { type: Array, default: () => [] },
    markerColor: { type: String, default: 'var(--color-scarlet)' },
});

const hostRef = ref(null);
const tipRef = ref(null);
const chart = shallowRef(null);
let resizeObserver = null;
let themeObserver = null;

const nowS = () => Date.now() / 1000;

const ariaSummary = computed(() => {
    const vals = (props.data ?? [])
        .map((d) => (typeof d === 'number' ? d : (d?.v ?? d?.value)))
        .filter((v) => v != null);
    if (vals.length === 0) return 'Trend chart. No data.';
    const min = Math.min(...vals), max = Math.max(...vals), last = vals[vals.length - 1];
    return `Trend chart. Min ${min.toFixed(2)}, max ${max.toFixed(2)}, latest ${last.toFixed(2)}.`;
});

/** Apply the per-variant stroke/fill onto the live uPlot series descriptor. */
function styleLiveSeries(s, liveColor) {
    if (props.variant === 'bipolar') {
        // Zero-keyed two-tone gradient: positive colour above zeroValue, negative below.
        const pos = resolveColor(props.colorPositive);
        const neg = resolveColor(props.colorNegative);
        s.stroke = (u) => {
            const yPos = u.valToPos(props.zeroValue, 'y', true);
            const grad = u.ctx.createLinearGradient(0, u.bbox.top, 0, u.bbox.top + u.bbox.height);
            const stop = Math.max(0, Math.min(1, (yPos - u.bbox.top) / u.bbox.height));
            grad.addColorStop(0, pos);
            grad.addColorStop(stop, pos);
            grad.addColorStop(stop, neg);
            grad.addColorStop(1, neg);
            return grad;
        };
        s.fill = (u) => {
            const yPos = u.valToPos(props.zeroValue, 'y', true);
            const grad = u.ctx.createLinearGradient(0, u.bbox.top, 0, u.bbox.top + u.bbox.height);
            const stop = Math.max(0, Math.min(1, (yPos - u.bbox.top) / u.bbox.height));
            grad.addColorStop(0, withAlpha(resolveColor(props.colorPositive), 0.15));
            grad.addColorStop(stop, withAlpha(resolveColor(props.colorPositive), 0.15));
            grad.addColorStop(stop, withAlpha(resolveColor(props.colorNegative), 0.15));
            grad.addColorStop(1, withAlpha(resolveColor(props.colorNegative), 0.15));
            return grad;
        };
    } else {
        s.stroke = liveColor;
        if (props.variant === 'area') s.fill = withAlpha(liveColor, 0.08);
        if (props.variant === 'water') s.fill = withAlpha(liveColor, 0.18);
    }
}

/** Convert a hex/rgb colour to an rgba() with the given alpha (best-effort). */
function withAlpha(color, alpha) {
    if (typeof color !== 'string') return color;
    if (color.startsWith('#')) {
        const h = color.slice(1);
        const f = h.length === 3 ? h.split('').map((c) => c + c).join('') : h;
        const n = parseInt(f, 16);
        return `rgba(${(n >> 16) & 255}, ${(n >> 8) & 255}, ${n & 255}, ${alpha})`;
    }
    return `color-mix(in srgb, ${color} ${Math.round(alpha * 100)}%, transparent)`;
}

/** uPlot plugin: draw event-marker bars + the end dot. */
function marksPlugin(getBuilt, liveColor) {
    return {
        hooks: {
            draw: (u) => {
                const built = getBuilt();
                const ctx = u.ctx;
                // Event-marker bars (height ∝ magnitude, normalised to the tallest).
                const maxMag = Math.max(1, ...built.markers.map((m) => m.magnitude));
                ctx.save();
                ctx.globalAlpha = 0.55;
                ctx.fillStyle = resolveColor(props.markerColor);
                for (const m of built.markers) {
                    const x = u.valToPos(m.t, 'x', true);
                    const h = (m.magnitude / maxMag) * u.bbox.height;
                    ctx.fillRect(x - 1, u.bbox.top + u.bbox.height - h, 2, h);
                }
                ctx.restore();
                // End dot at the last real sample.
                if (built.lastPoint) {
                    const x = u.valToPos(built.lastPoint.t, 'x', true);
                    const y = u.valToPos(built.lastPoint.v, 'y', true);
                    ctx.save();
                    ctx.fillStyle = liveColor;
                    ctx.strokeStyle = cssVar('--color-surface', '#fff');
                    ctx.lineWidth = 1.5;
                    ctx.beginPath();
                    ctx.arc(x, y, 3, 0, Math.PI * 2);
                    ctx.fill();
                    ctx.stroke();
                    ctx.restore();
                }
            },
        },
    };
}

/** uPlot cursor hook: position the value/time tooltip. */
function tooltipSetCursor(u) {
    const tip = tipRef.value;
    if (!tip) return;
    const { idx, left, top } = u.cursor;
    if (idx == null) { tip.hidden = true; return; }
    const t = u.data[0][idx];
    const v = u.data[1][idx] ?? u.data[2][idx];
    if (v == null) { tip.hidden = true; return; }
    const ageMin = Math.round((nowS() - t) / 60);
    tip.hidden = false;
    tip.textContent = `${Number(v).toFixed(1)} · ${ageMin <= 0 ? 'now' : ageMin + 'm ago'}`;
    tip.style.left = `${left}px`;
    tip.style.top = `${top}px`;
}

let built = { data: [[], [], []], gaps: {}, markers: [], lastPoint: null };

function buildOptions(liveColor, staleColor) {
    const live = { label: 'value', width: 2, points: { show: false } };
    styleLiveSeries(live, liveColor);
    const held = {
        label: 'held', stroke: staleColor, width: 1.5,
        dash: [3, 3], points: { show: false },
    };
    return {
        width: hostRef.value.clientWidth || 300,
        height: props.height,
        scales: { x: { time: true }, y: {} },
        axes: [{ show: false }, { show: false }],
        legend: { show: false },
        cursor: { show: props.showTooltip, x: props.showTooltip, y: false,
            points: { show: false } },
        hooks: props.showTooltip ? { setCursor: [tooltipSetCursor] } : {},
        series: [{}, live, held],
        plugins: [marksPlugin(() => built, liveColor)],
    };
}

function render() {
    if (chart.value) { chart.value.destroy(); chart.value = null; }
    if (!hostRef.value || !props.data || props.data.length === 0) return;
    const liveColor = resolveColor(props.color);
    const staleColor = cssVar('--color-amber', '#f39c12');
    built = buildMiniSeries(props.data, props, nowS());
    chart.value = new uPlot(buildOptions(liveColor, staleColor), built.data, hostRef.value);
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
    themeObserver = new MutationObserver(() => render());
    themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
});

watch(() => props.data, render, { deep: true });

onBeforeUnmount(() => {
    resizeObserver?.disconnect();
    themeObserver?.disconnect();
    chart.value?.destroy();
    chart.value = null;
});
</script>

<style scoped>
.mini-chart { position: relative; width: 100%; }
.mini-chart__tip {
    position: absolute;
    transform: translate(-50%, -130%);
    pointer-events: none;
    background: var(--color-surface);
    color: var(--color-text);
    border: 1px solid var(--color-border);
    border-radius: 6px;
    padding: 2px 6px;
    font-size: 11px;
    white-space: nowrap;
    z-index: 5;
}
@media (prefers-reduced-motion: reduce) {
    .mini-chart * { transition: none !important; animation: none !important; }
}
</style>
