<script setup>
import { computed } from 'vue'

const props = defineProps({
    tws: { type: Number, default: 0 },
    twa: { type: Number, default: 0 },
    size: { type: Number, default: 175 },
})

const absTwa = computed(() => Math.abs(props.twa))
const isPort = computed(() => props.twa < 0)

const pointOfSail = computed(() => {
    const a = absTwa.value
    if (a < 45) return 'In Irons'
    if (a < 60) return 'Close Hauled'
    if (a < 80) return 'Close Reach'
    if (a < 100) return 'Beam Reach'
    if (a < 150) return 'Broad Reach'
    if (a < 170) return 'Running'
    return 'Dead Run'
})

const beaufort = computed(() => {
    const s = props.tws
    if (s < 1) return 'F0'
    if (s < 4) return 'F1'
    if (s < 7) return 'F2'
    if (s < 11) return 'F3'
    if (s < 17) return 'F4'
    if (s < 22) return 'F5'
    if (s < 28) return 'F6'
    if (s < 34) return 'F7'
    if (s < 41) return 'F8'
    if (s < 48) return 'F9'
    if (s < 56) return 'F10'
    if (s < 64) return 'F11'
    return 'F12'
})

function polarToXY(cx, cy, r, angleDeg) {
    const rad = (angleDeg - 90) * Math.PI / 180
    return { x: cx + r * Math.cos(rad), y: cy + r * Math.sin(rad) }
}

const twaAngle = computed(() => isPort.value ? -absTwa.value : absTwa.value)

const arrowTip = computed(() => polarToXY(95, 95, 80, twaAngle.value))
const arrowLeft = computed(() => polarToXY(95, 95, 70, twaAngle.value - 6))
const arrowRight = computed(() => polarToXY(95, 95, 70, twaAngle.value + 6))

const arrowLine = computed(() => {
    const end = polarToXY(95, 95, 78, twaAngle.value)
    return { x2: end.x, y2: end.y }
})

const feathers = computed(() => {
    return [0.88, 0.8, 0.72].map(factor => {
        const base = polarToXY(95, 95, 80 * factor, twaAngle.value)
        const tip = polarToXY(base.x, base.y, 12, twaAngle.value - 90)
        return { x1: base.x, y1: base.y, x2: tip.x, y2: tip.y }
    })
})
</script>

