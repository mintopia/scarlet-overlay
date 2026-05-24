<script setup>
import { computed } from 'vue'

const props = defineProps({
    heading: { type: Number, default: 0 },
    cog: { type: Number, default: null },
    size: { type: Number, default: 165 },
})

const headingRotate = computed(() => props.heading - 90)
const cogRotate = computed(() => props.cog !== null ? props.cog - 90 : null)

function polarToXY(cx, cy, r, angleDeg) {
    const rad = (angleDeg - 90) * Math.PI / 180
    return { x: cx + r * Math.cos(rad), y: cy + r * Math.sin(rad) }
}

const headingArrow = computed(() => {
    const tip = polarToXY(90, 90, 78, props.heading)
    const left = polarToXY(90, 90, 68, props.heading - 8)
    const right = polarToXY(90, 90, 68, props.heading + 8)
    return `${tip.x},${tip.y} ${left.x},${left.y} ${right.x},${right.y}`
})

const headingLine = computed(() => {
    const end = polarToXY(90, 90, 80, props.heading)
    return { x2: end.x, y2: end.y }
})

const cogLine = computed(() => {
    if (props.cog === null) return null
    const end = polarToXY(90, 90, 80, props.cog)
    return { x2: end.x, y2: end.y }
})

const majorTicks = computed(() => {
    return [0, 90, 180, 270].map(angle => {
        const inner = polarToXY(90, 90, 74, angle)
        const outer = polarToXY(90, 90, 84, angle)
        return { x1: inner.x, y1: inner.y, x2: outer.x, y2: outer.y }
    })
})

const minorTicks = computed(() => {
    return [30, 60, 120, 150, 210, 240, 300, 330].map(angle => {
        const inner = polarToXY(90, 90, 76, angle)
        const outer = polarToXY(90, 90, 82, angle)
        return { x1: inner.x, y1: inner.y, x2: outer.x, y2: outer.y }
    })
})

const fineTicks = computed(() => {
    const ticks = []
    for (let a = 0; a < 360; a += 10) {
        if (a % 30 !== 0) {
            const inner = polarToXY(90, 90, 78, a)
            const outer = polarToXY(90, 90, 82, a)
            ticks.push({ x1: inner.x, y1: inner.y, x2: outer.x, y2: outer.y })
        }
    }
    return ticks
})
</script>

<template>
    <svg :width="size" :height="size" viewBox="0 0 180 180">
        <!-- Face -->
        <circle cx="90" cy="90" r="84" fill="var(--color-surface)" stroke="var(--color-border)" stroke-width="0.75"/>
        <circle cx="90" cy="90" r="80" fill="none" stroke="oklch(0.92 0.008 205)" stroke-width="0.5"/>
        <circle cx="90" cy="90" r="55" fill="none" stroke="oklch(0.94 0.005 205)" stroke-width="0.3" stroke-dasharray="2 3"/>

        <!-- Major ticks -->
        <line v-for="(t, i) in majorTicks" :key="'maj'+i" :x1="t.x1" :y1="t.y1" :x2="t.x2" :y2="t.y2" stroke="oklch(0.72 0.012 205)" stroke-width="1.2"/>
        <!-- Minor ticks -->
        <line v-for="(t, i) in minorTicks" :key="'min'+i" :x1="t.x1" :y1="t.y1" :x2="t.x2" :y2="t.y2" stroke="oklch(0.82 0.006 205)" stroke-width="0.5"/>
        <!-- Fine ticks -->
        <line v-for="(t, i) in fineTicks" :key="'fine'+i" :x1="t.x1" :y1="t.y1" :x2="t.x2" :y2="t.y2" stroke="oklch(0.88 0.004 205)" stroke-width="0.3"/>

        <!-- Cardinals -->
        <text x="90" y="30" text-anchor="middle" fill="var(--color-scarlet)" font-size="12" font-weight="800" font-family="Nunito Sans" dominant-baseline="central">N</text>
        <text x="153" y="93" text-anchor="middle" fill="oklch(0.6 0.012 205)" font-size="9" font-family="Nunito Sans" font-weight="600" dominant-baseline="central">E</text>
        <text x="90" y="158" text-anchor="middle" fill="oklch(0.6 0.012 205)" font-size="9" font-family="Nunito Sans" font-weight="600" dominant-baseline="central">S</text>
        <text x="27" y="93" text-anchor="middle" fill="oklch(0.6 0.012 205)" font-size="9" font-family="Nunito Sans" font-weight="600" dominant-baseline="central">W</text>

        <!-- Heading line + arrow -->
        <line x1="90" y1="90" :x2="headingLine.x2" :y2="headingLine.y2" stroke="var(--color-teal)" stroke-width="3" opacity="0.2"/>
        <polygon :points="headingArrow" fill="var(--color-teal)"/>

        <!-- COG dashed line -->
        <line v-if="cogLine" x1="90" y1="90" :x2="cogLine.x2" :y2="cogLine.y2" stroke="var(--color-teal)" stroke-width="1.5" stroke-dasharray="4 3" opacity="0.3"/>

        <!-- Center -->
        <circle cx="90" cy="90" r="2.5" fill="oklch(0.7 0.012 205)"/>
    </svg>
</template>
