<template>
    <AdminLayout>
        <Head title="Environment" />
        <h1 class="font-sans text-2xl font-extrabold tracking-tight mb-6">Environment</h1>

        <!-- Weather Hero -->
        <div class="panel overflow-hidden mb-4">
            <div v-if="weather" class="wx-hero" :style="{ background: wxGradient }">
                <div class="wx-hero__main">
                    <div class="wx-hero__temp">
                        {{ wxTemp }}<span class="wx-hero__unit">°C</span>
                    </div>
                    <div class="wx-hero__cond">{{ wxCondition }}</div>
                </div>
                <div class="wx-hero__icon" aria-hidden="true">{{ wxIcon }}</div>
            </div>
            <div v-if="weather" class="wx-body">
                <div class="wx-stats">
                    <div class="wx-stat">
                        <span class="wx-stat__label">Wind</span>
                        <span class="wx-stat__value">{{ wxWind }}</span>
                    </div>
                    <div class="wx-stat">
                        <span class="wx-stat__label">Pressure</span>
                        <span class="wx-stat__value">{{ wxPressure }}</span>
                    </div>
                    <div class="wx-stat">
                        <span class="wx-stat__label">Sea</span>
                        <span class="wx-stat__value">{{ wxSeaTemp }}</span>
                    </div>
                </div>
                <div v-if="weather.forecast?.length" class="wx-forecast">
                    <div v-for="slot in weather.forecast.slice(0, 8)" :key="slot.time" class="wx-fc-slot">
                        <span class="wx-fc-time">{{ formatHour(slot.time) }}</span>
                        <span class="wx-fc-icon" :title="wmoLabel(slot.code)">{{ wmoIcon(slot.code) }}</span>
                        <span class="wx-fc-temp">{{ Math.round(slot.temp) }}°</span>
                        <span class="wx-fc-wind" :title="slot.gusts != null ? `Gusts ${Math.round(slot.gusts)} kn` : ''">{{ slot.wind != null ? Math.round(slot.wind) : '—' }}<span class="wx-fc-wind-unit">kn</span></span>
                        <span v-if="slot.precip > 0" class="wx-fc-rain">{{ slot.precip.toFixed(1) }}mm</span>
                    </div>
                </div>
            </div>
            <div v-else class="p-4 text-center text-[13px] text-text-secondary">
                Weather data unavailable
            </div>
        </div>

        <!-- Cabin Gauges -->
        <div class="gauges-grid mb-4">
            <TemperatureGauge
                label="Forepeak"
                :temperature="cabinTemp('forepeak')"
                :humidity="cabinHumidity('forepeak')"
                color="var(--color-amber)"
                :selected="selectedZone === 'forepeak'"
                :sparkline="sparklines.forepeak"
                @select="selectZone('forepeak')"
            />
            <TemperatureGauge
                label="Main Cabin"
                :temperature="cabinTemp('main')"
                :humidity="cabinHumidity('main')"
                color="var(--color-scarlet)"
                :selected="selectedZone === 'main'"
                :sparkline="sparklines.main"
                @select="selectZone('main')"
            />
            <TemperatureGauge
                label="Quarterberth"
                :temperature="cabinTemp('quarterberth')"
                :humidity="cabinHumidity('quarterberth')"
                color="var(--color-green)"
                :selected="selectedZone === 'quarterberth'"
                :sparkline="sparklines.quarterberth"
                @select="selectZone('quarterberth')"
            />
        </div>

        <!-- Sea -->
        <button
            class="sea-card mb-4"
            :class="{ 'sea-card--selected': selectedZone === 'sea' }"
            @click="selectZone('sea')"
        >
            <div class="sea-card__header">
                <span class="sea-card__label">Sea State</span>
                <span class="sea-card__temp">{{ seaTemp }}</span>
            </div>
            <div v-if="hasWaves" class="sea-card__waves">
                <div class="sea-card__wave-item">
                    <span class="sea-card__wave-value">{{ wxWaveHeight }}</span>
                    <span class="sea-card__wave-label">Height</span>
                </div>
                <div class="sea-card__wave-divider"></div>
                <div class="sea-card__wave-item">
                    <span class="sea-card__wave-value">{{ wxWavePeriod }}</span>
                    <span class="sea-card__wave-label">Period</span>
                </div>
                <div class="sea-card__wave-divider"></div>
                <div class="sea-card__wave-item">
                    <span class="sea-card__wave-value">{{ wxWaveDir }}</span>
                    <span class="sea-card__wave-label">Direction</span>
                </div>
                <div v-if="wxCurrentSpeed !== '—'" class="sea-card__wave-divider"></div>
                <div v-if="wxCurrentSpeed !== '—'" class="sea-card__wave-item">
                    <span class="sea-card__wave-value">{{ wxCurrentSpeed }}</span>
                    <span class="sea-card__wave-label">Current</span>
                </div>
            </div>
            <div v-else class="sea-card__calm">
                <span>Calm seas</span>
            </div>
            <Sparkline
                v-if="sparklines.sea?.length"
                :data="sparklines.sea"
                color="var(--color-blue)"
                :height="28"
                :fill="true"
                :showDot="true"
                class="sea-card__spark"
            />
        </button>

        <!-- Detail Drawer -->
        <Transition name="drawer">
            <div v-if="selectedZone" class="panel detail-drawer">
                <div class="detail-drawer__head">
                    <span class="detail-drawer__title">{{ zoneLabel }}</span>
                    <div class="detail-drawer__pills">
                        <button
                            v-for="r in ranges"
                            :key="r"
                            class="pill"
                            :class="{ 'pill--active': selectedRange === r }"
                            @click="selectRange(r)"
                        >{{ r }}</button>
                    </div>
                </div>
                <div v-if="chartLoading" class="detail-drawer__loading">
                    <div class="skeleton skeleton--chart"></div>
                    <div class="skeleton skeleton--chart"></div>
                </div>
                <div v-else class="detail-drawer__charts">
                    <div v-for="chart in activeCharts" :key="chart.key" class="chart-section">
                        <div class="chart-section__head">
                            <span class="chart-section__label">{{ chart.label }}</span>
                            <span class="chart-section__value" :style="{ color: chart.color }">
                                {{ chart.current }} {{ chart.unit }}
                            </span>
                        </div>
                        <Sparkline
                            :data="chart.data"
                            :color="chart.color"
                            :height="140"
                            :fill="true"
                            :showDot="true"
                        />
                    </div>
                </div>
                <div class="detail-drawer__explore">
                    <a :href="exploreLink" class="explore-link">Explore →</a>
                </div>
            </div>
        </Transition>

        <p v-if="timestamp" class="text-[11px] text-text-dim mt-3 tabular-nums">Updated {{ timestamp }}</p>
    </AdminLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import TemperatureGauge from '@/Components/Admin/TemperatureGauge.vue'
