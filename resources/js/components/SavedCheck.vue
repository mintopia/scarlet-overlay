<template>
    <span class="saved-check">
        <svg class="saved-check-icon" viewBox="0 0 16 16" aria-hidden="true">
            <polyline points="3 8.5 6.5 12 13 4" />
        </svg>
        <span>{{ label }}</span>
    </span>
</template>

<script setup>
defineProps({
    label: { type: String, default: 'Saved' },
});
</script>

<style scoped>
.saved-check {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 13px;
    color: var(--color-green);
    font-weight: 500;
}

.saved-check-icon {
    width: 16px;
    height: 16px;
    fill: none;
    stroke: var(--color-green);
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.saved-check-icon polyline {
    stroke-dasharray: 16;
    stroke-dashoffset: 16;
    animation: draw-check 0.35s cubic-bezier(0.16, 1, 0.3, 1) 0.08s forwards;
}

@keyframes draw-check {
    to { stroke-dashoffset: 0; }
}

@media (prefers-reduced-motion: reduce) {
    .saved-check-icon polyline {
        animation: none;
        stroke-dashoffset: 0;
    }
}
</style>
