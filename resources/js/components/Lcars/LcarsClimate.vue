<script setup>
import { computed } from 'vue';

const props = defineProps({
    label: { type: String, default: '' },
    temp: { type: Number, default: null },
    humidity: { type: Number, default: null },
    tempRange: { type: Array, default: () => [0, 35] },
});

const tempPct = computed(() => {
    if (props.temp == null) return 0;
    const [lo, hi] = props.tempRange;
    return Math.max(0, Math.min(100, ((props.temp - lo) / (hi - lo || 1)) * 100));
});
const tempColor = computed(() => {
    if (props.temp == null) return 'var(--lilac)';
    if (props.temp < 10) return 'var(--ice)';
    if (props.temp > 26) return 'var(--orange)';
    return 'var(--amber)';
});
const rh = computed(() => (props.humidity == null ? 0 : Math.max(0, Math.min(100, props.humidity))));
</script>

<template>
    <div class="lcars-climate">
        <div class="hd">{{ label }}</div>
        <div class="big lcars-num" :style="{ color: tempColor }">{{ temp == null ? '--' : temp.toFixed(1) }}<small>°C</small></div>
        <div class="rail temp"><span class="fill" :style="{ width: tempPct + '%', background: tempColor }"></span></div>
        <div class="rh">
            <span class="k">RH</span>
            <div class="rail"><span class="fill" :style="{ width: rh + '%' }"></span></div>
            <span class="v lcars-num">{{ humidity == null ? '--' : Math.round(humidity) }}%</span>
        </div>
    </div>
</template>

<style scoped>
.lcars-climate { display: flex; flex-direction: column; gap: 6px; justify-content: center; }
.hd { font-size: 14px; color: var(--mauve); }
.big { font-size: 34px; font-weight: 700; line-height: 1; }
.big small { font-size: 15px; color: var(--blue); margin-left: 2px; }
.rail { height: 6px; background: var(--panel-2); border-radius: 3px; overflow: hidden; }
.rail .fill { display: block; height: 100%; background: var(--blue); transition: width 0.5s cubic-bezier(0.22, 1, 0.36, 1); }
.rh { display: grid; grid-template-columns: auto 1fr auto; align-items: center; gap: 8px; }
.rh .k { font-size: 12px; color: var(--mauve); }
.rh .v { font-size: 15px; color: var(--peach); font-weight: 600; }
@media (prefers-reduced-motion: reduce) { .rail .fill { transition: none; } }
</style>