import Sparkline from '@/Components/Admin/Sparkline.vue'
import { fmt } from '@/composables/useFormatters.js'

const props = defineProps({
    boat: Object,
    weather: Object,
    timestamp: String,
})

const selectedZone = ref(null)
const selectedRange = ref('24h')
const ranges = ['6h', '24h', '7d', '30d']
const chartLoading = ref(false)
const chartData = ref({})
const sparklines = ref({
    forepeak: [],
    main: [],
    quarterberth: [],
    sea: [],
})

function cabinTemp(zone) {
    const val = props.boat?.[`cabin_temp_${zone}`]
    return val != null ? Number(val) : null
}

function cabinHumidity(zone) {
    const val = props.boat?.[`cabin_humidity_${zone}`]
    return val != null ? Number(val) : null
}

const seaTemp = computed(() => {
    const val = props.boat?.water_temp
    return val != null ? `${Number(val).toFixed(1)}°C` : '—'
})

const wxGradients = {
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

const wxSummary = computed(() => props.weather?.summary ?? 'cloud')

const wxGradient = computed(() => wxGradients[wxSummary.value] ?? wxGradients.cloud)

const wxTemp = computed(() => props.weather?.temp != null ? Number(props.weather.temp).toFixed(1) : '—')

const wxCondition = computed(() => props.weather?.conditionText ?? 'Unknown')

const wxIcon = computed(() => {
    const icons = {
        'day-sunny': '☀️', 'night-clear': '🌙', 'cloud': '⛅', 'cloudy': '☁️',
        'fog': '🌫️', 'sprinkle': '🌦️', 'rain': '🌧️', 'snow': '❄️',
        'showers': '🌧️', 'thunderstorm': '⛈️',
    }
    return icons[wxSummary.value] ?? '☁️'
})

const wxWind = computed(() => {
    const w = props.weather?.wind
    if (!w?.speed) return '—'
    const dir = degreesToCompass(w.direction)
    return `${Math.round(w.speed)} kts ${dir}`
})

const wxPressure = computed(() => {
    return props.weather?.pressure != null ? `${Math.round(props.weather.pressure)} hPa` : '—'
})

const wxSeaTemp = computed(() => {
    return props.weather?.seaTemp != null ? `${Number(props.weather.seaTemp).toFixed(1)}°C` : '—'
})

const wxWaveHeight = computed(() => {
    return props.weather?.waves?.height != null ? `${Number(props.weather.waves.height).toFixed(1)}m` : '—'
})

const wxWavePeriod = computed(() => {
    return props.weather?.waves?.period != null ? `${Math.round(props.weather.waves.period)}s` : '—'
})

const wxWaveDir = computed(() => {
    return props.weather?.waves?.direction != null ? degreesToCompass(props.weather.waves.direction) : '—'
})

const hasWaves = computed(() => {
    const h = props.weather?.waves?.height
    return h != null && h > 0
})

const wxCurrentSpeed = computed(() => {
    return props.weather?.current?.speed != null ? `${Number(props.weather.current.speed).toFixed(1)} kn` : '—'
})

const wmoIcons = {
    0: '☀️', 1: '🌤️', 2: '⛅', 3: '☁️',
    45: '🌫️', 48: '🌫️',
    51: '🌦️', 53: '🌦️', 55: '🌧️',
    56: '🌧️', 57: '🌧️',
    61: '🌦️', 63: '🌧️', 65: '🌧️',
    66: '🌧️', 67: '🌧️',
    71: '🌨️', 73: '🌨️', 75: '❄️',
    77: '❄️',
    80: '🌦️', 81: '🌧️', 82: '🌧️',
    85: '🌨️', 86: '🌨️',
    95: '⛈️', 96: '⛈️', 99: '⛈️',
}

const wmoLabels = {
    0: 'Clear', 1: 'Mostly clear', 2: 'Partly cloudy', 3: 'Overcast',
    45: 'Fog', 48: 'Rime fog',
    51: 'Light drizzle', 53: 'Drizzle', 55: 'Heavy drizzle',
    56: 'Freezing drizzle', 57: 'Heavy freezing drizzle',
    61: 'Light rain', 63: 'Rain', 65: 'Heavy rain',
    66: 'Freezing rain', 67: 'Heavy freezing rain',
    71: 'Light snow', 73: 'Snow', 75: 'Heavy snow',
    77: 'Snow grains',
    80: 'Light showers', 81: 'Showers', 82: 'Heavy showers',
    85: 'Light snow showers', 86: 'Heavy snow showers',
    95: 'Thunderstorm', 96: 'Thunderstorm w/ hail', 99: 'Severe thunderstorm',
}

function wmoIcon(code) {
    return wmoIcons[code] ?? '☁️'
}

function wmoLabel(code) {
    return wmoLabels[code] ?? 'Unknown'
}

function degreesToCompass(deg) {
    if (deg == null) return '—'
    const dirs = ['N','NNE','NE','ENE','E','ESE','SE','SSE','S','SSW','SW','WSW','W','WNW','NW','NNW']
    return dirs[Math.round(((deg % 360) + 360) % 360 / 22.5) % 16]
}

function formatHour(timeStr) {
    if (!timeStr) return ''
    const d = new Date(timeStr)
    return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: false })
}

