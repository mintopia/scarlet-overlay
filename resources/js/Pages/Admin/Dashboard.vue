<template>
    <AdminLayout :wide="true">
    <Head title="Dashboard" />

    <!-- Status Ribbon -->
    <div class="flex items-center justify-between p-3 px-6 bg-surface border border-border rounded-[14px] mb-5 shadow-sm">
        <div class="flex items-center gap-4">
            <span class="sailing-badge">{{ statusText }}</span>
            <span class="text-sm text-text-secondary">
                <span class="text-scarlet font-extrabold">Scarlet</span>
                <template v-if="props.activeJourney">
                    &nbsp;·&nbsp;{{ props.activeJourney.title }}
                    <span class="text-text-dim">&nbsp;·&nbsp;{{ fmtDuration(props.activeJourney.duration) }}&nbsp;·&nbsp;{{ props.activeJourney.distance }} nm</span>
                </template>
                <template v-else-if="props.plannedJourney">
                    &nbsp;·&nbsp;{{ props.plannedJourney.from_port }} → {{ props.plannedJourney.to_port }}
                    <span class="ml-2 text-[10px] font-semibold tracking-wide px-2 py-0.5 rounded bg-amber-100 text-amber-700">PLANNED</span>
                </template>
                <template v-else>
                    &nbsp;·&nbsp;<span class="text-text-dim">No active journey</span>
                </template>
            </span>
        </div>
        <div class="flex gap-3 items-center">
            <div class="flex items-center gap-1.5">
                <span :class="['health-dot', props.tracker?.battery_percent > 0 ? 'health-dot--green' : 'health-dot--red']"></span>
                <span class="text-[11px] text-text-dim font-medium">Tracker</span>
            </div>
            <div class="flex items-center gap-1.5">
                <span :class="['health-dot', props.streamOnline ? 'health-dot--green' : 'health-dot--red']"></span>
                <span class="text-[11px] text-text-dim font-medium">Stream</span>
            </div>
            <div class="flex items-center gap-1.5">
                <span :class="['health-dot', props.streamPublisher ? 'health-dot--green' : 'health-dot--amber']"></span>
                <span class="text-[11px] text-text-dim font-medium">Publisher</span>
            </div>
        </div>
    </div>

    <!-- Helm Section -->
    <div class="helm-grid panel p-5 mb-5">
        <!-- Left instruments: SOG, SOW, HDG, Depth -->
        <div class="helm-instruments helm-instruments--left">
            <div class="instrument">
                <span class="instrument-label">SOG</span>
                <span class="instrument-value text-teal">{{ fmt(liveBoat?.speed_sog) }}</span>
                <span class="instrument-unit">kn</span>
            </div>
            <div class="instrument">
                <span class="instrument-label">SOW</span>
                <span class="instrument-value text-teal">{{ fmt(liveBoat?.speed_stw) }}</span>
                <span class="instrument-unit">kn</span>
            </div>
            <div class="instrument">
                <span class="instrument-label">HDG</span>
                <span class="instrument-value">{{ fmt(liveBoat?.heading, 0) }}</span>
                <span class="instrument-unit">°</span>
            </div>
            <div class="instrument">
                <span class="instrument-label">Depth</span>
                <span class="instrument-value text-blue">{{ fmt(liveBoat?.depth) }}</span>
                <span class="instrument-unit">m</span>
            </div>
        </div>

        <!-- Center: Compass hero -->
        <div class="helm-center">
            <CompassRose
                :heading="liveBoat?.heading ?? 0"
                :cog="liveBoat?.cog ?? null"
                :size="340"
            />
            <div class="helm-pos-label">
                <span class="text-[13px] font-bold text-text-secondary">{{ pointOfSailText }}</span>
            </div>
            <div class="helm-footer">
                <span class="helm-footer-item">
                    <span class="helm-footer-label">HDG</span>
                    <span class="helm-footer-value">{{ fmt(liveBoat?.heading, 0) }}°</span>
                </span>
                <span class="helm-footer-sep">·</span>
                <span class="helm-footer-item">
                    <span class="helm-footer-label">TWD</span>
                    <span class="helm-footer-value">{{ twd != null ? fmt(twd, 0) + '°' : '—' }}</span>
                </span>
            </div>
        </div>

        <!-- Right instruments: TWS, TWA -->
        <div class="helm-instruments helm-instruments--right">
            <div class="instrument instrument--right">
                <span class="instrument-unit">kn</span>
                <span class="instrument-value text-amber">{{ fmt(liveBoat?.wind_speed_true) }}</span>
                <span class="instrument-label">TWS</span>
            </div>
            <div class="instrument instrument--right">
                <span class="instrument-unit">°</span>
                <span class="instrument-value text-amber">{{ liveBoat?.wind_direction_true != null ? fmt(liveBoat.wind_direction_true, 0) : '—' }}</span>
                <span class="instrument-label">TWA</span>
            </div>
            <div class="instrument instrument--right">
                <span class="instrument-unit">kn</span>
                <span class="instrument-value text-amber">{{ fmt(liveBoat?.wind_speed_apparent) }}</span>
                <span class="instrument-label">AWS</span>
            </div>
            <div class="instrument instrument--right">
                <span class="instrument-unit">°</span>
                <span class="instrument-value text-amber">{{ liveBoat?.wind_angle_apparent != null ? fmt(liveBoat.wind_angle_apparent, 0) : '—' }}</span>
                <span class="instrument-label">AWA</span>
            </div>
        </div>
    </div>

    <!-- Journey Section -->
    <div v-if="routeWaypointsArr.length || liveGps?.latitude" class="journey-grid panel p-0 mb-5 overflow-hidden">
        <!-- Map -->
        <div ref="mapEl" class="journey-map"></div>

        <!-- Nav sidebar -->
        <div class="journey-sidebar p-4 flex flex-col gap-4">
            <div class="journey-reading">
                <span class="journey-reading__label">DTW</span>
                <span class="journey-reading__value text-blue">{{ liveBoat?.nav_wp_distance != null ? fmt(liveBoat.nav_wp_distance, 1) : '—' }}</span>
                <span class="journey-reading__unit">nm</span>
            </div>
            <div class="journey-reading">
                <span class="journey-reading__label">TTG</span>
                <span class="journey-reading__value">{{ formatTtg(liveBoat?.nav_wp_ttg) }}</span>
            </div>
            <div class="journey-reading">
                <span class="journey-reading__label">ETA</span>
                <span class="journey-reading__value">{{ formatEta(liveBoat?.nav_wp_ttg) }}</span>
            </div>
            <div class="mt-auto pt-3 border-t border-border-light">
                <div v-if="liveGps?.latitude != null" class="text-[11px] text-text-dim tabular-nums leading-relaxed">
                    {{ fmtCoord(liveGps.latitude, liveGps.longitude) }}
                </div>
                <div class="text-[11px] text-text-dim mt-1">
                    <Link href="/admin/journeys" class="text-scarlet font-medium hover:underline">Journeys →</Link>
                </div>
            </div>
        </div>
    </div>

    <!-- Bottom Row -->
    <div class="bottom-grid">
        <!-- Ship Status panel -->
        <div class="panel p-4">
            <div class="flex items-baseline justify-between mb-4">
                <span class="panel-title">Ship Status</span>
                <Link href="/admin/tracker" class="text-[12px] text-scarlet font-medium hover:underline">Tracker →</Link>
            </div>
            <div class="space-y-3 mb-4">
                <LevelBar
                    :value="batteryPct"
                    label="Battery"
                    color="green"
                />
                <LevelBar
                    :value="fuelLevel"
                    label="Fuel"
                    color="amber"
                />
                <LevelBar
                    :value="waterLevel"
                    label="Water"
                    color="blue"
                />
            </div>
            <a :href="'/admin/explore?metric=scarlet_boat_battery_power'" class="block">
                <Sparkline :data="powerData" color="var(--color-green)" :height="36" :fill="true" :showDot="true" :zeroLine="true" />
            </a>
        </div>

        <!-- Weather panel -->
        <div v-if="liveWeather" class="panel overflow-hidden">
            <!-- Gradient hero flush with panel top -->
            <div class="weather-hero" :style="{ background: wxGradient }">
                <div class="weather-hero__icon">{{ wxIcon }}</div>
                <div>
                    <div class="weather-hero__temp">{{ wxTemp }}</div>
                    <div class="weather-hero__condition">{{ wxCondition }}</div>
                </div>
            </div>
            <div class="p-4 pt-3 space-y-2 text-[13px]">
                <div class="data-row">
                    <span class="text-text-secondary">Sea Temp</span>
                    <span class="text-blue font-semibold tabular-nums">{{ wxSeaTemp }}</span>
                </div>
                <div class="data-row">
                    <span class="text-text-secondary">Wind</span>
                    <span class="font-semibold tabular-nums">{{ wxWindSpeed }} {{ degreesToCompass(liveWeather.wind?.direction) }}</span>
                </div>
                <div class="data-row">
                    <span class="text-text-secondary">Waves</span>
                    <span class="font-semibold tabular-nums">{{ wxWaveHeight }}<span v-if="wxWavePeriod" class="text-text-dim"> @ {{ wxWavePeriod }}</span></span>
                </div>
                <div class="data-row">
                    <span class="text-text-secondary">Pressure</span>
                    <span class="font-semibold tabular-nums">{{ liveWeather.pressure != null ? fmt(liveWeather.pressure, 0) + ' hPa' : '—' }}</span>
                </div>
                <div class="mt-3 pt-3 border-t border-border-light">
                    <Link href="/admin/weather" class="text-[12px] text-scarlet font-medium hover:underline">Weather details →</Link>
                </div>
            </div>
        </div>
        <div v-else class="panel p-4">
            <div class="panel-title mb-3">Weather</div>
            <p class="text-[13px] text-text-secondary">No weather data available.</p>
        </div>
    </div>
    </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import L from 'leaflet';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import CompassRose from '@/Components/Admin/CompassRose.vue';
