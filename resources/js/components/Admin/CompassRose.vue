<script setup>
import { computed } from 'vue'

const props = defineProps({
    heading: { type: Number, default: 0 },
    cog: { type: Number, default: null },
    twa: { type: Number, default: null },
    awa: { type: Number, default: null },
    size: { type: Number, default: 165 },
})

function polarToXY(cx, cy, r, angleDeg) {
    const rad = (angleDeg - 90) * Math.PI / 180
    return { x: cx + r * Math.cos(rad), y: cy + r * Math.sin(rad) }
}

function arcPath(startAngle, endAngle, r) {
    const p1 = polarToXY(90, 90, r, startAngle)
    const p2 = polarToXY(90, 90, r, endAngle)
    const diff = ((endAngle - startAngle) + 360) % 360
    const largeArc = diff > 180 ? 1 : 0
    return `M${p1.x.toFixed(2)},${p1.y.toFixed(2)} A${r},${r} 0 ${largeArc},1 ${p2.x.toFixed(2)},${p2.y.toFixed(2)}`
}

const cogRelative = computed(() => {
    if (props.cog === null) return null
    return ((props.cog - props.heading) % 360 + 360) % 360
})

const cogLine = computed(() => {
    if (cogRelative.value === null) return null
    const end = polarToXY(90, 90, 76, cogRelative.value)
    return { x2: end.x, y2: end.y }
})

const twaArrow = computed(() => {
    if (props.twa === null) return null
    const tip = polarToXY(90, 90, 72, props.twa)
    const left = polarToXY(90, 90, 63, props.twa - 5)
    const right = polarToXY(90, 90, 63, props.twa + 5)
    const line = polarToXY(90, 90, 74, props.twa)
    return { tip, left, right, line }
})

const awaArrow = computed(() => {
    if (props.awa === null) return null
    const tip = polarToXY(90, 90, 68, props.awa)
    const left = polarToXY(90, 90, 60, props.awa - 5)
    const right = polarToXY(90, 90, 60, props.awa + 5)
    const line = polarToXY(90, 90, 70, props.awa)
    return { tip, left, right, line }
})

