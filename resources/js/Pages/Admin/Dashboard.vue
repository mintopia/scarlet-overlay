<template>
    <AdminLayout>
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
                    <span class="ml-2 text-[10px] font-semibold tracking-wide px-2 py-0.5 rounded bg-amber-bg text-amber">PLANNED</span>
                </template>
                <template v-else>
                    &nbsp;·&nbsp;<span class="text-text-dim">No active journey</span>
                </template>
            </span>
        </div>
        <div class="flex gap-4 items-center">
            <div class="flex gap-3 items-center">
                <div class="flex items-center gap-1.5" :title="trackerFresh ? 'Receiving telemetry data' : 'No data received for 2+ minutes'">
                    <span :class="['health-dot', trackerFresh ? 'health-dot--green' : 'health-dot--red']"></span>
                    <span class="text-[11px] font-body text-text-dim font-medium">Tracker</span>
                </div>
                <div class="flex items-center gap-1.5" :title="props.streamOnline ? 'Stream is live' : 'Stream is offline'">
                    <span :class="['health-dot', props.streamOnline ? 'health-dot--green' : 'health-dot--red']"></span>
                    <span class="text-[11px] font-body text-text-dim font-medium">Stream</span>
                </div>
                <div class="flex items-center gap-1.5" :title="props.streamPublisher ? 'Publisher connected' : 'No active publisher'">
                    <span :class="['health-dot', props.streamPublisher ? 'health-dot--green' : 'health-dot--amber']"></span>
                    <span class="text-[11px] font-body text-text-dim font-medium">Publisher</span>
                </div>
            </div>
            <div v-if="liveSun" class="flex items-center gap-2.5 border-l border-border-light pl-4 text-[11px] font-body tabular-nums">
                <span class="flex items-center gap-1" :title="'Sunrise' + (liveSun.civilDawn ? ' (dawn ' + liveSun.civilDawn + ')' : '')">
                    <svg class="w-3 h-3 text-amber" viewBox="0 0 16 16" fill="currentColor"><path d="M8 1v2m0 10v2M1 8h2m10 0h2m-2.5-4.5L11 5m-6 6-1.5 1.5M13.5 12.5 12 11M5 5 3.5 3.5"/><circle cx="8" cy="8" r="3"/><path d="M8 4a4 4 0 0 1 0 8" fill="none" stroke="currentColor" stroke-width="1"/></svg>
                    <span class="font-semibold">{{ liveSun.sunrise === 'always' ? 'No set' : liveSun.sunrise === 'never' ? '--:--' : liveSun.sunrise }}</span>
                </span>
                <span class="flex items-center gap-1" :title="'Sunset' + (liveSun.civilDusk ? ' (dusk ' + liveSun.civilDusk + ')' : '')">
                    <svg class="w-3 h-3 text-text-dim" viewBox="0 0 16 16" fill="currentColor"><path d="M8 1v2m0 10v2M1 8h2m10 0h2m-2.5-4.5L11 5m-6 6-1.5 1.5M13.5 12.5 12 11M5 5 3.5 3.5"/><circle cx="8" cy="8" r="3"/></svg>
                    <span class="font-semibold">{{ liveSun.sunset === 'always' ? 'No rise' : liveSun.sunset === 'never' ? '--:--' : liveSun.sunset }}</span>
                </span>
            </div>
            <div class="text-right border-l border-border-light pl-4">
                <div class="text-sm font-semibold tabular-nums font-sans">{{ clock }}</div>
                <div class="text-[10px] text-text-dim font-medium">{{ clockDate }}</div>
            </div>
        </div>
    </div>

    <!-- Helm + Systems row -->
    <div class="helm-row mb-5">
        <!-- Helm panel (2/3) -->
        <div class="helm-grid panel p-5">
            <!-- Left instruments: SOG, SOW, Depth + Log -->
            <div class="helm-instruments helm-instruments--left">
                <div class="helm-reading">
                    <span class="helm-reading__label text-teal">SOG</span>
                    <div class="helm-reading__row">
                        <span class="helm-reading__value text-teal">{{ fmt(animSog) }}</span>
                        <span class="helm-reading__unit">kn</span>
                    </div>
                </div>
                <div class="helm-reading">
                    <span class="helm-reading__label text-teal">SOW</span>
                    <div class="helm-reading__row">
                        <span class="helm-reading__value text-teal">{{ fmt(animStw) }}</span>
                        <span class="helm-reading__unit">kn</span>
                    </div>
                </div>
                <div class="helm-reading">
                    <span class="helm-reading__label text-blue">Depth</span>
                    <div class="helm-reading__row">
                        <span class="helm-reading__value text-blue">{{ fmt(animDepth) }}</span>
                        <span class="helm-reading__unit">m</span>
                    </div>
                </div>
                <div class="helm-reading">
                    <span class="helm-reading__label">Log</span>
                    <div class="helm-reading__row">
                        <span class="helm-reading__value">{{ liveBoat?.trip_log != null ? fmt(animLog, 0) : '—' }}</span>
                        <span class="helm-reading__unit">nm</span>
                    </div>
                </div>
            </div>

            <!-- Center: Compass hero -->
            <div class="helm-center">
                <CompassRose
                    :heading="liveBoat?.heading ?? 0"
                    :cog="liveBoat?.cog ?? null"
                    :twa="relativeTwa"
                    :awa="liveBoat?.wind_angle_apparent ?? null"
                    :size="340"
                />
            </div>

            <!-- Right: Heading + Wind readings + point of sail -->
            <div class="helm-readings">
                <div class="helm-reading">
                    <span class="helm-reading__label" style="color: var(--color-teal)">Heading</span>
                    <div class="helm-reading__row">
                        <span class="helm-reading__value text-teal">{{ fmt(animHdg, 0) }}</span>
                        <span class="helm-reading__unit">°</span>
                    </div>
                </div>
                <div class="helm-reading">
                    <span class="helm-reading__label" style="color: var(--color-amber)">True Wind</span>
                    <div class="helm-reading__row">
                        <span class="helm-reading__value text-amber">{{ fmt(animTws) }}</span>
                        <span class="helm-reading__unit">kn</span>
                    </div>
                    <div class="helm-reading__sub">{{ twd != null ? fmt(animTwd, 0) + '°' : '—' }}</div>
                </div>
                <div class="helm-reading">
                    <span class="helm-reading__label" style="color: var(--color-teal); opacity: 0.65">Apparent Wind</span>
                    <div class="helm-reading__row">
                        <span class="helm-reading__value" style="color: var(--color-teal); opacity: 0.65">{{ fmt(animAws) }}</span>
                        <span class="helm-reading__unit">kn</span>
                    </div>
                    <div class="helm-reading__sub">{{ liveBoat?.wind_angle_apparent != null ? fmt(Math.abs(animAwa), 0) + '°' : '—' }}</div>
                </div>
                <div class="helm-pos-badge" :style="{ '--pos-color': pointOfSailColor }" v-if="pointOfSailText">
                    {{ pointOfSailText }}
                </div>
            </div>
        </div>

        <!-- Right column: Systems + Weather (1/3) -->
        <div class="helm-sidebar">
            <!-- Systems -->
            <div class="panel p-4 flex flex-col">
                <div class="flex items-baseline justify-between mb-3">
                    <span class="text-[11px] font-body font-extrabold tracking-[2.5px] uppercase text-teal">Systems</span>
                    <Link href="/admin/tracker" class="text-[11px] font-body font-bold text-teal hover:underline">Explore →</Link>
                </div>
                <div class="space-y-2.5 mb-3">
                    <LevelBar :value="batteryPct" label="Battery" color="green" />
                    <LevelBar :value="fuelLevel" label="Fuel" color="amber" />
                    <LevelBar :value="waterLevel" label="Water" color="blue" />
                </div>
                <div>
                    <div class="flex items-baseline justify-between mb-1">
                        <span class="text-[10px] font-body font-bold text-text-dim uppercase tracking-wide">Power</span>
                        <span class="font-sans text-xs font-semibold tabular-nums" :class="(liveBoat?.house_battery_current ?? 0) >= 0 ? 'text-green' : 'text-amber'">
                            {{ liveBoat?.house_battery_voltage != null && liveBoat?.house_battery_current != null ? (liveBoat.house_battery_current >= 0 ? '+' : '') + Math.round(liveBoat.house_battery_voltage * liveBoat.house_battery_current) + 'W' : '—' }}
                        </span>
                    </div>
                    <a href="/admin/explore?metric=battery_power" class="block">
                        <Sparkline :data="powerData" color="var(--color-green)" :height="36" :fill="true" :showDot="true" :zeroLine="true" />
                    </a>
                </div>
            </div>

            <!-- Weather -->
            <div v-if="liveWeather" class="panel flex flex-col flex-1 overflow-hidden">
                <div class="wx-hero rounded-t-[15px]" :style="{ background: wxGradient }">
                    <div>
                        <div class="wx-hero__temp font-sans text-[44px] font-bold tracking-tight leading-none tabular-nums">
                            {{ wxTemp }}
                        </div>
                        <div class="wx-hero__cond text-sm font-body font-medium mt-1">{{ wxCondition }}</div>
                    </div>
                    <div class="text-[36px] leading-none" aria-hidden="true">{{ wxIcon }}</div>
                </div>
                <div class="p-3 space-y-1 text-[11px] flex-1">
                    <div class="data-row"><span class="font-body text-text-dim">Sea</span><span class="font-sans font-semibold tabular-nums">{{ wxSeaTemp }}</span></div>
                    <div class="data-row"><span class="font-body text-text-dim">Wind</span><span class="font-sans font-semibold tabular-nums">{{ wxWindSpeed }}</span></div>
                    <div class="data-row"><span class="font-body text-text-dim">Waves</span><span class="font-sans font-semibold tabular-nums">{{ wxWaveHeight }}</span></div>
                    <div class="data-row"><span class="font-body text-text-dim">Pressure</span><span class="font-sans font-semibold tabular-nums">{{ liveWeather.pressure != null ? fmt(liveWeather.pressure, 0) + ' hPa' : '—' }}</span></div>
                </div>
            </div>
            <div v-else class="panel p-3 flex-1">
                <div class="text-[11px] font-body font-extrabold tracking-[2.5px] uppercase text-text-dim mb-1">Weather</div>
                <p class="text-[11px] font-body text-text-secondary">No data</p>
            </div>
        </div>
    </div>

    <!-- Journey Section -->
    <div v-if="routeWaypointsArr.length || liveGps?.latitude" class="journey-grid panel p-0 mb-5 overflow-hidden">
        <!-- Map -->
        <div ref="mapEl" class="journey-map">
            <MapLayerControl
                v-model:base="mapBase"
                v-model:seamark="mapSeamark"
                v-model:contours="mapContours"
            />
        </div>

        <!-- Nav sidebar -->
        <div class="journey-sidebar p-4 flex flex-col gap-4">
            <div class="journey-reading">
                <span class="journey-reading__label">DTW</span>
                <span class="journey-reading__value text-blue">{{ liveBoat?.nav_wp_distance != null ? fmt(animDtw, 1) : '—' }}</span>
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
            <div class="journey-reading" v-if="props.activeJourney?.distance || props.plannedJourney?.distance">
                <span class="journey-reading__label">Distance</span>
                <span class="journey-reading__value">{{ props.activeJourney?.distance ?? props.plannedJourney?.distance ?? '—' }}</span>
                <span class="journey-reading__unit">nm</span>
            </div>
            <div class="journey-reading" v-if="liveGps?.latitude != null">
                <span class="journey-reading__label">Lat</span>
                <span class="journey-reading__value">{{ fmtDM(liveGps.latitude, 'N', 'S') }}</span>
            </div>
            <div class="journey-reading" v-if="liveGps?.longitude != null">
                <span class="journey-reading__label">Lon</span>
                <span class="journey-reading__value">{{ fmtDM(liveGps.longitude, 'E', 'W') }}</span>
            </div>
        </div>
    </div>

    </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import L from 'leaflet';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import CompassRose from '@/components/Admin/CompassRose.vue';