const zoneConfig = {
    forepeak: {
        label: 'Forepeak',
        metrics: ['temp_forepeak', 'humidity_forepeak', 'pressure_forepeak'],
        charts: [
            { key: 'temp', metric: 'temp_forepeak', label: 'Temperature', unit: '°C', color: 'var(--color-amber)' },
            { key: 'hum', metric: 'humidity_forepeak', label: 'Humidity', unit: '%', color: 'var(--color-blue)' },
            { key: 'pres', metric: 'pressure_forepeak', label: 'Pressure', unit: 'hPa', color: 'var(--color-teal)' },
        ],
    },
    main: {
        label: 'Main Cabin',
        metrics: ['temp_main_cabin', 'humidity_main_cabin'],
        charts: [
            { key: 'temp', metric: 'temp_main_cabin', label: 'Temperature', unit: '°C', color: 'var(--color-scarlet)' },
            { key: 'hum', metric: 'humidity_main_cabin', label: 'Humidity', unit: '%', color: 'var(--color-blue)' },
        ],
    },
    quarterberth: {
        label: 'Quarterberth',
        metrics: ['temp_quarterberth', 'humidity_quarterberth'],
        charts: [
            { key: 'temp', metric: 'temp_quarterberth', label: 'Temperature', unit: '°C', color: 'var(--color-green)' },
            { key: 'hum', metric: 'humidity_quarterberth', label: 'Humidity', unit: '%', color: 'var(--color-blue)' },
        ],
    },
    sea: {
        label: 'Sea',
        metrics: ['water_temp'],
        charts: [
            { key: 'temp', metric: 'water_temp', label: 'Temperature', unit: '°C', color: 'var(--color-blue)' },
        ],
    },
}

