<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import CompassRose from '@/Components/Admin/CompassRose.vue'
import WindDial from '@/Components/Admin/WindDial.vue'
import Sparkline from '@/Components/Admin/Sparkline.vue'
import LevelBar from '@/Components/Admin/LevelBar.vue'
import { fmt, fmtDuration } from '@/composables/useFormatters.js'

defineOptions({ layout: AdminLayout })

const props = defineProps({
    boat:            { type: Object, default: () => ({}) },
    gps:             { type: Object, default: () => ({}) },
    weather:         { type: Object, default: () => ({}) },
    depthHistory:    { type: Array,  default: () => [] },
    powerHistory:    { type: Array,  default: () => [] },
    pressureHistory: { type: Array,  default: () => [] },
    speedHistory:    { type: Array,  default: () => [] },
    timestamp:       { type: String, default: null },
})

// ── Live metrics (WebSocket overlay) ──────────────────────────────────────
const liveBoat    = ref(null)
const liveGps     = ref(null)
const liveWeather = ref(null)

let echoChannel = null
if (typeof window !== 'undefined' && window.Echo) {
    echoChannel = window.Echo.channel('metrics')
    echoChannel.listen('.metrics.updated', (data) => {
        liveBoat.value    = data.boat    ?? null
        liveGps.value     = data.gps     ?? null
        liveWeather.value = data.weather ?? null
    })
}

onUnmounted(() => {
    if (echoChannel) window.Echo?.leave('metrics')
})

const b = computed(() => liveBoat.value    ?? props.boat    ?? {})
const g = computed(() => liveGps.value     ?? props.gps     ?? {})
const w = computed(() => liveWeather.value ?? props.weather ?? {})

// ── History value arrays (Sparkline expects plain number arrays) ───────────
const depthData    = computed(() => (props.depthHistory    ?? []).map(d => d?.value ?? d))
const powerData    = computed(() => (props.powerHistory    ?? []).map(d => d?.value ?? d))
const pressureData = computed(() => (props.pressureHistory ?? []).map(d => d?.value ?? d))
const speedData    = computed(() => (props.speedHistory    ?? []).map(d => d?.value ?? d))

// ── Compass / heading ──────────────────────────────────────────────────────
const heading = computed(() => b.value.heading        ?? b.value.heading_true ?? 0)
const cog     = computed(() => b.value.cog            ?? null)

// ── Position ───────────────────────────────────────────────────────────────
function decToDegreesMinutes(decimal, posLabel, negLabel) {
    if (decimal == null || isNaN(decimal)) return null
    const hem  = decimal >= 0 ? posLabel : negLabel
    const abs  = Math.abs(decimal)
    const deg  = Math.floor(abs)
    const min  = ((abs - deg) * 60).toFixed(1)
    return `${deg}°${min}′${hem}`
}

const posLat = computed(() => decToDegreesMinutes(g.value.latitude,  'N', 'S'))
const posLon = computed(() => decToDegreesMinutes(g.value.longitude, 'E', 'W'))
const posText = computed(() => {
    if (!posLat.value || !posLon.value) return null
    return `${posLat.value} ${posLon.value}`
})

// ── Speed ──────────────────────────────────────────────────────────────────
const sog = computed(() => b.value.speed_sog)
const sow = computed(() => b.value.speed_sow)

const dtw = computed(() => b.value.nav_wp_distance ?? null)   // nm
const ttg = computed(() => b.value.nav_wp_ttg      ?? null)   // seconds
const eta = computed(() => {
    if (ttg.value == null || ttg.value <= 0) return null
    const d = new Date(Date.now() + ttg.value * 1000)
    return d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })
})
const tripLog = computed(() => b.value.trip_log ?? null)

// ── Wind (compute locally — same logic as WindDial internals) ──────────────
const tws = computed(() => b.value.wind_speed_true ?? 0)
const twa = computed(() => b.value.wind_angle_true ?? 0)

