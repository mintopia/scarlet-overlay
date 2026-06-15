<script setup>
import { computed } from 'vue';
import LcarsLineGraph from './LcarsLineGraph.vue';
import { fmtMetric, fmtDuration } from '../../lcars/format.js';

const props = defineProps({
    metric: { type: Object, required: true },
    value: { type: [Number, String], default: null },
    points: { type: Array, default: () => [] },
    lastKnown: { type: Object, default: null },
    nowMs: { type: Number, required: true },
    color: { type: String, default: 'var(--orange)' },
    lost: { type: Boolean, default: false },
});
const emit = defineEmits(['close']);

const vals = computed(() => (props.points || []).map(p => p.value).filter(v => v != null));
const stats = computed(() => {
    const a = vals.value;
    if (!a.length) return null;
    return { min: Math.min(...a), max: Math.max(...a), avg: a.reduce((s, v) => s + v, 0) / a.length };
});
const cur = computed(() => fmtMetric(props.metric, props.value));
const isHistorical = computed(() => props.metric?.cls === 'historical');
const trend = computed(() => {
    const a = vals.value;
    if (a.length < 2) return '—';
    const d = a[a.length - 1] - a[0];
    return d > 0.01 ? '▲ RISING' : d < -0.01 ? '▼ FALLING' : '▬ STEADY';
});
function fmtStat(v) { return fmtMetric(props.metric, v).text; }

// The analysis view zooms its x-axis to the data it actually holds (vs the console
// tiles' fixed 6h window), so sparse history still reads as a full graph.
const SIX_H = 6 * 3600 * 1000;
const windowMs = computed(() => {
    const pts = props.points || [];
    if (pts.length < 2) return SIX_H;
    const span = props.nowMs - pts[0].t;
    return Math.min(SIX_H, Math.max(span * 1.06, 30 * 1000));
});
const windowLabel = computed(() => (windowMs.value >= SIX_H * 0.92 ? 'LAST 6H' : `LAST ${fmtDuration(windowMs.value / 1000)}`));
</script>

<template>
    <div class="lcars-detail">
        <button class="back" @click="emit('close')" aria-label="Return to console">◄ RETURN TO CONSOLE</button>
        <h2><span class="dot"></span>{{ metric.label }}</h2>
        <div class="big lcars-num" :class="{ lost }">{{ cur.text }}<small>{{ cur.unit }}</small></div>

        <template v-if="isHistorical">
            <div class="stats">
                <div><span class="k">MIN</span><span class="v lcars-num">{{ stats ? fmtStat(stats.min) : '--' }}</span></div>
                <div><span class="k">MAX</span><span class="v lcars-num">{{ stats ? fmtStat(stats.max) : '--' }}</span></div>
                <div><span class="k">AVG</span><span class="v lcars-num">{{ stats ? fmtStat(stats.avg) : '--' }}</span></div>
                <div><span class="k">TREND</span><span class="v sm">{{ trend }}</span></div>
                <div><span class="k">WINDOW</span><span class="v sm">{{ windowLabel }}</span></div>
            </div>
            <div class="graph">
                <LcarsLineGraph :points="points" :last-known="lastKnown" :range="metric.range"
                    :color="color" :now-ms="nowMs" :window-ms="windowMs" :height="340" />
            </div>
        </template>
        <div v-else class="instant">
            <p class="hd">LIVE INSTANTANEOUS READING</p>
            <p class="sub">This sensor reports a current value only; no trend history is stored.</p>
        </div>
    </div>
</template>

<style scoped>
.lcars-detail { position: fixed; inset: var(--gap); z-index: 60; background: var(--panel); border-radius: 6px 30px 30px 6px; padding: 26px 36px; display: flex; flex-direction: column; gap: 16px; }
.back { align-self: flex-start; background: var(--red); border: none; color: #000; font-family: inherit; font-weight: 700; padding: 9px 22px; border-radius: 20px; cursor: pointer; font-size: 15px; letter-spacing: 0.04em; }
.back:hover { background: var(--gold); }
.back:focus-visible { outline: 3px solid var(--ice); outline-offset: 2px; }
h2 { color: var(--orange); font-size: 34px; font-weight: 700; display: flex; align-items: center; gap: 14px; }
h2 .dot { width: 18px; height: 18px; border-radius: 50%; background: var(--orange); }
.big { font-size: clamp(64px, 11vh, 110px); font-weight: 700; color: var(--peach); line-height: 0.9; }
.big small { font-size: 0.28em; color: var(--blue); margin-left: 0.15em; }
.big.lost { color: var(--red); }
.stats { display: flex; gap: 48px; flex-wrap: wrap; }
.stats div { display: flex; flex-direction: column; gap: 2px; }
.stats .k { font-size: 14px; color: var(--mauve); }
.stats .v { font-size: 30px; font-weight: 700; color: var(--peach); }
.stats .v.sm { font-size: 22px; color: var(--ice); }
.graph { flex: 1; min-height: 0; display: flex; align-items: stretch; }
.graph :deep(svg) { height: 100% !important; }
.instant { margin-top: 20px; }
.instant .hd { color: var(--ice); font-size: 22px; font-weight: 600; }
.instant .sub { color: var(--mauve); font-size: 15px; margin-top: 8px; text-transform: none; max-width: 50ch; }
</style>
