<script setup>
const props = defineProps({
    value: { type: Number, default: 0 },
    label: { type: String, required: true },
    color: { type: String, default: 'green' },
    age: { type: Number, default: null },
    stale: { type: Boolean, default: false },
})

const gradients = {
    green: 'linear-gradient(90deg, var(--color-green), color-mix(in oklch, var(--color-green) 80%, white))',
    amber: 'linear-gradient(90deg, var(--color-amber), color-mix(in oklch, var(--color-amber) 80%, white))',
    blue: 'linear-gradient(90deg, var(--color-blue), color-mix(in oklch, var(--color-blue) 80%, white))',
}

const glows = {
    green: '0 0 6px color-mix(in oklch, var(--color-green) 15%, transparent)',
    amber: '0 0 6px color-mix(in oklch, var(--color-amber) 12%, transparent)',
    blue: '0 0 6px color-mix(in oklch, var(--color-blue) 12%, transparent)',
}

function formatAge(s) {
    if (s == null) return ''
    if (s < 60) return `${Math.round(s)}s`
    if (s < 3600) return `${Math.round(s / 60)}m`
    if (s < 86400) return `${Math.round(s / 3600)}h`
    return `${Math.round(s / 86400)}d`
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
        <span
            v-if="age !== null"
            class="level-bar__age"
            :class="{ 'level-bar__age--stale': stale }"
            :title="stale ? 'Stale — last known value' : 'Age of last reading'"
        >{{ stale ? 'stale' : formatAge(age) }}</span>
        <span class="level-bar__value" :class="{ 'level-bar__value--stale': stale }">{{ value > 0 ? Math.round(value) + '%' : '—' }}</span>
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
    background: var(--color-bg);
    border-radius: 5px;
    overflow: hidden;
}
.level-bar__fill {
    height: 100%;
    border-radius: 5px;
    transition: width 0.6s cubic-bezier(0.25, 1, 0.5, 1);
}
.level-bar__age {
    font-size: 10px;
    font-weight: 600;
    color: var(--color-text-dim);
    flex-shrink: 0;
    opacity: 0.7;
}
.level-bar__age--stale {
    color: var(--color-amber);
    opacity: 1;
}
.level-bar__value {
    font-family: 'Nunito Sans', sans-serif;
    font-size: 14px;
    font-weight: 600;
    width: 34px;
    text-align: right;
    flex-shrink: 0;
}
.level-bar__value--stale {
    opacity: 0.55;
}
</style>
