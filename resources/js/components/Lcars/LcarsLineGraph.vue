<script setup>
import { computed } from 'vue';

const props = defineProps({
    points: { type: Array, default: () => [] },     // [{t(ms), value}]
    lastKnown: { type: Object, default: null },      // {t, value}
    range: { type: Array, default: null },           // [min,max] or null => auto
    color: { type: String, default: 'var(--blue)' },
    nowMs: { type: Number, required: true },
    windowMs: { type: Number, default: 6 * 60 * 60 * 1000 },
    height: { type: Number, default: 120 },
});

const W = 600;
const PAD = 6;

const domain = computed(() => [props.nowMs - props.windowMs, props.nowMs]);

const bounds = computed(() => {
    if (props.range) return props.range;
    const vals = props.points.map(p => p.value).filter(v => v != null);
    if (!vals.length && props.lastKnown) return [props.lastKnown.value - 1, props.lastKnown.value + 1];
    if (!vals.length) return [0, 1];
    let lo = Math.min(...vals), hi = Math.max(...vals);
    if (lo === hi) { lo -= 1; hi += 1; }
    const pad = (hi - lo) * 0.1;
    return [lo - pad, hi + pad];
});

function x(t) {
    const [d0, d1] = domain.value;
    return ((t - d0) / (d1 - d0)) * W;
}
function y(v) {
    const [lo, hi] = bounds.value;
    return PAD + (1 - (v - lo) / (hi - lo || 1)) * (props.height - PAD * 2);
}

const inWindow = computed(() => props.points.filter(p => p.value != null && p.t >= domain.value[0]));

const path = computed(() => {
    const pts = inWindow.value;
    if (pts.length < 2) return null;
    return pts.map((p, i) => `${i ? 'L' : 'M'}${x(p.t).toFixed(1)},${y(p.value).toFixed(1)}`).join(' ');
});

const fillPath = computed(() => {
    if (!path.value) return null;
    const pts = inWindow.value;
    return `${path.value} L${x(pts[pts.length - 1].t).toFixed(1)},${props.height} L${x(pts[0].t).toFixed(1)},${props.height} Z`;
});

// "LAST KNOWN" dashed flat line when nothing falls inside the 6h domain.
const lastKnownY = computed(() => (props.lastKnown ? y(props.lastKnown.value) : null));
const showLastKnown = computed(() => inWindow.value.length < 2 && props.lastKnown != null);

const gid = `lk-${Math.round(props.height)}-${props.color.replace(/[^a-z]/gi, '')}`;
</script>

<template>
    <svg class="lcars-line" :viewBox="`0 0 ${W} ${height}`" preserveAspectRatio="none" :style="{ height: height + 'px', width: '100%' }">
        <defs>
            <linearGradient :id="gid" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" :stop-color="color" stop-opacity="0.28" />
                <stop offset="100%" :stop-color="color" stop-opacity="0" />
            </linearGradient>
        </defs>
        <template v-if="path">
            <path :d="fillPath" :fill="`url(#${gid})`" />
            <path :d="path" fill="none" :stroke="color" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />
        </template>
        <template v-else-if="showLastKnown">
            <line x1="0" :y1="lastKnownY" :x2="W" :y2="lastKnownY" :stroke="color" stroke-width="1.5" stroke-dasharray="6 5" opacity="0.55" vector-effect="non-scaling-stroke" />
            <text :x="W - 6" :y="lastKnownY - 6" text-anchor="end" fill="var(--lilac)" font-size="13" opacity="0.7">LAST KNOWN</text>
        </template>
        <text v-else :x="W / 2" :y="height / 2" text-anchor="middle" fill="var(--lilac)" font-size="14" opacity="0.5">NO DATA</text>
    </svg>
</template>
