<script setup>
import { computed } from 'vue';
import { useSpringValue } from '../../composables/useSpringValue.js';
import { fmtCoord } from '../../lcars/format.js';

const props = defineProps({
    lat: { type: Number, default: null },
    lon: { type: Number, default: null },
    heading: { type: Number, default: null },
    cog: { type: Number, default: null },
    satellites: { type: Number, default: null },
    hdop: { type: Number, default: null },
});

const hdgSpring = useSpringValue(() => props.heading ?? 0, { tension: 80, friction: 14 });
const cogSpring = useSpringValue(() => props.cog ?? 0, { tension: 80, friction: 14 });

const fixQuality = computed(() => {
    if (props.satellites == null) return { label: 'NO FIX', color: 'var(--red)' };
    if (props.satellites >= 8 && (props.hdop ?? 9) < 2) return { label: '3D FIX', color: 'var(--orange)' };
    if (props.satellites >= 4) return { label: '2D FIX', color: 'var(--amber)' };
    return { label: 'NO FIX', color: 'var(--red)' };
});
const satDots = computed(() => Array.from({ length: 12 }, (_, i) => i < (props.satellites ?? 0)));

function pol(a, r) { const rad = (a - 90) * Math.PI / 180; return [50 + r * Math.cos(rad), 50 + r * Math.sin(rad)]; }
const cardinals = [['N', 0], ['E', 90], ['S', 180], ['W', 270]];

// Swept heading arc (N → bearing) + a rim marker — LCARS grammar, no center pivot.
const R = 40;
const hdgArc = computed(() => {
    const t = Math.max(0.001, Math.min(359.999, hdgSpring.value ?? 0));
    const [sx, sy] = pol(0, R);
    const [ex, ey] = pol(t, R);
    return `M${sx.toFixed(2)},${sy.toFixed(2)} A${R},${R} 0 ${t > 180 ? 1 : 0} 1 ${ex.toFixed(2)},${ey.toFixed(2)}`;
});
const hdgMark = computed(() => {
    const tip = pol(hdgSpring.value ?? 0, R + 3);
    const l = pol((hdgSpring.value ?? 0) - 5, R - 1);
    const r = pol((hdgSpring.value ?? 0) + 5, R - 1);
    return `${l[0]},${l[1]} ${tip[0]},${tip[1]} ${r[0]},${r[1]}`;
});
const cogMark = computed(() => pol(cogSpring.value ?? 0, R));
</script>

<template>
    <div class="lcars-navfix">
        <div class="rose">
            <svg viewBox="0 0 100 100">
                <circle cx="50" cy="50" r="46" fill="none" stroke="var(--panel-2)" stroke-width="5" />
                <line v-for="d in 36" :key="d" :x1="pol(d * 10, 42)[0]" :y1="pol(d * 10, 42)[1]"
                    :x2="pol(d * 10, 46)[0]" :y2="pol(d * 10, 46)[1]" stroke="var(--mauve)" :stroke-width="d % 9 === 1 ? 1.4 : 0.6" opacity="0.6" />
                <text v-for="[c, a] in cardinals" :key="c" :x="pol(a, 34)[0]" :y="pol(a, 34)[1] + 3" text-anchor="middle" font-size="9"
                    :fill="c === 'N' ? 'var(--orange)' : 'var(--mauve)'">{{ c }}</text>
                <!-- COG rim marker (dashed bug) -->
                <circle v-if="cog != null" :cx="cogMark[0]" :cy="cogMark[1]" r="2.6" fill="none" stroke="var(--blue)" stroke-width="1.6" />
                <!-- Heading: swept arc from N + rim marker (no center pivot) -->
                <path v-if="heading != null" :d="hdgArc" fill="none" stroke="var(--orange)" stroke-width="4" stroke-linecap="round" opacity="0.9" />
                <polygon v-if="heading != null" :points="hdgMark" fill="var(--orange)" />
            </svg>
            <div class="hdg lcars-num">{{ heading == null ? '---' : Math.round(heading) }}°</div>
        </div>

        <div class="info">
            <div class="coords lcars-num">
                <span>{{ fmtCoord(lat, true) }}</span>
                <span>{{ fmtCoord(lon, false) }}</span>
            </div>
            <div class="fix" :style="{ color: fixQuality.color }">{{ fixQuality.label }}</div>
            <div class="sats">
                <span v-for="(on, i) in satDots" :key="i" class="dot" :class="{ on }"></span>
                <span class="cnt lcars-num">{{ satellites == null ? '--' : satellites }} SAT</span>
            </div>
            <div class="hdop"><span class="k">HDOP</span><span class="v lcars-num">{{ hdop == null ? '--' : hdop.toFixed(1) }}</span></div>
        </div>
    </div>
</template>

<style scoped>
.lcars-navfix { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.3fr); gap: 14px; align-items: center; height: 100%; }
.rose { display: flex; flex-direction: column; align-items: center; min-width: 0; }
.rose svg { width: 100%; max-height: 180px; }
.rose .hdg { font-size: 26px; font-weight: 700; color: var(--orange); margin-top: -4px; }
.info { display: flex; flex-direction: column; gap: 10px; }
.coords { display: flex; flex-direction: column; font-size: 30px; font-weight: 700; color: var(--peach); line-height: 1.1; }
.fix { font-size: 18px; font-weight: 700; }
.sats { display: flex; align-items: center; gap: 4px; flex-wrap: wrap; }
.sats .dot { width: 9px; height: 9px; border-radius: 50%; background: var(--panel-2); }
.sats .dot.on { background: var(--orange); }
.sats .cnt { font-size: 14px; color: var(--mauve); margin-left: 6px; }
.hdop { display: flex; gap: 10px; align-items: baseline; }
.hdop .k { font-size: 13px; color: var(--mauve); }
.hdop .v { font-size: 20px; color: var(--peach); font-weight: 700; }
</style>
