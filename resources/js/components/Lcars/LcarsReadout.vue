<script setup>
import { computed } from 'vue';

const props = defineProps({
    text: { type: String, default: '--' },     // pre-formatted display string
    unit: { type: String, default: '' },
    size: { type: Number, default: 40 },
    color: { type: String, default: 'var(--peach)' },
    lost: { type: Boolean, default: false },
});

const chars = computed(() => String(props.text).split(''));
function digit(ch) {
    const n = Number(ch);
    return ch.trim() !== '' && !Number.isNaN(n) ? n : null;
}
</script>

<template>
    <div class="lcars-rd" :class="{ lost }" :style="{ fontSize: size + 'px', color }">
        <span v-for="(ch, i) in chars" :key="i" class="ch" :class="{ digit: digit(ch) !== null }">
            <span v-if="digit(ch) !== null" class="reel" :style="{ transform: `translateY(${-digit(ch) * 10}%)` }">
                <span v-for="d in 10" :key="d" class="d">{{ d - 1 }}</span>
            </span>
            <template v-else>{{ ch }}</template>
        </span>
        <span v-if="unit" class="u">{{ unit }}</span>
    </div>
</template>

<style scoped>
.lcars-rd { display: inline-flex; align-items: baseline; font-weight: 700; line-height: 1; font-variant-numeric: tabular-nums; }
.ch { display: inline-block; }
.ch.digit { width: 1ch; height: 1em; overflow: hidden; position: relative; text-align: center; }
.ch.digit .reel { display: flex; flex-direction: column; transition: transform 0.55s cubic-bezier(0.22, 1, 0.36, 1); }
.ch.digit .d { height: 1em; }
.u { font-size: 0.4em; color: var(--blue); margin-left: 0.25em; font-weight: 500; }
.lcars-rd.lost { color: var(--red); opacity: 0.7; }
@media (prefers-reduced-motion: reduce) { .ch.digit .reel { transition: none; } }
</style>
