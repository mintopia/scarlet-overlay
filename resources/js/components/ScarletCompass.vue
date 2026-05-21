<script setup>
import { computed } from 'vue';
import { useAngleSpring } from '../composables/useSpringValue';

const props = defineProps({
    heading: { type: Number, default: 0 },
    windDirection: { type: Number, default: null },
    size: { type: Number, default: 80 },
});

const animHeading = useAngleSpring(() => props.heading);
const animWind = useAngleSpring(() => props.windDirection ?? 0);

const roseRotation = computed(() => `rotate(${-animHeading.value}, 80, 80)`);
const windRotation = computed(() => `rotate(${(animWind.value ?? 0) - animHeading.value}, 80, 80)`);
const showWind = computed(() => props.windDirection != null);
const headingDisplay = computed(() => Math.round(((animHeading.value % 360) + 360) % 360));
</script>

<template>
    <div class="compass" :style="{ width: size + 'px', height: size + 'px' }">
        <svg viewBox="0 0 160 160" class="compass-svg">
            <circle cx="80" cy="80" r="74" fill="none" stroke="oklch(0.28 0.01 40)" stroke-width="1"/>
            <circle cx="80" cy="80" r="70" fill="oklch(0.08 0.008 40 / 0.72)"/>

            <g :transform="roseRotation">
                <line x1="80" y1="12" x2="80" y2="22" stroke="oklch(0.96 0.005 70)" stroke-width="2"/>
                <line x1="148" y1="80" x2="138" y2="80" stroke="oklch(0.72 0.008 70)" stroke-width="1.5"/>
                <line x1="80" y1="148" x2="80" y2="138" stroke="oklch(0.72 0.008 70)" stroke-width="1.5"/>
                <line x1="12" y1="80" x2="22" y2="80" stroke="oklch(0.72 0.008 70)" stroke-width="1.5"/>

                <line x1="114" y1="21" x2="109" y2="29" stroke="oklch(0.38 0.008 70)" stroke-width="1"/>
                <line x1="139" y1="46" x2="131" y2="51" stroke="oklch(0.38 0.008 70)" stroke-width="1"/>
                <line x1="139" y1="114" x2="131" y2="109" stroke="oklch(0.38 0.008 70)" stroke-width="1"/>
                <line x1="114" y1="139" x2="109" y2="131" stroke="oklch(0.38 0.008 70)" stroke-width="1"/>
                <line x1="46" y1="139" x2="51" y2="131" stroke="oklch(0.38 0.008 70)" stroke-width="1"/>
                <line x1="21" y1="114" x2="29" y2="109" stroke="oklch(0.38 0.008 70)" stroke-width="1"/>
                <line x1="21" y1="46" x2="29" y2="51" stroke="oklch(0.38 0.008 70)" stroke-width="1"/>
                <line x1="46" y1="21" x2="51" y2="29" stroke="oklch(0.38 0.008 70)" stroke-width="1"/>

                <text x="80" y="36" text-anchor="middle" font-size="11" font-weight="700" font-family="Outfit, system-ui, sans-serif" fill="oklch(0.54 0.22 27)">N</text>
                <text x="131" y="84" text-anchor="middle" font-size="9" font-weight="600" font-family="Outfit, system-ui, sans-serif" fill="oklch(0.55 0.008 70)">E</text>
                <text x="80" y="132" text-anchor="middle" font-size="9" font-weight="600" font-family="Outfit, system-ui, sans-serif" fill="oklch(0.55 0.008 70)">S</text>
                <text x="29" y="84" text-anchor="middle" font-size="9" font-weight="600" font-family="Outfit, system-ui, sans-serif" fill="oklch(0.55 0.008 70)">W</text>
            </g>

            <g v-if="showWind" :transform="windRotation">
                <line x1="80" y1="46" x2="80" y2="62" stroke="oklch(0.65 0.12 200)" stroke-width="2" stroke-linecap="round"/>
                <polygon points="80,42 76,50 84,50" fill="oklch(0.65 0.12 200)"/>
            </g>

            <polygon points="80,44 76,54 84,54" fill="oklch(0.54 0.22 27)"/>

            <circle cx="80" cy="80" r="2.5" fill="oklch(0.96 0.005 70)"/>
        </svg>
        <div class="compass-heading">{{ headingDisplay }}°</div>
    </div>
</template>

<style scoped>
.compass {
    position: relative;
    flex-shrink: 0;
}

.compass-svg {
    width: 100%;
    height: 100%;
    filter: drop-shadow(0 2px 8px oklch(0.05 0.01 40 / 0.4));
}

.compass-heading {
    position: absolute;
    bottom: -2px;
    left: 50%;
    transform: translateX(-50%);
    font-size: 10px;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
    color: oklch(0.96 0.005 70);
    background: oklch(0.08 0.008 40 / 0.72);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    padding: 1px 6px;
    border-radius: 4px;
    border: 1px solid oklch(0.28 0.01 40 / 0.3);
    white-space: nowrap;
}
</style>
