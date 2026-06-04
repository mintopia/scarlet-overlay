<script setup>
defineProps({
    base: { type: String, default: 'map' },
    seamark: { type: Boolean, default: true },
    contours: { type: Boolean, default: false },
});

const emit = defineEmits(['update:base', 'update:seamark', 'update:contours']);
</script>

<template>
    <div class="map-layer-control">
        <div class="mlc-base">
            <button :class="['mlc-btn', { active: base === 'map' }]" @click="emit('update:base', 'map')">Map</button>
            <button :class="['mlc-btn', { active: base === 'satellite' }]" @click="emit('update:base', 'satellite')">Satellite</button>
        </div>
        <div class="mlc-divider"></div>
        <div class="mlc-overlays">
            <label class="mlc-toggle">
                <input type="checkbox" :checked="seamark" @change="emit('update:seamark', $event.target.checked)" />
                <span>Sea Charts</span>
            </label>
            <label class="mlc-toggle">
                <input type="checkbox" :checked="contours" @change="emit('update:contours', $event.target.checked)" />
                <span>Depth Contours</span>
            </label>
        </div>
    </div>
</template>

<style scoped>
.map-layer-control {
    position: absolute;
    top: 12px;
    right: 12px;
    z-index: 1000;
    background: var(--color-surface, oklch(0.08 0.008 40 / 0.82));
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border-radius: 10px;
    border: 1px solid var(--color-border, oklch(0.32 0.01 40 / 0.18));
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12);
    font-family: var(--font-body, 'Outfit', system-ui, sans-serif);
}

.mlc-base {
    display: flex;
}

.mlc-btn {
    padding: 7px 16px;
    font-size: 12px;
    font-weight: 600;
    color: var(--color-text-secondary, oklch(0.62 0.008 70));
    border: none;
    background: none;
    cursor: pointer;
    transition: background 0.15s, color 0.15s;
    flex: 1;
    text-align: center;
}

.mlc-btn:hover {
    background: var(--color-border-light, oklch(0.25 0.01 40 / 0.2));
}

.mlc-btn.active {
    background: var(--color-teal, oklch(0.55 0.14 185));
    color: #fff;
}

.mlc-btn + .mlc-btn {
    border-left: 1px solid var(--color-border, oklch(0.32 0.01 40 / 0.18));
}

.mlc-divider {
    height: 1px;
    background: var(--color-border, oklch(0.32 0.01 40 / 0.18));
}

.mlc-overlays {
    padding: 8px 12px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.mlc-toggle {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 11px;
    font-weight: 500;
    color: var(--color-text-secondary, oklch(0.75 0.008 70));
    cursor: pointer;
    user-select: none;
}

.mlc-toggle input[type="checkbox"] {
    width: 14px;
    height: 14px;
    accent-color: var(--color-teal, oklch(0.55 0.14 185));
    cursor: pointer;
    margin: 0;
    flex-shrink: 0;
}

@media (prefers-reduced-motion: reduce) {
    .mlc-btn { transition: none; }
}
</style>