const twaFeathers = computed(() => {
    if (props.twa === null) return []
    return [0.85, 0.75, 0.65].map(f => {
        const base = polarToXY(90, 90, 74 * f, props.twa)
        const tip = polarToXY(base.x, base.y, 8, props.twa - 90)
        return { x1: base.x, y1: base.y, x2: tip.x, y2: tip.y }
    })
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

const zoneArcs = computed(() => {
    const r = 89
    return [
        { d: arcPath(-45, 45, r), color: 'var(--color-scarlet)', opacity: 0.45 },
        { d: arcPath(45, 60, r), color: 'var(--color-teal)', opacity: 0.55 },
        { d: arcPath(-60, -45, r), color: 'var(--color-teal)', opacity: 0.55 },
        { d: arcPath(60, 80, r), color: 'var(--color-teal)', opacity: 0.35 },
        { d: arcPath(-80, -60, r), color: 'var(--color-teal)', opacity: 0.35 },
        { d: arcPath(80, 100, r), color: 'var(--color-amber)', opacity: 0.5 },
        { d: arcPath(-100, -80, r), color: 'var(--color-amber)', opacity: 0.5 },
        { d: arcPath(100, 150, r), color: 'var(--color-amber)', opacity: 0.65 },
        { d: arcPath(-150, -100, r), color: 'var(--color-amber)', opacity: 0.65 },
        { d: arcPath(150, 180, r), color: 'var(--color-green)', opacity: 0.5 },
        { d: arcPath(-180, -150, r), color: 'var(--color-green)', opacity: 0.5 },
    ]
})

const zoneLabels = computed(() => {
    const r = 98
    const defs = [
        { start: -45, end: 45, label: 'NO GO', color: 'var(--color-scarlet)' },
        { start: 45, end: 60, label: 'CH', color: 'var(--color-teal)' },
        { start: 60, end: 80, label: 'CR', color: 'var(--color-teal)' },
        { start: 80, end: 100, label: 'BEAM', color: 'var(--color-amber)' },
        { start: 100, end: 150, label: 'BROAD REACH', color: 'var(--color-amber)' },
        { start: 150, end: 180, label: 'RUN', color: 'var(--color-green)' },
        { start: -60, end: -45, label: 'CH', color: 'var(--color-teal)' },
        { start: -80, end: -60, label: 'CR', color: 'var(--color-teal)' },
        { start: -100, end: -80, label: 'BEAM', color: 'var(--color-amber)' },
        { start: -150, end: -100, label: 'BROAD REACH', color: 'var(--color-amber)' },
        { start: -180, end: -150, label: 'RUN', color: 'var(--color-green)' },
    ]
    return defs.map(z => {
        const mid = (z.start + z.end) / 2
        const pos = polarToXY(90, 90, r, mid)
        const normMid = ((mid % 360) + 360) % 360
        const rot = (normMid > 90 && normMid < 270) ? mid - 180 : mid
        return { ...z, x: pos.x, y: pos.y, rotation: rot }
    })
})
</script>

<template>
    <svg :width="size" :height="size" viewBox="-12 -12 204 204">
        <!-- Background -->
        <circle cx="90" cy="90" r="84" fill="var(--color-surface)" stroke="var(--color-border)" stroke-width="0.75"/>

        <!-- Point of sail zone arcs (fixed relative to boat) -->
        <path v-for="(z, i) in zoneArcs" :key="'zone'+i" :d="z.d" fill="none" :stroke="z.color" :stroke-opacity="z.opacity" stroke-width="5" stroke-linecap="butt"/>

        <!-- Zone labels -->
        <text v-for="(l, i) in zoneLabels" :key="'zl'+i"
              :x="l.x" :y="l.y"
              :transform="`rotate(${l.rotation}, ${l.x}, ${l.y})`"
              text-anchor="middle" dominant-baseline="central"
              :fill="l.color" font-size="4.5" font-weight="700" font-family="DM Sans"
              letter-spacing="0.8" opacity="0.6">
            {{ l.label }}
        </text>

        <!-- Rotating compass ring -->
        <g :style="{ transform: `rotate(${-heading}deg)`, transformOrigin: '90px 90px', transition: 'transform 0.8s cubic-bezier(0.4, 0, 0.2, 1)' }">
            <circle cx="90" cy="90" r="80" fill="none" stroke="var(--color-border-light)" stroke-width="0.5"/>
            <circle cx="90" cy="90" r="55" fill="none" stroke="var(--color-border-light)" stroke-width="0.3" stroke-dasharray="2 3"/>

            <!-- Major ticks -->
            <line v-for="(t, i) in majorTicks" :key="'maj'+i" :x1="t.x1" :y1="t.y1" :x2="t.x2" :y2="t.y2" stroke="var(--color-text-dim)" stroke-width="1.2"/>
            <!-- Minor ticks -->
            <line v-for="(t, i) in minorTicks" :key="'min'+i" :x1="t.x1" :y1="t.y1" :x2="t.x2" :y2="t.y2" stroke="var(--color-border)" stroke-width="0.5"/>
            <!-- Fine ticks -->
            <line v-for="(t, i) in fineTicks" :key="'fine'+i" :x1="t.x1" :y1="t.y1" :x2="t.x2" :y2="t.y2" stroke="var(--color-border-light)" stroke-width="0.3"/>

            <!-- Cardinals -->
            <text x="90" y="30" text-anchor="middle" fill="var(--color-scarlet)" font-size="12" font-weight="800" font-family="Nunito Sans" dominant-baseline="central">N</text>
            <text x="153" y="93" text-anchor="middle" fill="var(--color-text-dim)" font-size="9" font-family="Nunito Sans" font-weight="600" dominant-baseline="central">E</text>
            <text x="90" y="158" text-anchor="middle" fill="var(--color-text-dim)" font-size="9" font-family="Nunito Sans" font-weight="600" dominant-baseline="central">S</text>
            <text x="27" y="93" text-anchor="middle" fill="var(--color-text-dim)" font-size="9" font-family="Nunito Sans" font-weight="600" dominant-baseline="central">W</text>

            <!-- Intercardinals -->
            <text x="140.9" y="39.1" text-anchor="middle" fill="var(--color-text-dim)" font-size="7" font-family="Nunito Sans" font-weight="600" dominant-baseline="central">NE</text>
            <text x="140.9" y="140.9" text-anchor="middle" fill="var(--color-text-dim)" font-size="7" font-family="Nunito Sans" font-weight="600" dominant-baseline="central">SE</text>
            <text x="39.1" y="140.9" text-anchor="middle" fill="var(--color-text-dim)" font-size="7" font-family="Nunito Sans" font-weight="600" dominant-baseline="central">SW</text>
            <text x="39.1" y="39.1" text-anchor="middle" fill="var(--color-text-dim)" font-size="7" font-family="Nunito Sans" font-weight="600" dominant-baseline="central">NW</text>
        </g>

        <!-- Fixed elements: boat and COG -->

        <!-- Heading index mark at top -->
        <polygon points="90,-4 86,4 94,4" fill="var(--color-teal)" opacity="0.7"/>

        <!-- COG dashed line (relative to heading) -->
        <line v-if="cogLine" x1="90" y1="90" :x2="cogLine.x2" :y2="cogLine.y2" stroke="var(--color-teal)" stroke-width="1.5" stroke-dasharray="5 3" opacity="0.35"/>

        <!-- AWA arrow (relative to boat, pink — shorter, behind TWA) -->
        <g v-if="awaArrow">
            <line x1="90" y1="90" :x2="awaArrow.line.x" :y2="awaArrow.line.y" stroke="var(--color-pink)" stroke-width="1.5" stroke-dasharray="4 3" opacity="0.45"/>
            <polygon :points="`${awaArrow.tip.x},${awaArrow.tip.y} ${awaArrow.left.x},${awaArrow.left.y} ${awaArrow.right.x},${awaArrow.right.y}`" fill="var(--color-pink)" opacity="0.6"/>
        </g>

        <!-- TWA wind arrow (relative to boat, amber) -->
        <g v-if="twaArrow">
            <line x1="90" y1="90" :x2="twaArrow.line.x" :y2="twaArrow.line.y" stroke="var(--color-amber)" stroke-width="2" stroke-dasharray="6 4" opacity="0.6">
                <animate attributeName="stroke-dashoffset" values="0;-10" dur="1.2s" repeatCount="indefinite"/>
            </line>
            <polygon :points="`${twaArrow.tip.x},${twaArrow.tip.y} ${twaArrow.left.x},${twaArrow.left.y} ${twaArrow.right.x},${twaArrow.right.y}`" fill="var(--color-amber)" opacity="0.7"/>
            <line v-for="(f, i) in twaFeathers" :key="'twaf'+i" :x1="f.x1" :y1="f.y1" :x2="f.x2" :y2="f.y2" stroke="var(--color-amber)" stroke-width="1" stroke-linecap="round" opacity="0.3"/>
        </g>

        <!-- Boat hull outline (fixed pointing up) -->
        <path d="M90,58 C87,65 84.5,73 84.5,83 L84.5,94 C84.5,99 86.5,101.5 90,101.5 C93.5,101.5 95.5,99 95.5,94 L95.5,83 C95.5,73 93,65 90,58 Z"
              fill="var(--color-surface)" stroke="var(--color-teal)" stroke-width="1.5" stroke-linejoin="round"/>
        <!-- Keel line -->
        <line x1="90" y1="64" x2="90" y2="98" stroke="var(--color-teal)" stroke-width="0.5" opacity="0.3"/>
        <!-- Beam mark -->
        <line x1="84.5" y1="83" x2="95.5" y2="83" stroke="var(--color-teal)" stroke-width="0.4" opacity="0.2"/>
    </svg>
</template>