import LevelBar from '@/components/Admin/LevelBar.vue';
import Sparkline from '@/components/Admin/Sparkline.vue';
import { fmt, fmtDuration } from '@/composables/useFormatters.js';
import { useScarletMetrics } from '@/composables/useScarletMetrics.js';
import { useMapLayers } from '@/composables/useMapLayers.js';
import { useSpringValue, useAngleSpring } from '@/composables/useSpringValue.js';
import MapLayerControl from '@/components/MapLayerControl.vue';

const props = defineProps({
    boat: Object,
    gps: Object,
    tracker: Object,
    weather: Object,
    settings: Object,
    activeJourney: Object,
    plannedJourney: Object,
    routeWaypoints: { type: Array, default: () => [] },
    gpsTrack: { type: Array, default: () => [] },
    recentJourneys: { type: Array, default: () => [] },
    sun: Object,
    streamOnline: Boolean,
    streamPublisher: Boolean,
    timestamp: String,
    powerHistory: { type: Array, default: () => [] },
});

const routeWaypointsArr = computed(() => props.routeWaypoints ?? []);
const trackerFresh = computed(() => {
    const ts = props.tracker?.last_seen;
    if (!ts) return false;
    return (Date.now() / 1000) - ts < 120;
});

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
    sun: liveSun,
    clock,
    clockDate,
    initMap,
    addMapTarget,
} = useScarletMetrics({
    initialMetrics: { boat: props.boat, gps: props.gps, weather: props.weather, settings: props.settings },
    initialSun: props.sun,
    gpsTrack: props.gpsTrack,
    routeWaypoints: routeWaypointsArr.value,
});

