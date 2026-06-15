<script setup>
import { computed } from 'vue';
import { useSpringValue } from '../../composables/useSpringValue.js';

/*
 * LCARS bearing gauge. Pure radial-typographic readout — a swept arc + a rim marker,
 * NOT a center-pivot needle (needles are cockpit/HUD grammar, not LCARS). For a true
 * compass the value wraps 0..360; the arc sweeps clockwise from N to the bearing.
 */
const props = defineProps({
    value: { type: Number, default: null },
    range: { type: Array, default: () => [0, 360] },
    unit: { type: String, default: '°' },
    dp: { type: Number, default: 0 },
    compass: { type: Boolean, default: true },
    color: { type: String, default: 'var(--orange)' },
    lost: { type: Boolean, default: false },
});

const spring = useSpringValue(() => {
    if (props.value == null) return null;
    if (props.compass) return props.value;
    const [lo, hi] = props.range;
    return Math.max(0, Math.min(1, (props.value - lo) / (hi - lo || 1))) * 360;
}, { tension: 90, friction: 15 });

const bearing = computed(() => spring.value ?? 0);
const display = computed(() => (props.value == null ? '--' : props.value.toFixed(props.dp)));

const R = 40;
function pol(a, r) {
    const rad = (a - 90) * Math.PI / 180;
    return [50 + r * Math.cos(rad), 50 + r * Math.sin(rad)];
}
const arc = computed(() => {
    const t = Math.max(0.001, Math.min(359.999, bearing.value));
    const [sx, sy] = pol(0, R);
    const [ex, ey] = pol(t, R);
    return `M${sx.toFixed(2)},${sy.toFixed(2)} A${R},${R} 0 ${t > 180 ? 1 : 0} 1 ${ex.toFixed(2)},${ey.toFixed(2)}`;
});
const marker = computed(() => {
    const tip = pol(bearing.value, R + 3);
    const a = pol(bearing.value, R - 5);
    const l = pol(bearing.value - 5, R - 1);
    const r = pol(bearing.value + 5, R - 1);
    return { tip, a, pts: `${l[0]},${l[1]} ${tip[0]},${tip[1]} ${r[0]},${r[1]}` };
});

const ticks = computed(() => Array.from({ length: 12 }, (_, i) => i * 30));
const cardinals = [['N', 0], ['E', 90], ['S', 180], ['W', 270]];
</script>

<template>
    <div class="lcars-arc" :class="{ lost }">
        <div class="gauge">
            <svg viewBox="0 0 100 100">
                <line v-for="(a, i) in ticks" :key="i"
                    :x1="pol(a, 44)[0]" :y1="pol(a, 44)[1]" :x2="pol(a, 48)[0]" :y2="pol(a, 48)[1]"
                    stroke="var(--mauve)" :stroke-width="a % 90 === 0 ? 2 : 1.2" :opacity="a % 90 === 0 ? 0.85 : 0.5" />
                <template v-if="compass">
                    <text v-for="[c, a] in cardinals" :key="c" :x="pol(a, 33)[0]" :y="pol(a, 33)[1] + 3"
                        text-anchor="middle" font-size="9" :fill="c === 'N' ? 'var(--orange)' : 'var(--mauve)'">{{ c }}</text>
                </template>
                <!-- swept arc from origin to bearing -->
                <path v-if="value != null" :d="arc" fill="none" :stroke="lost ? 'var(--red)' : color"
                    stroke-width="4" stroke-linecap="round" opacity="0.9" />
                <!-- rim marker (no center pivot) -->
                <polygon v-if="value != null" :points="marker.pts" :fill="lost ? 'var(--red)' : color" />
            </svg>
            <div class="centre">
                <span class="v lcars-num">{{ display }}</span><span v-if="unit" class="u">{{ unit }}</span>
            </div>
        </div>
    </div>
</template>

<style scoped>
.lcars-arc { display: flex; flex-direction: column; align-items: center; min-height: 0; height: 100%; }
.gauge { position: relative; flex: 1 1 auto; min-height: 0; aspect-ratio: 1 / 1; max-height: 100%; display: flex; }
.gauge svg { width: 100%; height: 100%; }
.centre { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; pointer-events: none; }
.centre .v { font-size: 28px; font-weight: 700; color: var(--peach); line-height: 1; }
.centre .u { font-size: 13px; color: var(--blue); }
.lcars-arc.lost .centre .v { color: var(--red); }
.lcars-arc.lost { opacity: 0.6; }
</style>
