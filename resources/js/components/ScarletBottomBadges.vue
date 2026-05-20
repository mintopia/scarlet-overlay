<script setup>
defineProps({
    coordText: String,
    showSpeedLegend: { type: Boolean, default: true },
});
</script>

<template>
    <div class="bottom-badges">
        <div class="coord-badge">{{ coordText }}</div>
        <div v-if="showSpeedLegend" class="speed-legend">
            <span>0 kn</span>
            <div class="legend-gradient"></div>
            <span>10 kn</span>
        </div>
    </div>
</template>

<style scoped>
.bottom-badges {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.coord-badge {
    font-size: 13px;
    font-weight: 500;
    color: oklch(0.96 0.005 70);
    background: oklch(0.08 0.008 40 / 0.72);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    padding: 6px 14px;
    border-radius: 7px;
    border: 1px solid oklch(0.32 0.01 40 / 0.18);
    letter-spacing: 0.02em;
}

.speed-legend {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 10px;
    font-weight: 500;
    color: oklch(0.62 0.008 70);
    background: oklch(0.08 0.008 40 / 0.72);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    padding: 5px 12px;
    border-radius: 7px;
    border: 1px solid oklch(0.32 0.01 40 / 0.18);
    width: fit-content;
}

.legend-gradient {
    width: 52px;
    height: 3px;
    background: linear-gradient(to right,
        oklch(0.52 0.10 260),
        oklch(0.62 0.14 155),
        oklch(0.56 0.20 27)
    );
    border-radius: 2px;
}
</style>
