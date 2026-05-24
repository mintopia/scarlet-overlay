<script setup>
import { computed } from 'vue'

const props = defineProps({
    data: { type: Array, required: true },
    color: { type: String, default: 'var(--color-teal)' },
    height: { type: Number, default: 36 },
    fill: { type: Boolean, default: true },
    showDot: { type: Boolean, default: true },
    zeroLine: { type: Boolean, default: false },
    colorPositive: { type: String, default: 'var(--color-green)' },
    colorNegative: { type: String, default: 'var(--color-scarlet)' },
})

const width = 300

const pathData = computed(() => {
    if (!props.data || props.data.length < 2) return null

    const values = props.data.map(v => v ?? 0)
    const min = props.zeroLine ? Math.min(0, ...values) : Math.min(...values)
    const max = props.zeroLine ? Math.max(0, ...values) : Math.max(...values)
    const range = max - min || 1
    const padding = 4

    const points = values.map((v, i) => {
        const x = (i / (values.length - 1)) * width
        const y = padding + ((max - v) / range) * (props.height - padding * 2)
        return { x, y }
    })

    const linePath = points.map((p, i) => `${i === 0 ? 'M' : 'L'}${p.x.toFixed(1)},${p.y.toFixed(1)}`).join(' ')
    const fillPath = linePath + ` L${width},${props.height} L0,${props.height} Z`
    const zeroY = props.zeroLine ? padding + ((max - 0) / range) * (props.height - padding * 2) : null
    const lastPoint = points[points.length - 1]

    return { linePath, fillPath, lastPoint, zeroY }
})
</script>

<template>
    <svg :viewBox="`0 0 ${width} ${height}`" preserveAspectRatio="none" :style="{ width: '100%', height: height + 'px' }">
        <template v-if="pathData">
            <!-- Zero line -->
            <line v-if="zeroLine && pathData.zeroY" x1="0" :y1="pathData.zeroY" :x2="width" :y2="pathData.zeroY" :stroke="color" stroke-width="0.5" stroke-dasharray="2 2" opacity="0.3"/>

            <!-- Fill -->
            <path v-if="fill" :d="pathData.fillPath" :fill="color" opacity="0.06"/>

            <!-- Line -->
            <path :d="pathData.linePath" fill="none" :stroke="color" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" opacity="0.7"/>

            <!-- Current value dot -->
            <circle v-if="showDot && pathData.lastPoint" :cx="pathData.lastPoint.x" :cy="pathData.lastPoint.y" r="3" :fill="color" stroke="white" stroke-width="1.5"/>
        </template>
    </svg>
</template>
