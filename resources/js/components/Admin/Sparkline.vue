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

    const values = props.data
    const numericValues = values.filter(v => v != null)
    if (numericValues.length < 2) return null

    const min = props.zeroLine ? numericValues.reduce((m, v) => Math.min(m, v), 0) : numericValues.reduce((m, v) => Math.min(m, v), Infinity)
    const max = props.zeroLine ? numericValues.reduce((m, v) => Math.max(m, v), 0) : numericValues.reduce((m, v) => Math.max(m, v), -Infinity)
    const range = max - min || 1
    const padding = 4

    const points = values.map((v, i) => {
        const x = (i / (values.length - 1)) * width
        if (v == null) return { x, y: null }
        const y = padding + ((max - v) / range) * (props.height - padding * 2)
        return { x, y }
    })

    // Build line segments, breaking at nulls
    const lineSegments = []
    const fillSegments = []
    let current = []

    for (const p of points) {
        if (p.y == null) {
            if (current.length >= 2) {
                lineSegments.push(current.map((pt, i) => `${i === 0 ? 'M' : 'L'}${pt.x.toFixed(1)},${pt.y.toFixed(1)}`).join(' '))
                fillSegments.push(
                    current.map((pt, i) => `${i === 0 ? 'M' : 'L'}${pt.x.toFixed(1)},${pt.y.toFixed(1)}`).join(' ')
                    + ` L${current[current.length - 1].x.toFixed(1)},${props.height} L${current[0].x.toFixed(1)},${props.height} Z`
                )
            }
            current = []
        } else {
            current.push(p)
        }
    }
    if (current.length >= 2) {
        lineSegments.push(current.map((pt, i) => `${i === 0 ? 'M' : 'L'}${pt.x.toFixed(1)},${pt.y.toFixed(1)}`).join(' '))
        fillSegments.push(
            current.map((pt, i) => `${i === 0 ? 'M' : 'L'}${pt.x.toFixed(1)},${pt.y.toFixed(1)}`).join(' ')
            + ` L${current[current.length - 1].x.toFixed(1)},${props.height} L${current[0].x.toFixed(1)},${props.height} Z`
        )
    }

    // Build gap connector paths (dotted lines between segments)
    const gapPaths = []
    const validPoints = points.filter(p => p.y != null)
    let inGap = false
    let gapStart = null
    for (let i = 0; i < points.length; i++) {
        if (points[i].y == null) {
            if (!inGap && gapStart == null) {
                // Find last valid point before this gap
                for (let j = i - 1; j >= 0; j--) {
                    if (points[j].y != null) { gapStart = points[j]; break }
                }
            }
            inGap = true
        } else if (inGap) {
            if (gapStart) {
                gapPaths.push(`M${gapStart.x.toFixed(1)},${gapStart.y.toFixed(1)} L${points[i].x.toFixed(1)},${points[i].y.toFixed(1)}`)
            }
            inGap = false
            gapStart = null
        }
    }

    const zeroY = props.zeroLine ? padding + ((max - 0) / range) * (props.height - padding * 2) : null
    const lastValid = validPoints[validPoints.length - 1] ?? null

    return { lineSegments, fillSegments, gapPaths, lastPoint: lastValid, zeroY }
})
</script>

<template>
    <svg :viewBox="`0 0 ${width} ${height}`" preserveAspectRatio="none" :style="{ width: '100%', height: height + 'px' }">
        <template v-if="pathData">
            <!-- Zero line -->
            <line v-if="zeroLine && pathData.zeroY" x1="0" :y1="pathData.zeroY" :x2="width" :y2="pathData.zeroY" :stroke="color" stroke-width="0.5" stroke-dasharray="2 2" opacity="0.3"/>

            <!-- Fill segments -->
            <path v-if="fill" v-for="(seg, i) in pathData.fillSegments" :key="'f'+i" :d="seg" :fill="color" opacity="0.06"/>

            <!-- Solid line segments -->
            <path v-for="(seg, i) in pathData.lineSegments" :key="'l'+i" :d="seg" fill="none" :stroke="color" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" opacity="0.7"/>

            <!-- Dotted gap connectors -->
            <path v-for="(seg, i) in pathData.gapPaths" :key="'g'+i" :d="seg" fill="none" :stroke="color" stroke-width="1" stroke-dasharray="3 3" opacity="0.3"/>

            <!-- Current value dot -->
            <circle v-if="showDot && pathData.lastPoint" :cx="pathData.lastPoint.x" :cy="pathData.lastPoint.y" r="3" :fill="color" stroke="var(--color-surface)" stroke-width="1.5"/>
        </template>
    </svg>
</template>
