<script setup>
import { computed } from 'vue'
import { buildSegments, waterFillPath } from '@/lib/trendPath.js'

const props = defineProps({
    data: { type: Array, required: true },
    variant: { type: String, default: 'line' }, // 'line' | 'area' | 'water' | 'bipolar'
    color: { type: String, default: 'var(--color-teal)' },
    colorPositive: { type: String, default: 'var(--color-green)' },
    colorNegative: { type: String, default: 'var(--color-scarlet)' },
    height: { type: Number, default: 88 },
    width: { type: Number, default: 300 },
    zeroValue: { type: Number, default: 0 },
    timeAxis: { type: Boolean, default: false },
    markers: { type: Array, default: () => [] }, // [{x, magnitude}] — x in data-index space
    markerColor: { type: String, default: 'var(--color-scarlet)' },
})

/**
 * Normalise each data item to a numeric value (or null for gaps).
 * Accepts: number | null | {t, v}
 */
function toValue(item) {
    if (item == null) return null
    if (typeof item === 'number') return item
    if (typeof item === 'object' && 'v' in item) return item.v
    return null
}

/**
 * Scale a data-index (0..n-1) to SVG x (0..width).
 * With n===1 we place the single point at width/2.
 */
function indexToX(i, n, width) {
    if (n <= 1) return width / 2
    return i * (width / (n - 1))
}

/**
 * Build the array of {x, v} points in SVG space, filtering out nulls.
 * The CRITICAL contract: x must be pre-scaled to 0..width so that
 * buildSegments and waterFillPath receive x values already in SVG space.
 */
const svgPoints = computed(() => {
    if (!props.data || props.data.length === 0) return []
    const n = props.data.length
    return props.data
        .map((item, i) => ({ x: indexToX(i, n, props.width), v: toValue(item) }))
        .filter((p) => p.v != null)
})

/** Aria summary: min / max / last value across the dataset. */
const ariaSummary = computed(() => {
    if (svgPoints.value.length === 0) return 'No data'
    const vals = svgPoints.value.map((p) => p.v)
    const min = Math.min(...vals)
    const max = Math.max(...vals)
    const last = vals[vals.length - 1]
    return `Trend chart. Min ${min.toFixed(2)}, max ${max.toFixed(2)}, latest ${last.toFixed(2)}.`
})

/** Line + optional fill for 'line' and 'area' variants. */
const linePath = computed(() => {
    const pts = svgPoints.value
    if (pts.length < 2) return null

    const vals = pts.map((p) => p.v)
    const minV = Math.min(...vals)
    const maxV = Math.max(...vals)
    const rangeV = maxV - minV || 1

    const toY = (v) => props.height * (maxV - v) / rangeV

    const coords = pts.map((p) => ({ x: p.x, y: toY(p.v) }))
    const d = coords.map((c, i) => `${i === 0 ? 'M' : 'L'}${c.x.toFixed(2)},${c.y.toFixed(2)}`).join(' ')

    // Area fill: drop to bottom and back
    const fillD = d
        + ` L${coords[coords.length - 1].x.toFixed(2)},${props.height}`
        + ` L${coords[0].x.toFixed(2)},${props.height} Z`

    const last = coords[coords.length - 1]

    return { line: d, fill: fillD, last }
})

/** Bipolar segments (above/below zeroValue) via trendPath.buildSegments. */
const bipolarData = computed(() => {
    const pts = svgPoints.value
    if (pts.length < 2) return null

    // buildSegments expects x already in SVG space (0..width) — svgPoints satisfies this.
    const { above, below, line } = buildSegments(pts, {
        width: props.width,
        height: props.height,
        zeroValue: props.zeroValue,
    })

    // Zero baseline y in SVG space
    const vals = pts.map((p) => p.v)
    const minV = Math.min(props.zeroValue, ...vals)
    const maxV = Math.max(props.zeroValue, ...vals)
    const rangeV = maxV - minV || 1
    const zeroY = props.height * (maxV - props.zeroValue) / rangeV

    // Derive end dot from the full line's last screen point
    // trendPath's internal screenPts aren't exposed, so we replicate the y for the last point.
    const lastPt = pts[pts.length - 1]
    const lastY = props.height * (maxV - lastPt.v) / rangeV
    const last = { x: lastPt.x, y: lastY }

    return { above, below, line, zeroY, last }
})

