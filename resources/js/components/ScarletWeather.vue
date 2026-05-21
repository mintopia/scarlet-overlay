<script setup>
import { computed } from 'vue';
import { useSpringValue } from '../composables/useSpringValue';

const props = defineProps({
    wxIcon: String,
    wxTemp: String,
    wxCondition: String,
    wxSeaTemp: String,
    wxWindSpeed: String,
    wxWindDir: String,
    wxWaveHeight: String,
    wxWavePeriod: String,
    rawTemp: { type: Number, default: null },
    rawSeaTemp: { type: Number, default: null },
    rawWindSpeed: { type: Number, default: null },
    rawWaveHeight: { type: Number, default: null },
    rawWavePeriod: { type: Number, default: null },
});

const animTemp = useSpringValue(() => props.rawTemp, { tension: 60, friction: 10 });
const animSea = useSpringValue(() => props.rawSeaTemp, { tension: 60, friction: 10 });
const animWind = useSpringValue(() => props.rawWindSpeed, { tension: 80, friction: 12 });
const animWaveH = useSpringValue(() => props.rawWaveHeight, { tension: 60, friction: 10 });
const animWaveP = useSpringValue(() => props.rawWavePeriod, { tension: 60, friction: 10 });

const dispTemp = computed(() => props.rawTemp != null ? `${animTemp.value.toFixed(1)}°` : props.wxTemp);
const dispSea = computed(() => props.rawSeaTemp != null ? `${animSea.value.toFixed(1)}°` : props.wxSeaTemp);
const dispWind = computed(() => props.rawWindSpeed != null ? `${Math.round(animWind.value)} kn` : props.wxWindSpeed);
const dispWaveH = computed(() => props.rawWaveHeight != null ? `${animWaveH.value.toFixed(1)} m` : props.wxWaveHeight);
const dispWaveP = computed(() => props.rawWavePeriod != null ? `${Math.round(animWaveP.value)} s` : props.wxWavePeriod);
</script>

<template>
    <div class="wx-strip">
        <div class="pill pill--hero">
            <span class="wx-icon">{{ wxIcon }}</span>
            <div>
                <div class="pill-val">{{ dispTemp }}</div>
                <div class="pill-sub">{{ wxCondition }}</div>
            </div>
        </div>
        <div class="pill">
            <div class="pill-lbl">SEA</div>
            <div class="pill-val">{{ dispSea }}</div>
        </div>
        <div class="pill">
            <div class="pill-lbl">WIND</div>
            <div class="pill-val">{{ dispWind }}</div>
            <div class="pill-sub">{{ wxWindDir }}</div>
        </div>
        <div class="pill">
            <div class="pill-lbl">WAVES</div>
            <div class="pill-val">{{ dispWaveH }}</div>
            <div class="pill-sub">{{ dispWaveP }}</div>
        </div>
    </div>
</template>

<style scoped>
.wx-strip {
    display: flex;
    gap: 5px;
    align-items: stretch;
}

.pill {
    background: oklch(0.08 0.008 40 / 0.72);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    border-radius: 7px;
    padding: 7px 12px;
    text-align: center;
    border: 1px solid oklch(0.32 0.01 40 / 0.18);
    min-width: 52px;
}

.pill-lbl {
    font-size: 8px;
    font-weight: 600;
    letter-spacing: 0.04em;
    color: oklch(0.62 0.008 70);
    margin-bottom: 2px;
}

.pill-val {
    font-size: 14px;
    font-weight: 500;
    color: oklch(0.96 0.005 70);
    line-height: 1.1;
}

.pill-sub {
    font-size: 9px;
    color: oklch(0.62 0.008 70);
    margin-top: 2px;
}

.pill--hero {
    display: flex;
    gap: 8px;
    align-items: center;
    text-align: left;
    padding: 7px 14px;
}

.wx-icon {
    font-size: 20px;
    line-height: 1;
    flex-shrink: 0;
}

.pill--hero .pill-val {
    font-size: 16px;
    font-weight: 700;
}
</style>
