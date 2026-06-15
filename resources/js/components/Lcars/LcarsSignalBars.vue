<script setup>
import { computed } from 'vue';

const props = defineProps({
    value: { type: Number, default: null },     // dBm (negative)
    range: { type: Array, default: () => [-110, -50] },
    lost: { type: Boolean, default: false },
});

// dBm → 0..5 bars
const bars = computed(() => {
    if (props.value == null) return 0;
    const [lo, hi] = props.range;
    const frac = Math.max(0, Math.min(1, (props.value - lo) / (hi - lo || 1)));
    return Math.round(frac * 5);
});
const quality = computed(() => ['NO SIGNAL', 'POOR', 'FAIR', 'GOOD', 'STRONG', 'EXCELLENT'][bars.value]);
const color = computed(() => (bars.value <= 1 ? 'var(--red)' : bars.value <= 2 ? 'var(--amber)' : 'var(--orange)'));
</script>

<template>
    <div class="lcars-sig" :class="{ lost }">
        <div class="bars">
            <span v-for="i in 5" :key="i" class="bar" :class="{ on: i <= bars }"
                :style="[{ height: 28 + i * 12 + '%' }, i <= bars ? { background: color } : {}]"></span>
        </div>
        <div class="meta">
            <span class="q" :style="{ color }">{{ quality }}</span>
            <span class="v lcars-num">{{ value == null ? '--' : value }}<small> dBm</small></span>
        </div>
    </div>
</template>

<style scoped>
.lcars-sig { display: flex; flex-direction: column; gap: 6px; justify-content: center; }
.bars { display: flex; align-items: flex-end; gap: 5px; height: 56px; }
.bars .bar { flex: 1; background: var(--panel-2); border-radius: 2px; transition: background 0.3s; }
.meta { display: flex; justify-content: space-between; align-items: baseline; }
.meta .q { font-size: 15px; font-weight: 600; }
.meta .v { font-size: 18px; color: var(--peach); font-weight: 700; }
.meta .v small { font-size: 12px; color: var(--blue); }
.lcars-sig.lost { opacity: 0.55; }
.lost-flag { color: var(--red); }
</style>