/** Water fill via trendPath.waterFillPath. */
const waterData = computed(() => {
    const pts = svgPoints.value
    if (pts.length < 2) return null

    // waterFillPath needs last x === width; svgPoints already guarantees this for n-1 mapping.
    const fillPath = waterFillPath(pts, { width: props.width, height: props.height })

    // Line on top of the fill (reuse the same y mapping waterFillPath uses internally)
    const vals = pts.map((p) => p.v)
    const minV = Math.min(...vals)
    const maxV = Math.max(...vals)
    const rangeV = maxV - minV || 1
    const toY = (v) => props.height * (maxV - v) / rangeV

    const coords = pts.map((p) => ({ x: p.x, y: toY(p.v) }))
    const linePath = coords.map((c, i) => `${i === 0 ? 'M' : 'L'}${c.x.toFixed(2)},${c.y.toFixed(2)}`).join(' ')
    const last = coords[coords.length - 1]

    return { fillPath, linePath, last }
})

/** Marker bars: x in data-index space → scale to SVG x; height ∝ magnitude. */
const markerBars = computed(() => {
    if (!props.markers || props.markers.length === 0) return []
    const n = props.data ? props.data.length : 0
    if (n === 0) return []

    const magnitudes = props.markers.map((m) => m.magnitude).filter(Boolean)
    const maxMag = magnitudes.length > 0 ? Math.max(...magnitudes) : 1

    return props.markers.map((m) => {
        const svgX = indexToX(m.x, n, props.width)
        const barHeight = maxMag > 0 ? (m.magnitude / maxMag) * props.height : props.height
        return {
            x: svgX,
            y: props.height - barHeight,
            height: barHeight,
        }
    })
})

/** End dot, shared across variants. */
const endDot = computed(() => {
    if (props.variant === 'bipolar') return bipolarData.value?.last ?? null
    if (props.variant === 'water') return waterData.value?.last ?? null
    return linePath.value?.last ?? null
})

/** Dot color: for bipolar, reflect the final value's side. */
const endDotColor = computed(() => {
    if (props.variant === 'bipolar' && bipolarData.value) {
        const last = svgPoints.value[svgPoints.value.length - 1]
        return last && last.v >= props.zeroValue ? props.colorPositive : props.colorNegative
    }
    if (props.variant === 'water') return props.color
    return props.color
})
</script>

<template>
    <svg
        :viewBox="`0 0 ${width} ${height}`"
        preserveAspectRatio="none"
        :style="{ width: '100%', height: height + 'px' }"
        role="img"
        :aria-label="ariaSummary"
    >
        <title>{{ ariaSummary }}</title>

        <!-- ── LINE variant ─────────────────────────────────────────── -->
        <template v-if="variant === 'line' && linePath">
            <path
                :d="linePath.line"
                fill="none"
                :stroke="color"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                opacity="0.85"
            />
        </template>

        <!-- ── AREA variant ──────────────────────────────────────────── -->
        <template v-else-if="variant === 'area' && linePath">
            <path :d="linePath.fill" :fill="color" opacity="0.08" />
            <path
                :d="linePath.line"
                fill="none"
                :stroke="color"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                opacity="0.85"
            />
        </template>

        <!-- ── BIPOLAR variant ───────────────────────────────────────── -->
        <template v-else-if="variant === 'bipolar' && bipolarData">
            <!-- Zero baseline -->
            <line
                x1="0"
                :y1="bipolarData.zeroY"
                :x2="width"
                :y2="bipolarData.zeroY"
                :stroke="color"
                stroke-width="0.75"
                stroke-dasharray="4 3"
                opacity="0.35"
            />
            <!-- Above-zero segments (positive color) -->
            <path
                v-for="(seg, i) in bipolarData.above"
                :key="'a' + i"
                :d="seg"
                fill="none"
                :stroke="colorPositive"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                opacity="0.85"
            />
            <!-- Below-zero segments (negative color) -->
            <path
                v-for="(seg, i) in bipolarData.below"
                :key="'b' + i"
                :d="seg"
                fill="none"
                :stroke="colorNegative"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                opacity="0.85"
            />
        </template>

        <!-- ── WATER variant ─────────────────────────────────────────── -->
        <template v-else-if="variant === 'water' && waterData">
            <path :d="waterData.fillPath" :fill="color" opacity="0.18" />
            <path
                :d="waterData.linePath"
                fill="none"
                :stroke="color"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                opacity="0.85"
            />
        </template>

        <!-- ── MARKERS (event bars, drawn on top) ───────────────────── -->
        <rect
            v-for="(bar, i) in markerBars"
            :key="'m' + i"
            :x="bar.x - 1"
            :y="bar.y"
            width="2"
            :height="bar.height"
            :fill="markerColor"
            opacity="0.55"
        />

        <!-- ── END DOT ───────────────────────────────────────────────── -->
        <circle
            v-if="endDot"
            :cx="endDot.x"
            :cy="endDot.y"
            r="3"
            :fill="endDotColor"
            stroke="var(--color-surface)"
            stroke-width="1.5"
        />
    </svg>
</template>

<style scoped>
@media (prefers-reduced-motion: reduce) {
    svg * {
        transition: none !important;
        animation: none !important;
    }
}
</style>