import LevelBar from '@/Components/Admin/LevelBar.vue';
import Sparkline from '@/Components/Admin/Sparkline.vue';
import { fmt, fmtDuration } from '@/composables/useFormatters.js';
import { useScarletMetrics } from '@/composables/useScarletMetrics.js';

const props = defineProps({
    boat: Object,
    gps: Object,
    tracker: Object,
    weather: Object,
    activeJourney: Object,
    plannedJourney: Object,
    routeWaypoints: { type: Array, default: () => [] },
    recentJourneys: { type: Array, default: () => [] },
    streamOnline: Boolean,
    streamPublisher: Boolean,
    timestamp: String,
    powerHistory: { type: Array, default: () => [] },
});

const routeWaypointsArr = computed(() => props.routeWaypoints ?? []);

const {
    boat: liveBoat,
    gps: liveGps,
    weather: liveWeather,
    statusText,
    wxTemp,
    wxCondition,
    wxIcon,
    wxSeaTemp,
    wxWindSpeed,
    wxWaveHeight,
    wxWavePeriod,
    initMap,
    addMapTarget,
} = useScarletMetrics({
    initialMetrics: { boat: props.boat, gps: props.gps, weather: props.weather },
    gpsTrack: routeWaypointsArr.value.map(w => [w.lat, w.lng]),
    routeWaypoints: routeWaypointsArr.value,
});

