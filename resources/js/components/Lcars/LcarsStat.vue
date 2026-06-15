<script setup>
import { computed } from 'vue';
import LcarsReadout from './LcarsReadout.vue';

const props = defineProps({
    label: { type: String, default: '' },
    text: { type: String, default: '--' },     // pre-formatted value
    unit: { type: String, default: '' },
    sub: { type: String, default: '' },          // secondary context line
    pct: { type: Number, default: null },        // 0..100 → draws a thin progress rail
    color: { type: String, default: 'var(--orange)' },
    size: { type: Number, default: 38 },
    lost: { type: Boolean, default: false },
});
const railPct = computed(() => (props.pct == null ? null : Math.max(0, Math.min(100, props.pct))));
</script>

<template>
    <div class="lcars-stat" :class="{ lost }">
        <div class="lb">{{ label }}<span v-if="lost" class="lost-flag"> · LOST</span></div>
        <LcarsReadout :text="text" :unit="unit" :size="size" :color="lost ? 'var(--red)' : color" />
        <div v-if="sub" class="sub">{{ sub }}</div>
        <div v-if="railPct != null" class="rail"><span class="fill" :style="{ width: railPct + '%', background: color }"></span></div>
    </div>
</template>

<style scoped>
.lcars-stat { display: flex; flex-direction: column; gap: 5px; justify-content: center; min-height: 0; }
.lcars-stat .lb { font-size: 14px; color: var(--mauve); }
.lcars-stat .sub { font-size: 13px; color: var(--lilac); }
.lost-flag { color: var(--red); }
.rail { height: 5px; background: var(--panel-2); border-radius: 3px; overflow: hidden; margin-top: 2px; }
.rail .fill { display: block; height: 100%; border-radius: 3px; transition: width 0.5s cubic-bezier(0.22, 1, 0.36, 1); }
@media (prefers-reduced-motion: reduce) { .rail .fill { transition: none; } }
</style>
