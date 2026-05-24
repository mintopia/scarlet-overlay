<script setup>
const props = defineProps({
    value: { type: Number, default: 0 },
    label: { type: String, required: true },
    color: { type: String, default: 'green' },
})

const gradients = {
    green: 'linear-gradient(90deg, oklch(0.4 0.14 150), oklch(0.48 0.16 150))',
    amber: 'linear-gradient(90deg, oklch(0.48 0.14 70), oklch(0.56 0.17 70))',
    blue: 'linear-gradient(90deg, oklch(0.38 0.11 245), oklch(0.45 0.14 245))',
}

const glows = {
    green: '0 0 6px oklch(0.48 0.16 150 / 0.15)',
    amber: '0 0 6px oklch(0.56 0.17 70 / 0.12)',
    blue: '0 0 6px oklch(0.45 0.14 245 / 0.12)',
}
</script>

<template>
    <div class="level-bar">
        <span class="level-bar__label">{{ label }}</span>
        <div class="level-bar__track">
            <div
                class="level-bar__fill"
                :style="{
                    width: Math.min(100, Math.max(0, value)) + '%',
                    background: gradients[color] || gradients.green,
                    boxShadow: value > 0 ? (glows[color] || glows.green) : 'none',
                }"
            />
        </div>
        <span class="level-bar__value">{{ value > 0 ? Math.round(value) + '%' : '—' }}</span>
    </div>
</template>

<style scoped>
.level-bar {
    display: flex;
    align-items: center;
    gap: 10px;
}
.level-bar__label {
    font-size: 12px;
    font-weight: 600;
    color: var(--color-text-dim);
    width: 56px;
    flex-shrink: 0;
}
.level-bar__track {
    flex: 1;
    height: 10px;
    background: oklch(0.93 0.01 205);
    border-radius: 5px;
    overflow: hidden;
}
.level-bar__fill {
    height: 100%;
    border-radius: 5px;
    transition: width 0.6s cubic-bezier(0.25, 1, 0.5, 1);
}
.level-bar__value {
    font-family: 'Nunito Sans', sans-serif;
    font-size: 14px;
    font-weight: 600;
    width: 34px;
    text-align: right;
    flex-shrink: 0;
}
</style>
