<script setup>
import { computed } from 'vue'

const props = defineProps({
    bearing: { type: Number, default: null },
    speed: { type: Number, default: null },
    size: { type: Number, default: 100 },
    color: { type: String, default: 'var(--color-amber)' },
})

function polarToXY(cx, cy, r, angleDeg) {
    const rad = (angleDeg - 90) * Math.PI / 180
    return { x: cx + r * Math.cos(rad), y: cy + r * Math.sin(rad) }
}

const arrow = computed(() => {
    if (props.bearing === null) return null
    const tip = polarToXY(50, 50, 38, props.bearing)
    const left = polarToXY(50, 50, 30, props.bearing - 8)
    const right = polarToXY(50, 50, 30, props.bearing + 8)
    const line = polarToXY(50, 50, 40, props.bearing)
    return { tip, left, right, line }
})

const speedText = computed(() => {
    if (props.speed === null) return '—'
    return props.speed.toFixed(1)
})
</script>

<template>
    <svg :width="size" :height="size" viewBox="0 0 100 100">
        <!-- Face -->
        <circle cx="50" cy="50" r="46" fill="var(--color-surface)" stroke="var(--color-border)" stroke-width="0.8"/>
        <circle cx="50" cy="50" r="42" fill="none" stroke="var(--color-border-light)" stroke-width="0.4"/>

        <!-- Tick marks every 30° -->
        <line v-for="a in [0,30,60,90,120,150,180,210,240,270,300,330]" :key="a"
              :x1="polarToXY(50,50, a % 90 === 0 ? 38 : 40, a).x"
              :y1="polarToXY(50,50, a % 90 === 0 ? 38 : 40, a).y"
              :x2="polarToXY(50,50,44, a).x"
              :y2="polarToXY(50,50,44, a).y"
              :stroke="a % 90 === 0 ? 'var(--color-text-dim)' : 'var(--color-border)'"
              :stroke-width="a % 90 === 0 ? '1' : '0.5'"/>

        <!-- Cardinals -->
        <text x="50" y="14" text-anchor="middle" fill="var(--color-scarlet)" font-size="7" font-weight="800" font-family="Nunito Sans" dominant-baseline="central">N</text>
        <text x="88" y="52" text-anchor="middle" fill="var(--color-text-dim)" font-size="5.5" font-family="Nunito Sans" font-weight="600" dominant-baseline="central">E</text>
        <text x="50" y="90" text-anchor="middle" fill="var(--color-text-dim)" font-size="5.5" font-family="Nunito Sans" font-weight="600" dominant-baseline="central">S</text>
        <text x="12" y="52" text-anchor="middle" fill="var(--color-text-dim)" font-size="5.5" font-family="Nunito Sans" font-weight="600" dominant-baseline="central">W</text>

        <!-- Wind arrow -->
        <template v-if="arrow">
            <line x1="50" y1="50" :x2="arrow.line.x" :y2="arrow.line.y" :stroke="color" stroke-width="2" opacity="0.3"/>
            <polygon :points="`${arrow.tip.x},${arrow.tip.y} ${arrow.left.x},${arrow.left.y} ${arrow.right.x},${arrow.right.y}`" :fill="color" opacity="0.8"/>
        </template>

        <!-- Speed in center -->
        <text x="50" y="48" text-anchor="middle" :fill="color" font-size="16" font-weight="700" font-family="Nunito Sans" dominant-baseline="central">{{ speedText }}</text>
        <text x="50" y="60" text-anchor="middle" fill="var(--color-text-dim)" font-size="6" font-weight="600" font-family="Nunito Sans">kts</text>
    </svg>
</template>
