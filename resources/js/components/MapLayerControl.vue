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
    background: var(--color-surface);
    border-radius: 10px;
    border: 1px solid var(--color-border);
    overflow: hidden;
    box-shadow: var(--shadow-sm);
    font-family: var(--font-body);
}

.mlc-base {
    display: flex;
}

.mlc-btn {
    padding: 7px 16px;
    font-size: 12px;
    font-weight: 600;
    color: var(--color-text-secondary);
    border: none;
    background: none;
    cursor: pointer;
    transition: background 0.15s, color 0.15s;
    flex: 1;
    text-align: center;
}

.mlc-btn:hover {
    background: var(--color-border-light);
}

.mlc-btn.active {
    background: var(--color-teal);
    color: var(--color-surface);
}

.mlc-btn + .mlc-btn {
    border-left: 1px solid var(--color-border);
}

.mlc-divider {
    height: 1px;
    background: var(--color-border);
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
    color: var(--color-text-secondary);
    cursor: pointer;
    user-select: none;
}

.mlc-toggle input[type="checkbox"] {
    width: 14px;
    height: 14px;
    accent-color: var(--color-teal);
    cursor: pointer;
    margin: 0;
    flex-shrink: 0;
}

@media (prefers-reduced-motion: reduce) {
    .mlc-btn { transition: none; }
}
</style>
