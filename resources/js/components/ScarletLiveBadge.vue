<script setup>
defineProps({
    text: { type: String, default: 'LIVE' },
    extension: { type: String, default: '' },
    showExtension: { type: Boolean, default: true },
});
</script>

<template>
    <div class="live-badge">
        <div class="live-scarlet">
            <div class="live-dot"></div>
            <span class="live-label">{{ text }}</span>
        </div>
        <div v-if="showExtension" class="live-ext">
            <span>{{ extension }}</span>
        </div>
    </div>
</template>

<style scoped>
.live-badge {
    display: flex;
    align-items: stretch;
    border-radius: 7px;
    overflow: hidden;
    box-shadow: 0 2px 16px oklch(0.54 0.22 27 / 0.25);
}

.live-scarlet {
    display: flex;
    align-items: center;
    gap: 7px;
    background: oklch(0.54 0.22 27);
    padding: 7px 14px 7px 10px;
}

.live-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: oklch(0.96 0.005 70);
    animation: pulse 2s ease-in-out infinite;
    flex-shrink: 0;
}

@keyframes pulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.45; transform: scale(0.8); }
}

@media (prefers-reduced-motion: reduce) {
    .live-dot { animation: none; }
}

.live-label {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.08em;
    color: oklch(0.96 0.005 70);
}

.live-ext {
    display: flex;
    align-items: center;
    background: oklch(0.08 0.008 40 / 0.72);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    padding: 7px 14px;
    font-size: 10px;
    font-weight: 500;
    color: oklch(0.62 0.008 70);
    letter-spacing: 0.02em;
}
</style>