const absTwa = computed(() => Math.abs(twa.value))
const pointOfSail = computed(() => {
    const a = absTwa.value
    if (a < 45)  return 'In Irons'
    if (a < 60)  return 'Close Hauled'
    if (a < 80)  return 'Close Reach'
    if (a < 100) return 'Beam Reach'
    if (a < 150) return 'Broad Reach'
    if (a < 170) return 'Running'
    return 'Dead Run'
})
const beaufort = computed(() => {
    const s = tws.value
    if (s < 1)  return 'F0'
    if (s < 4)  return 'F1'
    if (s < 7)  return 'F2'
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

// ── Systems ────────────────────────────────────────────────────────────────
const batterySoc     = computed(() => b.value.house_battery_soc     ?? 0)
const fuelLevel      = computed(() => b.value.fuel_level            ?? 0)
const waterLevel     = computed(() => b.value.water_level           ?? 0)
const houseVoltage   = computed(() => b.value.house_battery_voltage ?? null)
const engineVoltage  = computed(() => b.value.engine_battery_voltage ?? null)

const livePower = computed(() => b.value.battery_power ?? null)
const livePowerLabel = computed(() => {
    if (livePower.value == null) return '—'
    const sign = livePower.value >= 0 ? '+' : ''
    return `${sign}${Math.round(livePower.value)} W`
})
const livePowerClass = computed(() =>
    livePower.value != null && livePower.value >= 0 ? 'text-green' : 'text-amber'
)

// ── Depth ──────────────────────────────────────────────────────────────────
const depth = computed(() => b.value.depth ?? null)

// ── Weather ────────────────────────────────────────────────────────────────
// weather object uses snake_case keys from controller
const wxTemp         = computed(() => w.value.temperature  ?? null)
const wxIcon         = computed(() => w.value.icon         ?? null)
const wxCondition    = computed(() => w.value.condition    ?? null)
const wxWindSpeed    = computed(() => w.value.wind_speed   ?? null)
const wxWindDir      = computed(() => w.value.wind_direction ?? null)
const wxWaveHeight   = computed(() => w.value.wave_height  ?? null)
const wxWavePeriod   = computed(() => w.value.wave_period  ?? null)
const wxCurrentSpeed = computed(() => w.value.current_speed ?? null)
const wxCurrentDir   = computed(() => w.value.current_direction ?? null)
const wxPressure     = computed(() => w.value.pressure     ?? null)
const wxSeaTemp      = computed(() => w.value.sea_temperature ?? null)

const wxSummary = computed(() => w.value.summary ?? 'na')
const wxGradient = computed(() => {
    const gradients = {
        'day-sunny':    'linear-gradient(135deg, oklch(0.72 0.14 80), oklch(0.65 0.16 55))',
        'night-clear':  'linear-gradient(135deg, oklch(0.22 0.06 260), oklch(0.18 0.04 240))',
        'cloud':        'linear-gradient(135deg, oklch(0.62 0.08 220), oklch(0.55 0.10 200))',
        'cloudy':       'linear-gradient(135deg, oklch(0.52 0.04 230), oklch(0.45 0.03 220))',
        'fog':          'linear-gradient(135deg, oklch(0.60 0.02 220), oklch(0.55 0.02 210))',
        'sprinkle':     'linear-gradient(135deg, oklch(0.48 0.06 230), oklch(0.42 0.06 240))',
        'rain':         'linear-gradient(135deg, oklch(0.40 0.06 235), oklch(0.34 0.06 245))',
        'snow':         'linear-gradient(135deg, oklch(0.68 0.02 230), oklch(0.62 0.02 220))',
        'showers':      'linear-gradient(135deg, oklch(0.46 0.06 230), oklch(0.40 0.06 240))',
        'thunderstorm': 'linear-gradient(135deg, oklch(0.30 0.08 270), oklch(0.24 0.06 260))',
    }
    return gradients[wxSummary.value] ?? 'linear-gradient(135deg, oklch(0.62 0.08 220), oklch(0.55 0.10 200))'
})
</script>

<template>
    <Head title="Skipper Overview" />

    <div class="skipper-grid">

        <!-- ═══════════════════════════════════════════════════════════════ -->
        <!-- PRIMARY ROW                                                      -->
        <!-- ═══════════════════════════════════════════════════════════════ -->

        <!-- 1. Heading & Course -->
        <div class="panel p-4 flex flex-col items-center gap-3">
            <div class="text-[11px] font-extrabold tracking-[2.5px] uppercase text-teal self-center">
                Heading &amp; Course
            </div>

            <CompassRose :heading="heading" :cog="cog" :size="165" />

            <div class="hdg-grid w-full">
                <div class="text-center">
                    <div class="text-xs font-medium text-text-dim mb-0.5">HDG</div>
                    <div class="font-sans text-base font-semibold tabular-nums">
                        {{ fmt(heading, 0) }}<span class="text-text-dim text-xs">&deg;</span>
                    </div>
                </div>
                <div class="hdg-divider"></div>
                <div class="text-center">
                    <div class="text-xs font-medium text-text-dim mb-0.5">COG</div>
                    <div class="font-sans text-base font-semibold tabular-nums">
                        {{ cog != null ? fmt(cog, 0) : '—' }}<span v-if="cog != null" class="text-text-dim text-xs">&deg;</span>
                    </div>
                </div>
            </div>

            <div class="text-xs font-medium text-text-dim text-center tabular-nums">
                {{ posText ?? '—' }}
            </div>
        </div>

        <!-- 2. Speed -->
        <div class="panel p-4 flex flex-col gap-2">
            <div class="text-[11px] font-extrabold tracking-[2.5px] uppercase text-teal">
                Speed
            </div>

            <!-- SOG hero -->
            <div class="flex items-baseline gap-1.5">
                <span class="font-sans text-[72px] font-bold tracking-tight leading-none text-teal tabular-nums">
                    {{ sog != null ? fmt(sog) : '—' }}
                </span>
                <span class="text-text-dim text-sm font-medium self-end mb-2">kn SOG</span>
            </div>

            <!-- SOW sub-line -->
            <div class="text-xs font-medium text-text-dim -mt-1">
                SOW&nbsp;<span class="font-sans text-base font-semibold text-text-primary tabular-nums">{{ sow != null ? fmt(sow) : '—' }}</span>&nbsp;kn
            </div>

            <!-- Speed sparkline -->
            <a :href="'/admin/explore?metric=scarlet_gps_speed_sog'" class="block mt-1">
                <Sparkline :data="speedData" color="var(--color-teal)" :height="36" :fill="true" :showDot="true" />
            </a>

            <div class="text-[10px] font-extrabold tracking-[2px] uppercase text-text-dim mt-0.5">Navigation</div>

            <!-- DTW / TTG / ETA rows -->
            <div class="space-y-1.5 text-[13px]">
                <div class="data-row">
                    <span>DTW</span>
                    <span class="tabular-nums">{{ dtw != null ? fmt(dtw) + ' nm' : '—' }}</span>
                </div>
                <div class="data-row">
                    <span>TTG</span>
                    <span class="tabular-nums">{{ ttg != null ? fmtDuration(ttg) : '—' }}</span>
                </div>
                <div class="data-row">
                    <span>ETA</span>
                    <span class="tabular-nums">{{ eta ?? '—' }}</span>
                </div>
            </div>

            <hr class="border-border-light my-1" />

            <div class="data-row text-[13px]">
                <span>Trip Distance</span>
                <span class="tabular-nums font-semibold">{{ tripLog != null ? fmt(tripLog) + ' nm' : '—' }}</span>
            </div>
        </div>

        <!-- 3. True Wind -->
        <div class="panel p-4 flex flex-col items-center gap-2">
            <div class="text-[11px] font-extrabold tracking-[2.5px] uppercase text-amber self-center">
                True Wind
            </div>

            <WindDial :tws="tws" :twa="twa" :size="175" />

            <div class="font-sans text-base font-semibold text-amber text-center">
                {{ pointOfSail }}
            </div>
            <div class="text-xs font-medium text-text-dim text-center">
                {{ beaufort }}
                <span class="text-text-dim"> &middot; </span>
                {{ fmt(tws, 1) }} kn
                <span v-if="twa !== 0">
                    &middot; {{ Math.abs(twa) }}&deg; {{ twa < 0 ? 'Port' : 'Stbd' }}
                </span>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════ -->
        <!-- SECONDARY ROW                                                    -->
        <!-- ═══════════════════════════════════════════════════════════════ -->

        <!-- 4. Systems -->
        <div class="panel p-4 flex flex-col gap-3">
            <div class="text-[11px] font-extrabold tracking-[2.5px] uppercase text-teal">
                Systems
            </div>

            <!-- Level bars -->
            <div class="space-y-2">
                <LevelBar :value="batterySoc" label="Battery" color="green" />
                <LevelBar :value="fuelLevel"  label="Fuel"    color="amber" />
                <LevelBar :value="waterLevel" label="Water"   color="blue"  />
            </div>

            <hr class="border-border-light" />

            <!-- Voltage rows -->
            <div class="space-y-1.5 text-[13px]">
                <div class="data-row">
                    <span>House Battery</span>
                    <span class="tabular-nums">{{ houseVoltage != null ? fmt(houseVoltage, 1) + 'V' : '—' }}</span>
                </div>
                <div class="data-row">
                    <span>Engine Battery</span>
                    <span class="tabular-nums">{{ engineVoltage != null ? fmt(engineVoltage, 1) + 'V' : '—' }}</span>
                </div>
            </div>
        </div>

        <!-- 5. Depth & Power -->
        <div class="panel p-4 flex flex-col gap-4">
            <div class="text-[11px] font-extrabold tracking-[2.5px] uppercase text-teal">
                Depth &amp; Power
            </div>

            <!-- Depth chart -->
            <div>
                <div class="flex items-baseline justify-between mb-1.5">
                    <span class="text-xs font-medium text-text-dim uppercase tracking-wide">Depth</span>
                    <span class="font-sans text-base font-semibold tabular-nums">
                        {{ depth != null ? fmt(depth, 1) + ' m' : '—' }}
                    </span>
                </div>
                <a :href="'/admin/explore?metric=scarlet_gps_depth'" class="block">
                    <Sparkline :data="depthData" color="var(--color-blue)" :height="44" :fill="true" :showDot="true" />
                </a>
            </div>

            <!-- Power chart -->
            <div>
                <div class="flex items-baseline justify-between mb-1.5">
                    <span class="text-xs font-medium text-text-dim uppercase tracking-wide">Battery Power</span>
                    <span class="font-sans text-base font-semibold tabular-nums" :class="livePowerClass">
                        {{ livePowerLabel }}
                    </span>
                </div>
                <a :href="'/admin/explore?metric=scarlet_boat_battery_power'" class="block">
                    <Sparkline :data="powerData" color="var(--color-green)" :height="44" :fill="true" :showDot="true" :zeroLine="true" />
                </a>
            </div>
        </div>

        <!-- 6. Weather -->
        <div class="panel flex flex-col">
            <!-- Gradient hero strip — flush with top, no padding -->
            <div class="wx-hero" :style="{ background: wxGradient }">
                <div>
                    <div class="font-sans text-[48px] font-bold tracking-tight leading-none text-white tabular-nums">
                        {{ wxTemp != null ? fmt(wxTemp, 1) + '°' : '—' }}
                    </div>
                    <div class="text-sm font-medium text-white/80 mt-1">{{ wxCondition ?? '' }}</div>
                </div>
                <div class="text-[40px] leading-none" aria-hidden="true">{{ wxIcon ?? '' }}</div>
            </div>

            <!-- Data rows -->
            <div class="px-4 py-3 flex flex-col gap-3 flex-1">
                <div class="space-y-1.5 text-[13px]">
                    <!-- Wind -->
                    <div class="data-row">
                        <span>Wind</span>
                        <span class="tabular-nums">
                            {{ wxWindSpeed != null ? fmt(wxWindSpeed, 0) + ' kn' : '—' }}
                            {{ wxWindDir ? ' ' + wxWindDir : '' }}
                        </span>
                    </div>
                    <!-- Waves -->
                    <div class="data-row">
                        <span>Waves</span>
                        <span class="tabular-nums">
                            {{ wxWaveHeight != null ? fmt(wxWaveHeight, 1) + ' m' : '—' }}
                            {{ wxWavePeriod != null ? ' / ' + fmt(wxWavePeriod, 0) + ' s' : '' }}
                        </span>
                    </div>
                    <!-- Current -->
                    <div class="data-row">
                        <span>Current</span>
                        <span class="tabular-nums">
                            {{ wxCurrentSpeed != null ? fmt(wxCurrentSpeed, 1) + ' kn' : '—' }}
                            {{ wxCurrentDir != null ? ' @ ' + fmt(wxCurrentDir, 0) + '°' : '' }}
                        </span>
                    </div>
                    <!-- Sea temp -->
                    <div class="data-row">
                        <span>Sea Temp</span>
                        <span class="tabular-nums">{{ wxSeaTemp != null ? fmt(wxSeaTemp, 1) + '°C' : '—' }}</span>
                    </div>
                </div>

                <hr class="border-border-light" />

                <!-- Pressure sparkline -->
                <div>
                    <div class="flex items-baseline justify-between mb-1.5">
                        <span class="text-xs font-medium text-text-dim uppercase tracking-wide">Pressure</span>
                        <span class="font-sans text-sm font-semibold tabular-nums">
                            {{ wxPressure != null ? fmt(wxPressure, 0) + ' hPa' : '—' }}
                        </span>
                    </div>
                    <a :href="'/admin/explore?metric=scarlet_boat_pressure'" class="block">
                        <Sparkline :data="pressureData" color="var(--color-teal)" :height="32" :fill="true" :showDot="true" />
                    </a>
                </div>
            </div>
        </div>

    </div>
</template>

<style scoped>
.skipper-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 16px;
}

@media (min-width: 900px) {
    .skipper-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

/* HDG / COG two-cell grid with centre divider */
.hdg-grid {
    display: grid;
    grid-template-columns: 1fr 1px 1fr;
    align-items: center;
}

.hdg-divider {
    background: var(--color-border-light);
    height: 28px;
    width: 1px;
}

/* Data row (label + value) */
.data-row {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
}

.data-row > span:first-child {
    color: var(--color-text-secondary);
}

.data-row > span:last-child {
    font-weight: 600;
    font-variant-numeric: tabular-nums;
}

/* Weather hero strip */
.wx-hero {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px 20px 18px;
}
</style>