const zoneLabel = computed(() => zoneConfig[selectedZone.value]?.label ?? '')

const activeCharts = computed(() => {
    const zone = zoneConfig[selectedZone.value]
    if (!zone) return []
    return zone.charts.map(c => {
        const series = chartData.value[c.metric]
        const values = series?.data?.map(d => d.value) ?? []
        const current = series?.current != null ? fmt(series.current, c.unit === 'hPa' ? 0 : 1) : '—'
        return { ...c, data: values, current }
    })
})

const exploreLink = computed(() => {
    const zone = zoneConfig[selectedZone.value]
    if (!zone) return '/admin/explore'
    return `/admin/explore?metric=${zone.metrics[0]}&range=${selectedRange.value}`
})

async function fetchChartData() {
    const zone = zoneConfig[selectedZone.value]
    if (!zone) return

    chartLoading.value = true
    try {
        const metrics = zone.metrics.join(',')
        const res = await fetch(`/admin/environment/series?metrics=${metrics}&range=${selectedRange.value}`)
        if (res.ok) {
            chartData.value = await res.json()
        }
    } catch {
        chartData.value = {}
    } finally {
        chartLoading.value = false
    }
}

async function fetchSparklines() {
    const metrics = [
        'temp_forepeak', 'temp_main_cabin', 'temp_quarterberth',
    ].join(',')

    try {
        const res = await fetch(`/admin/environment/series?metrics=${metrics}&range=24h`)
        if (res.ok) {
            const data = await res.json()
            sparklines.value.forepeak = data.temp_forepeak?.data?.map(d => d.value) ?? []
            sparklines.value.main = data.temp_main_cabin?.data?.map(d => d.value) ?? []
            sparklines.value.quarterberth = data.temp_quarterberth?.data?.map(d => d.value) ?? []
        }
    } catch {
        // Sparklines are non-critical
    }
}

function selectZone(zone) {
    if (selectedZone.value === zone) {
        selectedZone.value = null
        return
    }
    selectedZone.value = zone
}

function selectRange(range) {
    selectedRange.value = range
}

watch([selectedZone, selectedRange], () => {
    if (selectedZone.value) {
        fetchChartData()
    }
})

