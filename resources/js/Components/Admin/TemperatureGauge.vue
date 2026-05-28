<script setup>
import { computed } from 'vue'

const props = defineProps({
    label: { type: String, required: true },
    temperature: { type: Number, default: null },
    humidity: { type: Number, default: null },
    color: { type: String, default: 'var(--color-scarlet)' },
    selected: { type: Boolean, default: false },
    min: { type: Number, default: 5 },
    max: { type: Number, default: 40 },
    sparkline: { type: Array, default: () => [] },
})

defineEmits(['select'])

const arcLength = 188
const arcOffset = 53

const fillLength = computed(() => {
    if (props.temperature == null) return 0
    const ratio = Math.max(0, Math.min(1, (props.temperature - props.min) / (props.max - props.min)))
    return ratio * arcLength
})

const sparkPath = computed(() => {
    if (!props.sparkline?.length || props.sparkline.length < 2) return null
    const vals = props.sparkline.map(v => v ?? 0)
    const min = Math.min(...vals)
    const max = Math.max(...vals)
    const range = max - min || 1
    const w = 60
    const h = 18
    const pad = 2
    return vals.map((v, i) => {
        const x = (i / (vals.length - 1)) * w
        const y = pad + ((max - v) / range) * (h - pad * 2)
        return `${i === 0 ? 'M' : 'L'}${x.toFixed(1)},${y.toFixed(1)}`
    }).join(' ')
})
</script>

<template>
    <button
        class="gauge"
        :class="{ 'gauge--selected': selected }"
        @click="$emit('select')"
    >
        <div class="gauge__label">{{ label }}</div>
        <div class="gauge__ring">
            <svg viewBox="0 0 80 80">
                <circle cx="40" cy="40" r="30" fill="none" stroke="var(--color-border)" stroke-width="4"/>
                <circle
                    v-if="temperature != null"
                    cx="40" cy="40" r="30"
                    fill="none"
                    :stroke="color"
                    stroke-width="4"
                    :stroke-dasharray="`${fillLength} ${arcLength}`"
                    :stroke-dashoffset="`-${arcOffset}`"
                    stroke-linecap="round"
                    transform="rotate(-90 40 40)"
                    class="gauge__arc"
                />
            </svg>
            <div class="gauge__value">
                <span class="gauge__temp" :style="{ color }">
                    {{ temperature != null ? temperature.toFixed(1) + '°' : '—' }}
                </span>
                <span v-if="humidity != null" class="gauge__hum">{{ Math.round(humidity) }}%</span>
            </div>
        </div>
        <svg v-if="sparkPath" class="gauge__spark" viewBox="0 0 60 18" width="60" height="18">
            <path :d="sparkPath" fill="none" :stroke="color" stroke-width="1.2" stroke-linecap="round" opacity="0.5"/>
        </svg>
    </button>
</template>

<style scoped>
.gauge {
    display: flex;
    flex-direction: column;
    align-items: center;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: 12px;
    padding: 16px 12px 14px;
    cursor: pointer;
    transition: border-color 0.15s, background 0.15s;
    -webkit-appearance: none;
    appearance: none;
    font-family: inherit;
    color: inherit;
}

.gauge:hover {
    border-color: var(--color-text-dim);
}

.gauge:focus-visible {
    outline: 2px solid var(--color-scarlet);
    outline-offset: 2px;
}

.gauge--selected {
    border-color: var(--color-scarlet-light);
    background: color-mix(in oklch, var(--color-scarlet) 3%, transparent);
}

.gauge--selected .gauge__label {
    color: var(--color-scarlet);
}

.gauge__label {
    font-family: var(--font-body);
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 2px;
    text-transform: uppercase;
    color: var(--color-text-dim);
    margin-bottom: 10px;
    transition: color 0.15s;
}

.gauge__ring {
    width: 100px;
    height: 100px;
    position: relative;
    margin-bottom: 6px;
}

.gauge__ring svg {
    width: 100%;
    height: 100%;
}

.gauge__arc {
    transition: stroke-dasharray 0.6s cubic-bezier(0.25, 1, 0.5, 1);
}

.gauge__value {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.gauge__temp {
    font-family: var(--font-sans);
    font-size: 22px;
    font-weight: 700;
    letter-spacing: -0.5px;
    line-height: 1;
}

.gauge__hum {
    font-family: var(--font-sans);
    font-size: 11px;
    font-weight: 600;
    color: var(--color-blue);
    margin-top: 3px;
}

.gauge__spark {
    opacity: 0.7;
}

@media (prefers-reduced-motion: reduce) {
    .gauge__arc { transition: none; }
}
</style>
