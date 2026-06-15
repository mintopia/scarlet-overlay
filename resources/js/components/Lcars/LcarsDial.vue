<script setup>
import { computed, toRef } from 'vue';
import { useSpringValue } from '../../composables/useSpringValue.js';

const props = defineProps({
    value: { type: Number, default: null },
    range: { type: Array, default: () => [0, 100] },
    unit: { type: String, default: '' },
    label: { type: String, default: '' },
    dp: { type: Number, default: 1 },
    compass: { type: Boolean, default: false },  // 0..360 wrap, needle points to bearing
    color: { type: String, default: 'var(--orange)' },
    lost: { type: Boolean, default: false },
});

toRef(props, 'value');
const spring = useSpringValue(() => {
    if (props.value == null) return null;
    if (props.compass) return props.value;
    const [lo, hi] = props.range;
    const frac = Math.max(0, Math.min(1, (props.value - lo) / (hi - lo || 1)));
    return -135 + frac * 270;
}, { tension: 90, friction: 14 });

const angle = computed(() => (spring.value == null ? (props.compass ? 0 : -135) : spring.value));
const display = computed(() => (props.value == null ? '--' : props.value.toFixed(props.dp)));

const ticks = computed(() => {
    const out = [];
    const count = props.compass ? 12 : 9;
    for (let i = 0; i < count; i++) {
        out.push(props.compass ? (i / count) * 360 : -135 + (i / (count - 1)) * 270);
    }
    return out;
});
function pol(a, r) {
    const rad = (a - 90) * Math.PI / 180;
    return [50 + r * Math.cos(rad), 50 + r * Math.sin(rad)];
}
const cardinals = [['N', 0], ['E', 90], ['S', 180], ['W', 270]];
</script>

<template>
    <div class="lcars-dial" :class="{ lost }">
        <div class="gauge">
            <svg viewBox="0 0 100 100">
                <circle cx="50" cy="50" r="47" fill="none" stroke="var(--panel-2)" stroke-width="5" />
                <line v-for="(a, i) in ticks" :key="i"
                    :x1="pol(a, 41)[0]" :y1="pol(a, 41)[1]" :x2="pol(a, 47)[0]" :y2="pol(a, 47)[1]"
                    stroke="var(--mauve)" :stroke-width="props.compass && i === 0 ? 2 : 1.4" :opacity="props.compass && i === 0 ? 0.9 : 0.55" />
                <template v-if="compass">
                    <text v-for="[c, a] in cardinals" :key="c" :x="pol(a, 35)[0]" :y="pol(a, 35)[1] + 3"
                        text-anchor="middle" font-size="8" :fill="c === 'N' ? 'var(--orange)' : 'var(--mauve)'">{{ c }}</text>
                </template>
                <line v-if="value != null" x1="50" y1="50" :x2="pol(angle, 39)[0]" :y2="pol(angle, 39)[1]"
                    :stroke="lost ? 'var(--red)' : color" stroke-width="3.5" stroke-linecap="round" />
                <circle cx="50" cy="50" r="4.5" :fill="lost ? 'var(--red)' : color" />
            </svg>
            <div class="centre">
                <span class="v lcars-num">{{ display }}</span><span v-if="unit" class="u">{{ unit }}</span>
            </div>
        </div>
        <div class="lb">{{ label }}<span v-if="lost" class="lost-flag"> · LOST</span></div>
    </div>
</template>

<style scoped>
.lcars-dial { display: flex; flex-direction: column; align-items: center; min-height: 0; height: 100%; }
.gauge { position: relative; flex: 1 1 auto; min-height: 0; aspect-ratio: 1 / 1; max-height: 100%; display: flex; }
.gauge svg { width: 100%; height: 100%; }
.centre { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; pointer-events: none; }
.centre .v { font-size: 26px; font-weight: 700; color: var(--peach); line-height: 1; }
.centre .u { font-size: 12px; color: var(--blue); }
.lcars-dial .lb { font-size: 13px; color: var(--mauve); margin-top: 4px; flex: 0 0 auto; }
.lcars-dial.lost .centre .v { color: var(--red); }
.lcars-dial.lost { opacity: 0.6; }
.lost-flag { color: var(--red); }
</style>
