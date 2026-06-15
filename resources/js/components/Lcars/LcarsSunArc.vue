<script setup>
import { computed } from 'vue';

const props = defineProps({
    sun: { type: Object, default: null },        // { sunrise, sunset, dawn?, dusk?, ... } ISO/HH:MM strings
    nowMs: { type: Number, required: true },
});

function toMinutes(t) {
    if (!t) return null;
    // accept "HH:MM[:SS]" or ISO; pull local HH:MM
    const m = String(t).match(/(\d{1,2}):(\d{2})/);
    if (!m) { const d = new Date(t); return Number.isNaN(d) ? null : d.getHours() * 60 + d.getMinutes(); }
    return Number(m[1]) * 60 + Number(m[2]);
}

const rise = computed(() => toMinutes(props.sun?.sunrise));
const set = computed(() => toMinutes(props.sun?.sunset));
const nowMin = computed(() => { const d = new Date(props.nowMs); return d.getHours() * 60 + d.getMinutes(); });

const isDay = computed(() => rise.value != null && set.value != null && nowMin.value >= rise.value && nowMin.value <= set.value);

// Sun position along a semicircular arc (0 at sunrise → 1 at sunset).
const sunFrac = computed(() => {
    if (rise.value == null || set.value == null) return null;
    return Math.max(0, Math.min(1, (nowMin.value - rise.value) / ((set.value - rise.value) || 1)));
});

const W = 300, H = 150, cx = 150, cy = 140, r = 120;
function pt(frac) {
    const a = Math.PI * (1 - frac); // left(π) → right(0)
    return [cx + r * Math.cos(a), cy - r * Math.sin(a)];
}
const arcPath = `M${cx - r},${cy} A${r},${r} 0 0 1 ${cx + r},${cy}`;
const sunPos = computed(() => (sunFrac.value == null ? null : pt(sunFrac.value)));

function hhmm(t) { const m = toMinutes(t); return m == null ? '--:--' : `${String(Math.floor(m / 60)).padStart(2, '0')}:${String(m % 60).padStart(2, '0')}`; }
const dayLen = computed(() => {
    if (rise.value == null || set.value == null) return '--';
    const mins = set.value - rise.value;
    return `${Math.floor(mins / 60)}h ${String(mins % 60).padStart(2, '0')}m`;
});
</script>

<template>
    <div class="lcars-sun">
        <svg :viewBox="`0 0 ${W} ${H}`" preserveAspectRatio="xMidYMax meet">
            <line :x1="cx - r - 6" :y1="cy" :x2="cx + r + 6" :y2="cy" stroke="var(--blue)" stroke-width="1" opacity="0.4" stroke-dasharray="3 4" />
            <path :d="arcPath" fill="none" stroke="var(--mauve)" stroke-width="2" opacity="0.5" />
            <template v-if="sunPos">
                <circle :cx="sunPos[0]" :cy="sunPos[1]" r="9" :fill="isDay ? 'var(--amber)' : 'var(--lilac)'" />
                <circle :cx="sunPos[0]" :cy="sunPos[1]" r="15" fill="none" :stroke="isDay ? 'var(--amber)' : 'var(--lilac)'" stroke-width="1.5" opacity="0.5" />
            </template>
            <text :x="cx - r" :y="cy + 18" fill="var(--orange)" font-size="13" text-anchor="middle">{{ hhmm(sun?.sunrise) }}</text>
            <text :x="cx + r" :y="cy + 18" fill="var(--orange)" font-size="13" text-anchor="middle">{{ hhmm(sun?.sunset) }}</text>
        </svg>
        <div class="meta">
            <div><span class="k">RISE</span><span class="v lcars-num">{{ hhmm(sun?.sunrise) }}</span></div>
            <div><span class="k">{{ isDay ? 'DAYLIGHT' : 'NIGHT' }}</span><span class="v lcars-num">{{ dayLen }}</span></div>
            <div><span class="k">SET</span><span class="v lcars-num">{{ hhmm(sun?.sunset) }}</span></div>
        </div>
    </div>
</template>

<style scoped>
.lcars-sun { display: flex; flex-direction: column; height: 100%; justify-content: center; gap: 8px; }
.lcars-sun svg { width: 100%; max-height: 200px; }
.meta { display: flex; justify-content: space-around; border-top: 2px solid var(--panel-2); padding-top: 8px; }
.meta div { display: flex; flex-direction: column; align-items: center; }
.meta .k { font-size: 12px; color: var(--mauve); }
.meta .v { font-size: 20px; color: var(--peach); font-weight: 700; }
</style>