// ── Map ──────────────────────────────────────────────────────────────────────
const mapEl = ref(null);
let map = null;

onMounted(() => {
    if (mapEl.value) {
        map = initMap(mapEl.value, { interactive: true });
        addMapTarget(map, { autoCenter: true });

        if (routeWaypointsArr.value.length) {
            const bounds = L.latLngBounds(routeWaypointsArr.value.map(w => [w.lat, w.lng]));
            map.fitBounds(bounds, { padding: [30, 30] });
        }
    }
});

onUnmounted(() => {
    map?.remove();
    map = null;
});

// ── Computed helpers ─────────────────────────────────────────────────────────
const twd = computed(() => liveBoat.value?.wind_direction_true ?? null);

const pointOfSailText = computed(() => {
    const twa = liveBoat.value?.wind_direction_true;
    const hdg = liveBoat.value?.heading;
    if (twa == null || hdg == null) return '';
    const rel = ((twa - hdg + 360) % 360);
    const abs = rel > 180 ? 360 - rel : rel;
    if (abs < 45) return 'In Irons';
    if (abs < 60) return 'Close Hauled';
    if (abs < 80) return 'Close Reach';
    if (abs < 100) return 'Beam Reach';
    if (abs < 150) return 'Broad Reach';
    if (abs < 170) return 'Running';
    return 'Dead Run';
});