fetchSparklines()
</script>

<style scoped>
/* Gauges grid */
.gauges-grid {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 16px;
}

/* Weather hero */
.wx-hero {
    padding: 20px 24px;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    border-radius: 15px 15px 0 0;
    position: relative;
    overflow: hidden;
}

.wx-hero::after {
    content: '';
    position: absolute;
    right: 60px;
    top: -10px;
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: oklch(0.90 0.10 80 / 0.25);
    filter: blur(24px);
    pointer-events: none;
}

.wx-hero__temp {
    font-family: var(--font-sans);
    font-size: 44px;
    font-weight: 700;
    letter-spacing: -1.5px;
    line-height: 1;
    color: white;
    font-variant-numeric: tabular-nums;
}

.wx-hero__unit {
    font-size: 18px;
    font-weight: 400;
    opacity: 0.7;
}

.wx-hero__cond {
    font-family: var(--font-body);
    font-size: 14px;
    font-weight: 500;
    color: oklch(1 0 0 / 0.8);
    margin-top: 4px;
}

.wx-hero__icon {
    font-size: 36px;
    line-height: 1;
    position: relative;
}

.wx-body {
    padding: 12px 24px 16px;
}

.wx-stats {
    display: flex;
    gap: 24px;
    margin-bottom: 10px;
}

.wx-stat {
    display: flex;
    align-items: baseline;
    gap: 6px;
    font-size: 12px;
}

.wx-stat__label {
    font-family: var(--font-body);
    color: var(--color-text-dim);
}

.wx-stat__value {
    font-family: var(--font-sans);
    font-weight: 600;
    font-variant-numeric: tabular-nums;
    color: var(--color-text-secondary);
}

.wx-forecast {
    display: flex;
    gap: 6px;
    margin-top: 10px;
}

.wx-fc-slot {
    flex: 1;
    text-align: center;
    background: var(--color-bg);
    border-radius: 8px;
    padding: 8px 4px 6px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2px;
}

.wx-fc-time {
    font-family: var(--font-body);
    font-size: 10px;
    font-weight: 700;
    color: var(--color-text-dim);
    letter-spacing: 0.5px;
    display: block;
}

.wx-fc-icon {
    font-size: 16px;
    line-height: 1;
}

.wx-fc-temp {
    font-family: var(--font-sans);
    font-size: 14px;
    font-weight: 600;
    color: var(--color-text-secondary);
}

.wx-fc-wind {
    font-family: var(--font-sans);
    font-size: 11px;
    font-weight: 600;
    color: var(--color-text-dim);
    font-variant-numeric: tabular-nums;
}

.wx-fc-wind-unit {
    font-size: 9px;
    font-weight: 500;
    opacity: 0.7;
    margin-left: 1px;
}

.wx-fc-rain {
    font-family: var(--font-sans);
    font-size: 10px;
    font-weight: 600;
    color: var(--color-blue);
    background: oklch(0.65 0.12 240 / 0.1);
    border-radius: 3px;
    padding: 1px 4px;
}

/* Sea card */
.sea-card {
    display: flex;
    flex-direction: column;
    gap: 12px;
    width: 100%;
    background: linear-gradient(135deg, oklch(0.97 0.02 220), oklch(0.95 0.03 210));
    border: 1px solid oklch(0.80 0.06 220 / 0.3);
    border-radius: 14px;
    padding: 18px 22px;
    cursor: pointer;
    transition: border-color 0.15s, box-shadow 0.15s;
    -webkit-appearance: none;
    appearance: none;
    font-family: inherit;
    color: inherit;
    text-align: left;
}

.sea-card:hover {
    border-color: oklch(0.65 0.10 220 / 0.4);
    box-shadow: 0 2px 12px oklch(0.50 0.10 220 / 0.08);
}

.sea-card:focus-visible {
    outline: 2px solid var(--color-scarlet);
    outline-offset: 2px;
}

.sea-card--selected {
    border-color: oklch(0.48 0.22 25 / 0.3);
    background: linear-gradient(135deg, oklch(0.97 0.01 25), oklch(0.95 0.02 30));
}