// ── Animated instrument values ───────────────────────────────────────────────
const animSog = useSpringValue(() => liveBoat.value?.speed_sog);
const animStw = useSpringValue(() => liveBoat.value?.speed_stw);
const animDepth = useSpringValue(() => liveBoat.value?.depth);
const animLog = useSpringValue(() => liveBoat.value?.trip_log);
const animTws = useSpringValue(() => liveBoat.value?.wind_speed_true);
const animAws = useSpringValue(() => liveBoat.value?.wind_speed_apparent);
const animDtw = useSpringValue(() => liveBoat.value?.nav_wp_distance);
const animHdg = useAngleSpring(() => liveBoat.value?.heading);
const animTwd = useAngleSpring(() => liveBoat.value?.wind_direction_true);
const animAwa = useAngleSpring(() => liveBoat.value?.wind_angle_apparent);

// ── Map ──────────────────────────────────────────────────────────────────────
const mapEl = ref(null);
const { base: mapBase, seamark: mapSeamark, contours: mapContours, attach: attachLayers } = useMapLayers();
let map = null;

onMounted(() => {
    if (mapEl.value) {
        map = initMap(mapEl.value, { interactive: true, tiles: false });
        attachLayers(map);
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

const relativeTwa = computed(() => {
    if (liveBoat.value?.wind_angle_true != null) return liveBoat.value.wind_angle_true;
    const twdVal = liveBoat.value?.wind_direction_true;
    const hdg = liveBoat.value?.heading;
    if (twdVal == null || hdg == null) return null;
    return ((twdVal - hdg + 360) % 360);
});


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

const pointOfSailColor = computed(() => {
    const t = pointOfSailText.value;
    if (t === 'In Irons') return 'var(--color-scarlet)';
    if (t === 'Close Hauled' || t === 'Close Reach') return 'var(--color-teal)';
    if (t === 'Beam Reach' || t === 'Broad Reach') return 'var(--color-amber)';
    if (t === 'Running' || t === 'Dead Run') return 'var(--color-green)';
    return 'var(--color-text-dim)';
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
    transition: background-color 0.4s ease-out, box-shadow 0.4s ease-out;
}

@media (prefers-reduced-motion: reduce) {
    .health-dot { transition: none; }
}
.health-dot--green { background: var(--color-green); box-shadow: 0 0 5px oklch(0.48 0.16 150 / 0.4); }
.health-dot--amber { background: oklch(0.7 0.18 70); box-shadow: 0 0 5px oklch(0.7 0.18 70 / 0.4); }
.health-dot--red   { background: var(--color-scarlet); }

.sailing-badge {
    display: flex;
    align-items: center;
    gap: 7px;
    background: var(--color-green-bg);
    color: var(--color-green);
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
    background: var(--color-green);
    border-radius: 50%;
}

/* ── Helm row (helm 2/3 + sidebar 1/3) ────────────────────────────────────── */
.helm-row {
    display: grid;
    grid-template-columns: 1fr;
    gap: 12px;
}

@media (min-width: 1024px) {
    .helm-row {
        grid-template-columns: 2fr 1fr;
    }
}

.helm-sidebar {
    display: flex;
    flex-direction: column;
    gap: 12px;
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
    gap: 16px;
}

.helm-instruments--left { align-items: flex-start; }
.helm-instruments--left .helm-reading { align-items: flex-start; }

/* ── Wind readings ────────────────────────────────────────────────────────── */
.helm-readings {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    justify-content: center;
    gap: 16px;
}

.helm-reading {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 2px;
}

.helm-reading__label {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--color-text-dim);
}

.helm-reading__row {
    display: flex;
    align-items: baseline;
    gap: 4px;
}

.helm-reading__value {
    font-family: var(--font-sans);
    font-size: 36px;
    font-weight: 600;
    letter-spacing: -0.02em;
    line-height: 1;
    font-variant-numeric: tabular-nums;
    color: var(--color-text-primary);
}

.helm-reading__unit {
    font-size: 11px;
    font-weight: 500;
    color: var(--color-text-dim);
}

.helm-reading__sub {
    font-family: var(--font-sans);
    font-size: 13px;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
    color: var(--color-text-dim);
    margin-top: -2px;
}

.helm-pos-badge {
    display: inline-flex;
    align-items: center;
    padding: 5px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--pos-color);
    background: color-mix(in oklch, var(--pos-color) 12%, transparent);
    align-self: flex-end;
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
    min-height: 300px;
    isolation: isolate;
}

@media (min-width: 640px) {
    .journey-grid { min-height: 400px; }
    .journey-map { height: 100%; }
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

/* ── Weather hero ──────────────────────────────────────────────────────────── */
.wx-hero {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px 20px 18px;
}

/* ── Shared utilities ──────────────────────────────────────────────────────── */
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