// Battery / tank / power computeds
const batteryPct = computed(() => {
    const soc = liveBoat.value?.house_battery_soc;
    if (soc != null) return Math.min(100, Math.max(0, soc));
    const v = liveBoat.value?.house_battery_voltage;
    if (v == null) return 0;
    return Math.min(100, Math.max(0, ((v - 11.5) / (12.7 - 11.5)) * 100));
});

const fuelLevel = computed(() => liveBoat.value?.fuel_level ?? 0);
const waterLevel = computed(() => liveBoat.value?.water_level ?? 0);
const powerData = computed(() => (props.powerHistory ?? []).map(d => d?.value ?? d));

// ── Coordinate formatting ─────────────────────────────────────────────────────
function fmtCoord(lat, lon) {
    if (lat == null || lon == null) return '';
    return fmtDM(lat, 'N', 'S') + '  ' + fmtDM(lon, 'E', 'W');
}

function fmtDM(decimal, pos, neg) {
    const dir = decimal >= 0 ? pos : neg;
    const abs = Math.abs(decimal);
    const deg = Math.floor(abs);
    const min = ((abs - deg) * 60).toFixed(1).padStart(4, '0');
    return `${deg}°${min}'${dir}`;
}

function formatTtg(seconds) {
    if (seconds == null || seconds <= 0) return '—';
    const s = Math.floor(seconds);
    const d = Math.floor(s / 86400);
    const h = Math.floor((s % 86400) / 3600);
    const m = Math.floor((s % 3600) / 60);
    if (d > 0) return `${d}d ${h}h`;
    if (h > 0) return `${h}h ${m}m`;
    return `${m}m`;
}

function formatEta(seconds) {
    if (seconds == null || seconds <= 0) return '—';
    const eta = new Date(Date.now() + seconds * 1000);
    return eta.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
}

function degreesToCompass(deg) {
    if (deg == null) return '—';
    const dirs = ['N', 'NNE', 'NE', 'ENE', 'E', 'ESE', 'SE', 'SSE', 'S', 'SSW', 'SW', 'WSW', 'W', 'WNW', 'NW', 'NNW'];
    return dirs[Math.round(((deg % 360) + 360) % 360 / 22.5) % 16];
}

const wxSummary = computed(() => liveWeather.value?.summary ?? 'na');
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
    };
    return gradients[wxSummary.value] ?? 'linear-gradient(135deg, oklch(0.62 0.08 220), oklch(0.55 0.10 200))';
});
</script>

<style scoped>
/* ── Status ribbon ─────────────────────────────────────────────────────────── */
.health-dot {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}
.health-dot--green { background: var(--color-green); box-shadow: 0 0 5px oklch(0.48 0.16 150 / 0.4); }
.health-dot--amber { background: oklch(0.7 0.18 70); box-shadow: 0 0 5px oklch(0.7 0.18 70 / 0.4); }
.health-dot--red   { background: var(--color-scarlet); }

.sailing-badge {
    display: flex;
    align-items: center;
    gap: 7px;
    background: linear-gradient(135deg, oklch(0.9 0.06 150), oklch(0.85 0.07 160));
    color: oklch(0.24 0.1 150);
    padding: 5px 16px 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 1.2px;
    text-transform: uppercase;
}
.sailing-badge::before {
    content: '';
    width: 7px;
    height: 7px;
    background: oklch(0.48 0.16 150);
    border-radius: 50%;
}