.sea-card--selected .sea-card__label {
    color: var(--color-scarlet);
}

.sea-card__header {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
}

.sea-card__label {
    font-family: var(--font-body);
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 2px;
    text-transform: uppercase;
    color: var(--color-blue);
    transition: color 0.15s;
}

.sea-card__temp {
    font-family: var(--font-sans);
    font-size: 26px;
    font-weight: 700;
    color: var(--color-blue);
    letter-spacing: -0.5px;
    font-variant-numeric: tabular-nums;
}

.sea-card__waves {
    display: flex;
    align-items: center;
    gap: 0;
}

.sea-card__wave-item {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2px;
}

.sea-card__wave-value {
    font-family: var(--font-sans);
    font-size: 16px;
    font-weight: 700;
    color: var(--color-text-primary);
    font-variant-numeric: tabular-nums;
}

.sea-card__wave-label {
    font-family: var(--font-body);
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--color-text-dim);
}

.sea-card__wave-divider {
    width: 1px;
    height: 28px;
    background: oklch(0.70 0.06 220 / 0.25);
    flex-shrink: 0;
}

.sea-card__calm {
    font-family: var(--font-body);
    font-size: 12px;
    font-weight: 500;
    color: var(--color-text-dim);
    font-style: italic;
}

.sea-card__spark {
    margin-top: 2px;
}

/* Detail drawer */
.detail-drawer {
    padding: 20px 24px;
}

.detail-drawer__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
}

.detail-drawer__title {
    font-family: var(--font-body);
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 2.5px;
    text-transform: uppercase;
    color: var(--color-scarlet);
}

.detail-drawer__pills {
    display: flex;
    gap: 4px;
}

.pill {
    font-family: var(--font-body);
    font-size: 11px;
    font-weight: 700;
    padding: 5px 14px;
    border-radius: 6px;
    border: 1px solid var(--color-border);
    background: transparent;
    color: var(--color-text-dim);
    cursor: pointer;
    transition: all 0.15s;
}

.pill:hover {
    border-color: oklch(0.78 0.01 205);
    color: var(--color-text-secondary);
}

.pill:focus-visible {
    outline: 2px solid var(--color-scarlet);
    outline-offset: 1px;
}

.pill--active {
    background: var(--color-scarlet);
    border-color: var(--color-scarlet);
    color: white;
}

.detail-drawer__charts {
    display: flex;
    gap: 20px;
}

.chart-section {
    flex: 1;
    min-width: 0;
}

.chart-section__head {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    margin-bottom: 8px;
}

.chart-section__label {
    font-family: var(--font-body);
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 2px;
    text-transform: uppercase;
    color: var(--color-text-dim);
}

.chart-section__value {
    font-family: var(--font-sans);
    font-size: 14px;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
}

.detail-drawer__explore {
    margin-top: 16px;
    text-align: right;
}

.explore-link {
    font-family: var(--font-body);
    font-size: 11px;
    font-weight: 700;
    color: var(--color-teal);
    text-decoration: none;
}

.explore-link:hover {
    text-decoration: underline;
}

/* Loading skeleton */
.detail-drawer__loading {
    display: flex;
    gap: 20px;
}

/* Drawer transition */
.drawer-enter-active {
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
.drawer-leave-active {
    transition: all 0.2s cubic-bezier(0.7, 0, 0.84, 0);
}
.drawer-enter-from {
    opacity: 0;
    transform: translateY(-8px);
}
.drawer-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}

/* Responsive */
@media (max-width: 768px) {
    .gauges-grid {
        grid-template-columns: 1fr;
        gap: 12px;
    }

    .wx-stats {
        flex-wrap: wrap;
        gap: 12px;
    }

    .sea-card__waves {
        flex-wrap: wrap;
        gap: 8px;
    }

    .sea-card__wave-divider {
        display: none;
    }

    .detail-drawer__charts {
        flex-direction: column;
        gap: 16px;
    }
}
</style>
