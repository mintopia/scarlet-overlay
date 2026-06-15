<script setup>
import { computed, toRef } from 'vue';
import { useSpringValue } from '../../composables/useSpringValue.js';

const props = defineProps({
    value: { type: Number, default: null },
    range: { type: Array, default: () => [0, 100] },
    unit: { type: String, default: '%' },
    dp: { type: Number, default: 0 },
    color: { type: String, default: 'var(--orange)' },
    lost: { type: Boolean, default: false },
});

toRef(props, 'value');
const spring = useSpringValue(() => {
    if (props.value == null) return null;
    const [lo, hi] = props.range;
    return Math.max(0, Math.min(100, ((props.value - lo) / (hi - lo || 1)) * 100));
}, { tension: 110, friction: 16 });

const pct = computed(() => spring.value ?? 0);
const display = computed(() => (props.value == null ? '--' : props.value.toFixed(props.dp)));
// LCARS segmented bar: 20 cells lit proportionally.
const cells = computed(() => Array.from({ length: 20 }, (_, i) => (i + 1) * 5 <= pct.value));
</script>

<template>
    <div class="lcars-barm" :class="{ lost }">
        <div class="top"><span class="v lcars-num">{{ display }}<small>{{ unit }}</small></span></div>
        <div class="cells">
            <span v-for="(on, i) in cells" :key="i" class="cell" :class="{ on }" :style="on ? { background: color } : {}"></span>
        </div>
    </div>
</template>

<style scoped>
.lcars-barm { display: flex; flex-direction: column; gap: 6px; justify-content: center; }
.lcars-barm .top { display: flex; justify-content: flex-start; align-items: baseline; }
.lcars-barm .v { font-size: 30px; font-weight: 700; color: var(--peach); }
.lcars-barm .v small { font-size: 13px; color: var(--blue); margin-left: 2px; }
.lcars-barm .cells { display: flex; gap: 3px; height: 22px; }
.lcars-barm .cell { flex: 1; background: var(--panel-2); border-radius: 1px; transition: background 0.3s cubic-bezier(0.25, 1, 0.5, 1); }
.lcars-barm.lost { opacity: 0.55; }
.lcars-barm.lost .v { color: var(--red); }
</style>