/* ── Helm section ──────────────────────────────────────────────────────────── */
.helm-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 16px;
    align-items: center;
}

@media (min-width: 900px) {
    .helm-grid {
        grid-template-columns: 1fr 360px 1fr;
    }
}

.helm-instruments {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.helm-instruments--left { align-items: flex-start; }
.helm-instruments--right { align-items: flex-end; }

.instrument {
    display: flex;
    flex-direction: row;
    align-items: baseline;
    gap: 6px;
}

.instrument--right {
    flex-direction: row-reverse;
}

.instrument-label {
    font-size: 12px;
    font-weight: 700;
    color: var(--color-text-dim);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    width: 46px;
    flex-shrink: 0;
}

.instrument--right .instrument-label {
    text-align: right;
}

.instrument-value {
    font-family: var(--font-sans);
    font-size: 46px;
    font-weight: 600;
    letter-spacing: -0.02em;
    line-height: 1;
    font-variant-numeric: tabular-nums;
    color: var(--color-text-primary);
}

.instrument-unit {
    font-size: 14px;
    font-weight: 500;
    color: var(--color-text-dim);
    margin-bottom: 4px;
}

.helm-center {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
}

.helm-pos-label {
    text-align: center;
}

.helm-footer {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 6px 16px;
    background: var(--color-bg);
    border: 1px solid var(--color-border-light);
    border-radius: 20px;
    font-size: 12px;
}

.helm-footer-item {
    display: flex;
    align-items: center;
    gap: 5px;
}

.helm-footer-label {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    color: var(--color-text-dim);
    letter-spacing: 0.04em;
}

.helm-footer-value {
    font-weight: 600;
    font-variant-numeric: tabular-nums;
    color: var(--color-text-primary);
}

.helm-footer-sep {
    color: var(--color-border);
}

/* ── Journey section ───────────────────────────────────────────────────────── */
.journey-grid {
    display: grid;
    grid-template-columns: 1fr;
}

@media (min-width: 640px) {
    .journey-grid {
        grid-template-columns: 1fr 220px;
    }
}

.journey-map {
    height: 260px;
    isolation: isolate;
}

@media (min-width: 640px) {
    .journey-map { height: 320px; }
}

.journey-sidebar {
    border-top: 1px solid var(--color-border-light);
}

@media (min-width: 640px) {
    .journey-sidebar {
        border-top: none;
        border-left: 1px solid var(--color-border-light);
    }
}

.journey-reading {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.journey-reading__label {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--color-text-dim);
}

.journey-reading__value {
    font-size: 26px;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    line-height: 1;
    color: var(--color-text-primary);
}

.journey-reading__unit {
    font-size: 12px;
    color: var(--color-text-dim);
}

/* ── Bottom row ────────────────────────────────────────────────────────────── */
.bottom-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 12px;
}

@media (min-width: 640px) {
    .bottom-grid {
        grid-template-columns: 1fr 1fr;
    }
}

/* ── Weather hero ──────────────────────────────────────────────────────────── */
.weather-hero {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 20px;
    color: white;
}

.weather-hero__icon {
    font-size: 40px;
    line-height: 1;
    flex-shrink: 0;
}

.weather-hero__temp {
    font-size: 32px;
    font-weight: 800;
    font-variant-numeric: tabular-nums;
    line-height: 1;
}

.weather-hero__condition {
    font-size: 13px;
    font-weight: 600;
    opacity: 0.85;
    margin-top: 3px;
}

/* ── Shared utilities ──────────────────────────────────────────────────────── */
.panel-title {
    font-size: 15px;
    font-weight: 600;
}

.data-row {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
}

.data-row span:last-child {
    font-variant-numeric: tabular-nums;
    font-weight: 600;
}

/* ── Color helpers used in instrument values ───────────────────────────────── */
.text-teal   { color: var(--color-teal); }
.text-amber  { color: var(--color-amber); }
.text-blue   { color: var(--color-blue); }
.text-green  { color: var(--color-green); }
.text-scarlet { color: var(--color-scarlet); }
</style>