<template>
    <svg :width="size" :height="size" viewBox="0 0 190 190">
        <!-- Face -->
        <circle cx="95" cy="95" r="89" fill="var(--color-surface)" stroke="var(--color-border)" stroke-width="0.75"/>
        <circle cx="95" cy="95" r="85" fill="none" stroke="oklch(0.92 0.008 205)" stroke-width="0.5"/>

        <!-- Point of sail zones (all endpoints on r=85 circle centered at 95,95) -->
        <path d="M95,95 L34.90,34.90 A85,85 0 0,1 155.10,34.90 Z" fill="oklch(0.48 0.22 25 / 0.14)"/>
        <path d="M95,95 L155.10,34.90 A85,85 0 0,1 168.61,52.50 Z" fill="oklch(0.42 0.14 178 / 0.12)"/>
        <path d="M95,95 L21.39,52.50 A85,85 0 0,1 34.90,34.90 Z" fill="oklch(0.42 0.14 178 / 0.12)"/>
        <path d="M95,95 L168.61,52.50 A85,85 0 0,1 178.71,80.24 Z" fill="oklch(0.42 0.14 178 / 0.08)"/>
        <path d="M95,95 L11.29,80.24 A85,85 0 0,1 21.39,52.50 Z" fill="oklch(0.42 0.14 178 / 0.08)"/>
        <path d="M95,95 L178.71,80.24 A85,85 0 0,1 178.71,109.76 Z" fill="oklch(0.48 0.17 70 / 0.1)"/>
        <path d="M95,95 L11.29,109.76 A85,85 0 0,1 11.29,80.24 Z" fill="oklch(0.48 0.17 70 / 0.1)"/>
        <path d="M95,95 L178.71,109.76 A85,85 0 0,1 137.50,168.61 Z" fill="oklch(0.48 0.17 70 / 0.14)"/>
        <path d="M95,95 L52.50,168.61 A85,85 0 0,1 11.29,109.76 Z" fill="oklch(0.48 0.17 70 / 0.14)"/>
        <path d="M95,95 L137.50,168.61 A85,85 0 0,1 95.00,180.00 Z" fill="oklch(0.45 0.12 90 / 0.1)"/>
        <path d="M95,95 L95.00,180.00 A85,85 0 0,1 52.50,168.61 Z" fill="oklch(0.45 0.12 90 / 0.1)"/>

        <!-- Tick marks -->
        <g stroke="oklch(0.82 0.006 205)" stroke-width="0.5">
            <line x1="95" y1="6" x2="95" y2="13"/><line x1="95" y1="177" x2="95" y2="184"/>
            <line x1="6" y1="95" x2="13" y2="95"/><line x1="177" y1="95" x2="184" y2="95"/>
            <line x1="30" y1="30" x2="35" y2="35"/><line x1="160" y1="30" x2="155" y2="35"/>
            <line x1="30" y1="160" x2="35" y2="155"/><line x1="160" y1="160" x2="155" y2="155"/>
        </g>

        <!-- Labels -->
        <text x="95" y="22" text-anchor="middle" fill="oklch(0.65 0.01 205)" font-size="8" font-family="Nunito Sans" font-weight="700">0°</text>
        <text x="173" y="98" text-anchor="middle" fill="oklch(0.65 0.01 205)" font-size="8" font-family="Nunito Sans" font-weight="700">90°</text>
        <text x="95" y="178" text-anchor="middle" fill="oklch(0.65 0.01 205)" font-size="8" font-family="Nunito Sans" font-weight="700">180°</text>
        <text x="17" y="98" text-anchor="middle" fill="oklch(0.65 0.01 205)" font-size="8" font-family="Nunito Sans" font-weight="700">90°</text>
        <text x="26" y="70" text-anchor="middle" fill="oklch(0.7 0.008 205)" font-size="7" font-family="DM Sans" font-weight="800">PORT</text>
        <text x="164" y="70" text-anchor="middle" fill="oklch(0.7 0.008 205)" font-size="7" font-family="DM Sans" font-weight="800">STBD</text>

        <!-- Bow marker + boat -->
        <polygon points="95,10 91,22 99,22" fill="var(--color-teal)" opacity="0.4"/>
        <path d="M95,34 L89,60 L91,65 L95,62 L99,65 L101,60 Z" fill="var(--color-teal)" opacity="0.3"/>

        <!-- TWA arrow -->
        <line x1="95" y1="95" :x2="arrowLine.x2" :y2="arrowLine.y2" stroke="var(--color-amber)" stroke-width="2.5" stroke-dasharray="7 4" opacity="0.75">
            <animate attributeName="stroke-dashoffset" values="0;-11" dur="1.2s" repeatCount="indefinite"/>
        </line>
        <polygon :points="`${arrowTip.x},${arrowTip.y} ${arrowLeft.x},${arrowLeft.y} ${arrowRight.x},${arrowRight.y}`" fill="var(--color-amber)"/>

        <!-- Feathers -->
        <line v-for="(f, i) in feathers" :key="i" :x1="f.x1" :y1="f.y1" :x2="f.x2" :y2="f.y2" stroke="var(--color-amber)" stroke-width="1.2" stroke-linecap="round" opacity="0.3"/>

        <!-- Center circle with TWS -->
        <circle cx="95" cy="95" r="27" fill="var(--color-surface)" stroke="var(--color-border)" stroke-width="0.5"/>
        <text x="95" y="92" text-anchor="middle" fill="var(--color-amber)" font-size="26" font-weight="800" font-family="Nunito Sans" dominant-baseline="central">{{ tws.toFixed(1) }}</text>
        <text x="95" y="112" text-anchor="middle" fill="var(--color-text-dim)" font-size="9" font-weight="700" font-family="Nunito Sans">kts</text>
    </svg>
</template>
