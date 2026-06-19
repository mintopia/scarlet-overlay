<template>
    <AdminLayout :breadcrumbs="[{ label: 'Ops' }]">
        <Head title="Ops Dashboard" />

        <div class="ops-dash">
            <!-- Status bar -->
            <div class="ops-status">
                <span class="ops-badge">
                    <span class="ops-badge__dot"></span>
                    {{ statusText }}
                </span>
                <span class="ops-hdot">Tracker</span>
                <span class="ops-hdot">SignalK</span>
                <span class="ops-hdot">EcoFlow</span>
                <span class="ops-hdot">Sensors</span>
                <span class="ops-passage">{{ passageLabel }}</span>
            </div>

            <!-- REGION 1: ENDURANCE -->
            <div class="ops-region">
                <span class="ops-seclabel">Endurance</span>
                <div class="ops-endgrid">
                    <!-- Power: House + EcoFlow side by side -->
                    <div class="ops-power">
                        <!-- House Battery -->
                        <div class="ops-sys ops-sys--house">
                            <div class="ops-sys__header">
                                <span class="ops-seclabel ops-seclabel--inline" style="color: var(--color-green)">House Battery</span>
                            </div>
                            <div class="ops-sys__main">
                                <Link
                                    :href="route('admin.data.show', { metric: 'house_battery_soc' })"
                                    class="metric-link ops-sys__soc ops-sys__soc--house"
                                    :class="{ 'ops-stale': stale('house_battery_soc') }"
                                >{{ houseSocDisplay }}</Link>
                                <span class="ops-sys__rem">
                                    <span class="ops-sys__rem-lab">runtime</span>
                                    <span class="ops-sys__rem-val">~18 h</span>
                                </span>
                            </div>
                            <div class="ops-sys__det" :class="{ 'ops-stale': stale('house_battery_voltage') }">
                                <b>{{ houseVoltDisplay }}</b> V ·
                                <span :class="housePowerClass">{{ housePowerDisplay }}</span>
                            </div>
                            <div class="ops-chart">
                                <TrendChart
                                    v-if="housePowerHistory.length > 0"
                                    :data="housePowerHistory"
                                    variant="bipolar"
                                    color-positive="var(--color-green)"
                                    color-negative="var(--color-scarlet)"
                                    :height="104"
                                    :width="300"
                                />
                                <div v-else class="ops-chart__empty">No Data</div>
                            </div>
                            <div class="ops-axis">
                                <span>net power · 6 h</span>
                                <span>now</span>
                            </div>
                        </div>

                        <!-- EcoFlow Delta -->
                        <div class="ops-sys ops-sys--eco">
                            <div class="ops-sys__header">
                                <span class="ops-seclabel ops-seclabel--inline" style="color: var(--color-teal)">EcoFlow Delta</span>
                            </div>
                            <div class="ops-sys__main">
                                <Link
                                    :href="route('admin.data.show', { metric: 'ecoflow_soc' })"
                                    class="metric-link ops-sys__soc ops-sys__soc--eco"
                                    :class="{ 'ops-stale': stale('ecoflow_soc') }"
                                >{{ ecoflowSocDisplay }}</Link>
                                <span class="ops-sys__rem">
                                    <span class="ops-sys__rem-lab">{{ ecoflowRemLabel }}</span>
                                    <span class="ops-sys__rem-val">{{ ecoflowRemDisplay }}</span>
                                </span>
                            </div>
                            <div class="ops-sys__det">
                                <span :class="ecoflowPowerClass">{{ ecoflowPowerDisplay }}</span>
                            </div>
                            <div class="ops-chart">
                                <TrendChart
                                    v-if="ecoflowPowerHistory.length > 0"
                                    :data="ecoflowPowerHistory"
                                    variant="bipolar"
                                    color-positive="var(--color-green)"
                                    color-negative="var(--color-scarlet)"
                                    :height="104"
                                    :width="300"
                                />
                                <div v-else class="ops-chart__empty">No Data</div>
                            </div>
                            <div class="ops-axis">
                                <span>net flow · 6 h</span>
                                <span>now</span>
                            </div>
                        </div>
                    </div>

                    <!-- Tanks: Fuel + Water -->
                    <div class="ops-tanks">
                        <!-- Fuel -->
                        <div class="ops-tank ops-tank--fuel">
                            <div class="ops-tank__header">
                                <span class="ops-tank__label">Fuel · diesel</span>
                                <Link
                                    :href="route('admin.data.show', { metric: 'fuel_level' })"
                                    class="metric-link ops-tank__pct ops-tank__pct--fuel"
                                    :class="{ 'ops-stale': stale('fuel_level') }"
                                >{{ fuelPctDisplay }}</Link>
                                <span class="ops-tank__days">{{ fuelDaysDisplay }}</span>
                            </div>
                            <div class="ops-tank__chart">
                                <Link
                                    v-if="fuelHistory.length > 0"
                                    :href="route('admin.data.show', { metric: 'fuel_level' })"
                                    class="metric-link metric-link--block"
                                >
                                    <TrendChart
                                        :data="fuelHistory"
                                        variant="area"
                                        color="var(--color-amber)"
                                        :height="54"
                                    />
                                </Link>
                                <div v-else class="ops-tank__empty-wrap">
                                    <svg viewBox="0 0 360 54" preserveAspectRatio="none" class="ops-tank__empty-svg">
                                        <line x1="0" y1="27" x2="360" y2="27" stroke="var(--color-border-light)" stroke-width="1" stroke-dasharray="3 3"/>
                                    </svg>
                                    <div class="ops-tank__empty-label">No Data</div>
                                </div>
                            </div>
                            <div class="ops-axis">
                                <span>6 h ago</span>
                                <span>now</span>
                            </div>
                        </div>

                        <!-- Water -->
                        <div class="ops-tank ops-tank--water">
                            <div class="ops-tank__header">
                                <span class="ops-tank__label">Fresh water</span>
                                <Link
                                    :href="route('admin.data.show', { metric: 'water_fresh_level' })"
                                    class="metric-link ops-tank__pct ops-tank__pct--water"
                                    :class="{ 'ops-stale': stale('water_fresh_level') }"
                                >{{ waterPctDisplay }}</Link>
                                <span class="ops-tank__days">{{ waterDaysDisplay }}</span>
                            </div>
                            <div class="ops-tank__chart">
                                <Link
                                    v-if="waterHistory.length > 0"
                                    :href="route('admin.data.show', { metric: 'water_fresh_level' })"
                                    class="metric-link metric-link--block"
                                >
                                    <TrendChart
                                        :data="waterHistory"
                                        variant="area"
                                        color="var(--color-blue)"
                                        :height="54"
                                    />
                                </Link>
                                <div v-else class="ops-tank__empty-wrap">
                                    <svg viewBox="0 0 360 54" preserveAspectRatio="none" class="ops-tank__empty-svg">
                                        <line x1="0" y1="27" x2="360" y2="27" stroke="var(--color-border-light)" stroke-width="1" stroke-dasharray="3 3"/>
                                    </svg>
                                    <div class="ops-tank__empty-label">No Data</div>
                                </div>
                            </div>
                            <div class="ops-axis">
                                <span>6 h ago</span>
                                <span>now</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- REGION 2: CABIN CLIMATE -->
            <div class="ops-region">
                <span class="ops-seclabel">Climate</span>
                <div class="ops-climate">
                    <!-- Current outside weather cell -->
                    <div class="ops-wnow">
                        <svg width="50" height="50" viewBox="0 0 40 40" aria-hidden="true">
                            <circle cx="15" cy="14" r="6.5" fill="oklch(0.82 0.14 78)"/>
                            <g fill="oklch(0.87 0.022 235)">
                                <circle cx="17" cy="25" r="6"/>
                                <circle cx="25" cy="23" r="7.5"/>
                                <rect x="12" y="23" width="19" height="9" rx="4.5"/>
                            </g>
                        </svg>
                        <div
                            class="ops-wnow__temp"
                            :class="{ 'ops-stale': stale('wx_air_temp') }"
                        >{{ wxAirTempDisplay }}</div>
                        <div class="ops-wnow__cond">
                            <div class="ops-wnow__cond-main">Outside</div>
                            <div class="ops-wnow__cond-sub">{{ wxWindDisplay }}</div>
                        </div>
                    </div>

                    <!-- Cabin gauges: Forepeak / Quarterberth / Main Cabin -->
                    <TemperatureGauge
                        label="Forepeak"
                        :temperature="val('cabin_temp_forepeak')"
                        :humidity="val('cabin_humidity_forepeak')"
                        color="oklch(0.6 0.14 60)"
                        :min="5"
                        :max="40"
                    />
                    <TemperatureGauge
                        label="Quarterberth"
                        :temperature="val('cabin_temp_quarterberth')"
                        :humidity="val('cabin_humidity_quarterberth')"
                        color="oklch(0.62 0.12 85)"
                        :min="5"
                        :max="40"
                    />
                    <TemperatureGauge
                        label="Main Cabin"
                        :temperature="val('cabin_temp_main')"
                        :humidity="val('cabin_humidity_main')"
                        color="oklch(0.6 0.15 45)"
                        :min="5"
                        :max="40"
                    />

                    <!-- Sea temp: no humidity, blue arc -->
                    <TemperatureGauge
                        label="Sea"
                        :temperature="val('water_temp')"
                        :humidity="null"
                        color="oklch(0.5 0.14 245)"
                        :min="0"
                        :max="30"
                    />
                </div>
            </div>

            <!-- REGION 3: CONDITIONS & POSITION -->
            <div class="ops-region">
                <!-- Weather band: Atmosphere / Sea State / Forecast -->
                <div class="ops-wxband">
                    <!-- Atmosphere -->
                    <div class="ops-wcol ops-wcol--atmos">
                        <div class="ops-wcl">Atmosphere</div>
                        <Link :href="route('admin.data.show', { metric: 'wx_wind_speed' })" class="metric-link ops-wcol__row">
                            <span class="ops-wcol__l">Wind</span>
                            <span class="ops-wcol__v ops-wcol__v--amber" :class="{ 'ops-stale': stale('wx_wind_speed') }">{{ windSpeedDisplay }}</span>
                        </Link>
                        <Link :href="route('admin.data.show', { metric: 'wx_wind_dir' })" class="metric-link ops-wcol__row">
                            <span class="ops-wcol__l">Direction</span>
                            <span class="ops-wcol__v" :class="{ 'ops-stale': stale('wx_wind_dir') }">{{ windDirDisplay }}</span>
                        </Link>
                        <Link :href="route('admin.data.show', { metric: 'wx_wind_gust' })" class="metric-link ops-wcol__row">
                            <span class="ops-wcol__l">Gust</span>
                            <span class="ops-wcol__v" :class="{ 'ops-stale': stale('wx_wind_gust') }">{{ gustDisplay }}</span>
                        </Link>
                        <Link :href="route('admin.data.show', { metric: 'wx_pressure' })" class="metric-link ops-wcol__row">
                            <span class="ops-wcol__l">Pressure</span>
                            <span class="ops-wcol__v ops-wcol__v--blue" :class="{ 'ops-stale': stale('wx_pressure') }">{{ pressureDisplay }}</span>
                        </Link>
                    </div>

                    <!-- Sea State -->
                    <div class="ops-wcol ops-wcol--sea">
                        <div class="ops-wcl">Sea State</div>
                        <Link :href="route('admin.data.show', { metric: 'sea_wave_height' })" class="metric-link ops-wcol__row">
                            <span class="ops-wcol__l">Wave height</span>
                            <span class="ops-wcol__v ops-wcol__v--blue" :class="{ 'ops-stale': stale('sea_wave_height') }">{{ waveHeightDisplay }}</span>
                        </Link>
                        <Link :href="route('admin.data.show', { metric: 'sea_wave_period' })" class="metric-link ops-wcol__row">
                            <span class="ops-wcol__l">Wave period</span>
                            <span class="ops-wcol__v ops-wcol__v--blue" :class="{ 'ops-stale': stale('sea_wave_period') }">{{ wavePeriodDisplay }}</span>
                        </Link>
                        <Link :href="route('admin.data.show', { metric: 'sea_wave_direction' })" class="metric-link ops-wcol__row">
                            <span class="ops-wcol__l">Swell dir.</span>
                            <span class="ops-wcol__v" :class="{ 'ops-stale': stale('sea_wave_direction') }">{{ waveDirectionDisplay }}</span>
                        </Link>
                        <div class="ops-wcol__row">
                            <span class="ops-wcol__l">Current</span>
                            <span class="ops-wcol__v" :class="{ 'ops-stale': stale('sea_current_speed') }">{{ seaCurrentDisplay }}</span>
                        </div>
                    </div>

                    <!-- Forecast — current wx in first cell; multi-day forecast follows -->
                    <div class="ops-wfc">
                        <div class="ops-wcl">Forecast</div>
                        <div
                            class="ops-wfc__cells"
                            :style="{ gridTemplateColumns: `repeat(${forecastDays.length + 1}, minmax(0, 1fr))` }"
                        >
                            <div class="ops-wfc__p">
                                <span class="ops-wfc__ph">Now</span>
                                <svg width="30" height="30" viewBox="0 0 28 28" aria-hidden="true">
                                    <circle cx="10" cy="9" r="4.5" fill="oklch(0.82 0.14 78)"/>
                                    <g fill="oklch(0.87 0.022 235)">
                                        <circle cx="12" cy="17" r="4.5"/>
                                        <circle cx="18" cy="16" r="5.5"/>
                                        <rect x="8" y="16" width="14" height="6.5" rx="3.25"/>
                                    </g>
                                </svg>
                                <span class="ops-wfc__pt" :class="{ 'ops-stale': stale('wx_air_temp') }">{{ wxAirTempDisplay }}</span>
                                <span class="ops-wfc__pw" :class="{ 'ops-stale': stale('wx_wind_speed') }">{{ windKtsShort }}</span>
                            </div>
                            <div v-for="day in forecastDays" :key="day.key" class="ops-wfc__p">
                                <span class="ops-wfc__ph">{{ day.label }}</span>
                                <span class="ops-wfc__pi" :title="day.title">{{ day.icon }}</span>
                                <span class="ops-wfc__pt">{{ day.temp }}</span>
                                <span class="ops-wfc__pw">{{ day.wind }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Position band: Map + Sailing & Nav -->
                <div class="ops-posband">
                    <div class="ops-cmap" ref="mapContainerRef">
                        <div class="ops-cmap__chip ops-cmap__chip--tr">{{ coordDisplay }}</div>
                        <div class="ops-cmap__chip ops-cmap__chip--bl">{{ passageLabel }}</div>
                    </div>

                    <div class="ops-saildata">
                        <span class="ops-seclabel ops-seclabel--inline">Sailing &amp; Navigation</span>
                        <div class="ops-sdrows">
                            <Link :href="route('admin.data.show', { metric: 'speed_sog' })" class="metric-link ops-sdrow">
                                <span class="ops-sdrow__l">SOG</span>
                                <span class="ops-sdrow__v ops-sdrow__v--teal" :class="{ 'ops-stale': stale('speed_sog') }">{{ sogDisplay }}</span>
                            </Link>
                            <Link :href="route('admin.data.show', { metric: 'heading_true' })" class="metric-link ops-sdrow">
                                <span class="ops-sdrow__l">Heading</span>
                                <span class="ops-sdrow__v ops-sdrow__v--teal" :class="{ 'ops-stale': stale('heading_true') }">{{ headingDisplay }}</span>
                            </Link>
                            <Link :href="route('admin.data.show', { metric: 'cog' })" class="metric-link ops-sdrow">
                                <span class="ops-sdrow__l">COG</span>
                                <span class="ops-sdrow__v ops-sdrow__v--teal" :class="{ 'ops-stale': stale('cog') }">{{ cogDisplay }}</span>
                            </Link>
                            <Link :href="route('admin.data.show', { metric: 'depth_below_surface' })" class="metric-link ops-sdrow">
                                <span class="ops-sdrow__l">Depth</span>
                                <span class="ops-sdrow__v ops-sdrow__v--blue" :class="{ 'ops-stale': stale('depth_below_surface') }">{{ depthDisplay }}</span>
                            </Link>
                            <Link :href="route('admin.data.show', { metric: 'wp_ttg' })" class="metric-link ops-sdrow">
                                <span class="ops-sdrow__l">ETA</span>
                                <span class="ops-sdrow__v" :class="{ 'ops-stale': stale('wp_ttg') }">{{ etaDisplay }}</span>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import TrendChart from '@/components/Admin/TrendChart.vue';
import TemperatureGauge from '@/components/Admin/TemperatureGauge.vue';
import { useScarletMetrics } from '@/composables/useScarletMetrics.js';

const props = defineProps({
    contracts: { type: Object, default: () => ({}) },
    housePowerHistory: { type: Array, default: () => [] },
    ecoflowPowerHistory: { type: Array, default: () => [] },
    fuelHistory: { type: Array, default: () => [] },
    waterHistory: { type: Array, default: () => [] },
    gps: { type: Object, default: () => ({}) },
    gpsTrack: { type: Array, default: () => [] },
    forecast: { type: Array, default: () => [] },
    reverb: { type: Object, default: null },
    reverbKey: { type: String, default: null },
});

// ── Map container ref ─────────────────────────────────────────────────────────
const mapContainerRef = ref(null);
let map = null;

// ── Live updates via Echo ─────────────────────────────────────────────────────
// useScarletMetrics wires Echo on 'metrics' channel and exposes a `canonical`
// ref updated on each broadcast. Also provides initMap/addMapTarget for Leaflet.
const {
    canonical,
    statusText,
    passageFrom,
    passageTo,
    initMap,
    addMapTarget,
} = useScarletMetrics({
    initialMetrics: { canonical: props.contracts, gps: props.gps },
    gpsTrack: props.gpsTrack,
});

// Merge SSR props with live canonical updates (same pattern as Tech.vue).
const liveContracts = computed(() => {
    return Object.assign({}, props.contracts, canonical.value ?? {});
});

// ── Contract helpers ──────────────────────────────────────────────────────────
function val(key, fallback = null) {
    return liveContracts.value?.[key]?.value ?? fallback;
}

function stale(key) {
    return liveContracts.value?.[key]?.stale ?? false;
}

function fmtInt(v) {
    if (v == null) return '—';
    return Math.round(v).toString();
}

function fmtFixed(v, decimals = 1) {
    if (v == null) return '—';
    return Number(v).toFixed(decimals);
}

function fmtDeg(v, decimals = 0) {
    if (v == null) return '—';
    return `${Number(v).toFixed(decimals)}°`;
}

// ── Lifecycle: Leaflet map ────────────────────────────────────────────────────
onMounted(() => {
    if (mapContainerRef.value) {
        map = initMap(mapContainerRef.value, { interactive: false });
        addMapTarget(map, {});
    }
});

onUnmounted(() => {
    map?.remove();
    map = null;
});

// ── Status / passage ──────────────────────────────────────────────────────────
const passageLabel = computed(() => {
    if (passageFrom.value && passageTo.value) {
        return `${passageFrom.value} → ${passageTo.value}`;
    }
    if (passageFrom.value) return `From ${passageFrom.value}`;
    if (passageTo.value) return `To ${passageTo.value}`;
    return '';
});

// ── House Battery ─────────────────────────────────────────────────────────────
const houseSocDisplay = computed(() => {
    const v = val('house_battery_soc');
    return v == null ? '—%' : `${fmtInt(v)}%`;
});

const houseVoltDisplay = computed(() => {
    const v = val('house_battery_voltage');
    return v == null ? '—' : Number(v).toFixed(1);
});

const housePowerDisplay = computed(() => {
    const hist = props.housePowerHistory;
    if (!hist || hist.length === 0) return '—';
    const last = hist[hist.length - 1];
    const w = typeof last === 'object' ? last.v : last;
    if (w == null) return '—';
    const abs = Math.abs(w).toFixed(0);
    return w >= 0 ? `+${abs} W charge` : `−${abs} W discharge`;
});

const housePowerClass = computed(() => {
    const hist = props.housePowerHistory;
    if (!hist || hist.length === 0) return '';
    const last = hist[hist.length - 1];
    const w = typeof last === 'object' ? last.v : last;
    if (w == null) return '';
    return w >= 0 ? 'ops-chg' : 'ops-dis';
});

// ── EcoFlow ───────────────────────────────────────────────────────────────────
const ecoflowSocDisplay = computed(() => {
    const v = val('ecoflow_soc');
    return v == null ? '—%' : `${fmtInt(v)}%`;
});

const ecoflowPowerDisplay = computed(() => {
    const inW = val('ecoflow_input_watts');
    const outW = val('ecoflow_output_watts');
    if (inW == null && outW == null) return '—';
    const parts = [];
    if (inW != null) { parts.push(`+${Math.round(inW)} W in`); }
    if (outW != null) { parts.push(`${Math.round(outW)} W out`); }
    return parts.join(' · ');
});

const ecoflowPowerClass = computed(() => {
    const inW = val('ecoflow_input_watts');
    const outW = val('ecoflow_output_watts');
    if (inW == null && outW == null) return '';
    if (inW != null && outW != null) { return inW > outW ? 'ops-chg' : 'ops-dis'; }
    return inW != null ? 'ops-chg' : 'ops-dis';
});

const ecoflowRemLabel = computed(() => {
    const inputW = val('ecoflow_input_watts');
    const outputW = val('ecoflow_output_watts');
    if (inputW != null && outputW != null) {
        return inputW > outputW ? 'to full' : 'remaining';
    }
    return 'remaining';
});

const ecoflowRemDisplay = computed(() => {
    const minutes = val('ecoflow_remain_time');
    if (minutes == null) return '—';
    const totalMin = Math.round(minutes);
    if (totalMin <= 0) return '—';
    const h = Math.floor(totalMin / 60);
    const m = totalMin % 60;
    if (h === 0) { return `${m} m`; }
    return `${h} h ${m.toString().padStart(2, '0')} m`;
});

// ── Tanks ─────────────────────────────────────────────────────────────────────
const fuelPctDisplay = computed(() => {
    const v = val('fuel_level');
    return v == null ? '—' : `${fmtInt(v)}%`;
});

const fuelDaysDisplay = computed(() => '—');

const waterPctDisplay = computed(() => {
    const v = val('water_fresh_level');
    return v == null ? '—' : `${fmtInt(v)}%`;
});

const waterDaysDisplay = computed(() => '—');

// ── Weather: multi-day forecast ───────────────────────────────────────────────
// WMO weather-code → emoji icon + short label. Ops has no shared wmo helpers,
// so map the codes locally (mirrors Weather::getConditionText/getWeatherSummary).
function wmoIcon(code) {
    const c = Number(code) || 0;
    if (c === 0 || c === 1) return '☀️';
    if (c === 2) return '⛅';
    if (c === 3) return '☁️';
    if (c === 45 || c === 48) return '🌫️';
    if (c >= 51 && c <= 57) return '🌦️';
    if ((c >= 61 && c <= 67) || (c >= 80 && c <= 82)) return '🌧️';
    if ((c >= 71 && c <= 77) || c === 85 || c === 86) return '🌨️';
    if (c >= 95) return '⛈️';
    return '❓';
}

function wmoLabel(code) {
    const c = Number(code) || 0;
    if (c === 0) return 'Clear';
    if (c === 1) return 'Mainly clear';
    if (c === 2) return 'Partly cloudy';
    if (c === 3) return 'Overcast';
    if (c === 45 || c === 48) return 'Fog';
    if (c >= 51 && c <= 57) return 'Drizzle';
    if ((c >= 61 && c <= 67)) return 'Rain';
    if (c >= 71 && c <= 77) return 'Snow';
    if (c >= 80 && c <= 82) return 'Showers';
    if (c === 85 || c === 86) return 'Snow showers';
    if (c >= 95) return 'Thunderstorm';
    return 'Unknown';
}

const forecastDays = computed(() => {
    return (props.forecast ?? []).map((day) => {
        const date = day.date ? new Date(`${day.date}T00:00:00`) : null;
        const hi = day.tempMax != null ? `${Math.round(day.tempMax)}°` : '—°';
        const lo = day.tempMin != null ? `${Math.round(day.tempMin)}°` : '—°';
        const wind = day.windMax != null ? `${Math.round(day.windMax)} kt` : '—';
        return {
            key: day.date,
            label: date ? date.toLocaleDateString(undefined, { weekday: 'short' }) : '—',
            icon: wmoIcon(day.code),
            title: wmoLabel(day.code),
            temp: `${hi}/${lo}`,
            wind,
        };
    });
});

// ── Weather: air ──────────────────────────────────────────────────────────────
const wxAirTempDisplay = computed(() => {
    const v = val('wx_air_temp');
    return v == null ? '—°' : `${fmtFixed(v, 0)}°`;
});

const windSpeedDisplay = computed(() => {
    const spd = val('wx_wind_speed');
    return spd == null ? '—' : `${fmtInt(spd)} kts`;
});

const windKtsShort = computed(() => {
    const spd = val('wx_wind_speed');
    return spd == null ? '—' : `${fmtInt(spd)} kt`;
});

const gustDisplay = computed(() => {
    const v = val('wx_wind_gust');
    return v == null ? '—' : `${fmtInt(v)} kts`;
});

const windDirDisplay = computed(() => {
    const v = val('wx_wind_dir');
    return fmtDeg(v);
});

const pressureDisplay = computed(() => {
    const v = val('wx_pressure');
    return v == null ? '—' : `${fmtInt(v)} hPa`;
});

const wxWindDisplay = computed(() => {
    const spd = val('wx_wind_speed');
    const dir = val('wx_wind_dir');
    if (spd == null && dir == null) return '—';
    const parts = [];
    if (spd != null) { parts.push(`${fmtInt(spd)} kts`); }
    if (dir != null) { parts.push(fmtDeg(dir)); }
    return parts.join(' · ');
});

// ── Weather: sea state ────────────────────────────────────────────────────────
const waveHeightDisplay = computed(() => {
    const v = val('sea_wave_height');
    return v == null ? '—' : `${fmtFixed(v)} m`;
});

const wavePeriodDisplay = computed(() => {
    const v = val('sea_wave_period');
    return v == null ? '—' : `${fmtFixed(v, 0)} s`;
});

const waveDirectionDisplay = computed(() => {
    const v = val('sea_wave_direction');
    return fmtDeg(v);
});

const seaCurrentDisplay = computed(() => {
    const spd = val('sea_current_speed');
    const dir = val('sea_current_dir');
    if (spd == null) return '—';
    const base = `${fmtFixed(spd)} kts`;
    return dir != null ? `${base} · ${fmtDeg(dir)}` : base;
});

// ── Position & navigation ─────────────────────────────────────────────────────
const coordDisplay = computed(() => {
    const track = props.gpsTrack;
    if (!track || track.length === 0) return '— · —';
    const last = track[track.length - 1];
    if (!last) return '— · —';
    const lat = last[0];
    const lon = last[1];
    if (lat == null || lon == null) return '— · —';
    const latD = Math.abs(lat).toFixed(3);
    const lonD = Math.abs(lon).toFixed(3);
    const latH = lat >= 0 ? 'N' : 'S';
    const lonH = lon >= 0 ? 'E' : 'W';
    return `${latD}°${latH} · ${lonD}°${lonH}`;
});

const sogDisplay = computed(() => {
    const v = val('speed_sog');
    return v == null ? '—' : `${fmtFixed(v)} kts`;
});

const headingDisplay = computed(() => fmtDeg(val('heading_true')));

const cogDisplay = computed(() => fmtDeg(val('cog')));

const depthDisplay = computed(() => {
    const v = val('depth_below_surface');
    return v == null ? '—' : `${fmtFixed(v)} m`;
});

const etaDisplay = computed(() => {
    const seconds = val('wp_ttg');
    if (seconds == null || seconds <= 0) return '—';
    const now = new Date();
    const eta = new Date(now.getTime() + seconds * 1000);
    return new Intl.DateTimeFormat('en-GB', { hour: '2-digit', minute: '2-digit' }).format(eta);
});
</script>

<style scoped>
/* ── Metric → data-explorer links ──────────────────────────────────────────── */
.metric-link {
    text-decoration: none;
    color: inherit;
    cursor: pointer;
    transition: opacity 0.12s ease;
}

.metric-link--block {
    display: block;
}

.metric-link:hover {
    opacity: 0.85;
}

/* ── Shell ─────────────────────────────────────────────────────────────────── */
.ops-dash {
    max-width: 1100px;
    font-family: 'DM Sans', sans-serif;
}

/* ── Status bar ────────────────────────────────────────────────────────────── */
.ops-status {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 18px;
    flex-wrap: wrap;
}

.ops-badge {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    background: oklch(0.92 0.05 150);
    color: oklch(0.28 0.1 150);
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 1px;
    text-transform: uppercase;
    padding: 5px 11px;
    border-radius: 20px;
}

.ops-badge__dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: oklch(0.45 0.16 150);
}

.ops-hdot {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 11px;
    font-weight: 600;
    color: var(--color-text-dim);
}

.ops-hdot::before {
    content: '';
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: oklch(0.45 0.16 150);
}

.ops-passage {
    margin-left: auto;
    font-size: 13px;
    font-weight: 700;
    color: var(--color-text-secondary);
}

/* ── Regions ───────────────────────────────────────────────────────────────── */
.ops-region {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: 14px;
    padding: 22px 26px;
    margin-bottom: 14px;
}

.ops-region:last-child {
    margin-bottom: 0;
}

/* Section label: block (region header) vs inline (sub-section within region) */
.ops-seclabel {
    display: block;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 2px;
    text-transform: uppercase;
    color: var(--color-text-dim);
    margin-bottom: 16px;
}

.ops-seclabel--inline {
    display: inline;
    margin-bottom: 0;
}

/* ── Region 1: Endurance ───────────────────────────────────────────────────── */
.ops-endgrid {
    display: grid;
    grid-template-columns: 1.55fr 1fr;
    gap: 36px;
    align-items: stretch;
}

.ops-power {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
}

.ops-sys {
    display: flex;
    flex-direction: column;
}

.ops-sys__header {
    min-height: 20px;
    margin-bottom: 8px;
}

.ops-sys__main {
    min-height: 46px;
    display: flex;
    align-items: flex-end;
    gap: 14px;
    margin-bottom: 5px;
}

.ops-sys__soc {
    font-family: 'Nunito Sans', sans-serif;
    font-weight: 700;
    font-size: 38px;
    letter-spacing: -1.5px;
    line-height: 0.85;
}

.ops-sys__soc--house { color: oklch(0.45 0.16 150); }
.ops-sys__soc--eco   { color: oklch(0.42 0.14 178); }

.ops-sys__rem {
    display: flex;
    flex-direction: column;
    line-height: 1.05;
    padding-bottom: 2px;
}

.ops-sys__rem-lab {
    font-size: 9px;
    font-weight: 700;
    letter-spacing: 0.6px;
    text-transform: uppercase;
    color: var(--color-text-dim);
    margin-bottom: 2px;
}

.ops-sys__rem-val {
    font-family: 'Nunito Sans', sans-serif;
    font-weight: 700;
    font-size: 18px;
    color: var(--color-text-primary);
}

.ops-sys__det {
    min-height: 18px;
    font-size: 11px;
    font-weight: 500;
    color: var(--color-text-dim);
    margin-bottom: 4px;
}

.ops-sys__det b {
    font-family: 'Nunito Sans', sans-serif;
    font-weight: 700;
    color: var(--color-text-secondary);
}

.ops-chg { color: oklch(0.34 0.11 150); font-weight: 600; }
.ops-dis { color: var(--color-scarlet); font-weight: 600; }

.ops-chart {
    flex: 1;
    background: var(--color-bg);
    border-radius: 8px;
    padding: 4px 6px 2px;
    overflow: hidden;
    margin-top: 4px;
}

.ops-chart__empty {
    height: 104px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    color: var(--color-text-dim);
}

.ops-axis {
    display: flex;
    justify-content: space-between;
    margin-top: 5px;
    font-size: 9px;
    font-weight: 600;
    color: var(--color-text-dim);
    padding: 0 4px;
}

/* Tanks */
.ops-tanks {
    padding-left: 36px;
    border-left: 1px solid var(--color-border);
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 24px;
}

.ops-tank__header {
    display: flex;
    align-items: baseline;
    gap: 8px;
    margin-bottom: 6px;
}

.ops-tank__label {
    font-size: 12px;
    font-weight: 500;
    color: var(--color-text-dim);
}

.ops-tank__pct {
    margin-left: auto;
    font-family: 'Nunito Sans', sans-serif;
    font-weight: 700;
    font-size: 24px;
}

.ops-tank__pct--fuel  { color: oklch(0.48 0.17 70); }
.ops-tank__pct--water { color: oklch(0.42 0.14 245); }

.ops-tank__days {
    font-family: 'Nunito Sans', sans-serif;
    font-weight: 700;
    font-size: 13px;
    color: var(--color-text-secondary);
}

.ops-tank__chart {
    background: var(--color-bg);
    border-radius: 6px;
    overflow: hidden;
}

.ops-tank__empty-wrap {
    position: relative;
}

.ops-tank__empty-svg {
    width: 100%;
    height: 54px;
    display: block;
}

.ops-tank__empty-label {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    color: var(--color-text-tertiary, var(--color-text-secondary));
    letter-spacing: 0.04em;
    pointer-events: none;
}

/* ── Region 2: Climate ─────────────────────────────────────────────────────── */
.ops-climate {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.ops-wnow {
    flex: none;
    display: flex;
    align-items: center;
    gap: 12px;
    padding-right: 24px;
    margin-right: 8px;
    border-right: 1px solid var(--color-border);
}

.ops-wnow__temp {
    font-family: 'Nunito Sans', sans-serif;
    font-weight: 700;
    font-size: 40px;
    letter-spacing: -2px;
    color: var(--color-text-primary);
    line-height: 0.85;
}

.ops-wnow__cond-main {
    font-size: 13px;
    font-weight: 700;
    color: var(--color-text-secondary);
}

.ops-wnow__cond-sub {
    font-size: 10px;
    font-weight: 500;
    color: var(--color-text-dim);
    margin-top: 3px;
}

/* ── Region 3: Conditions & Position ──────────────────────────────────────── */

/* Weather band */
.ops-wxband {
    display: grid;
    grid-template-columns: 1fr 1fr 1.4fr;
    gap: 32px;
    align-items: start;
    padding-bottom: 20px;
    margin-bottom: 20px;
    border-bottom: 1px solid var(--color-border);
}

.ops-wcol--sea,
.ops-wfc {
    padding-left: 32px;
    border-left: 1px solid var(--color-border);
}

.ops-wcl {
    font-size: 9px;
    font-weight: 800;
    letter-spacing: 1.2px;
    text-transform: uppercase;
    color: var(--color-text-dim);
    margin-bottom: 12px;
}

.ops-wcol__row {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    padding: 6px 0;
    border-bottom: 1px solid var(--color-border);
}

.ops-wcol__row:last-child {
    border-bottom: none;
}

.ops-wcol__l {
    font-size: 11px;
    font-weight: 500;
    color: var(--color-text-dim);
}

.ops-wcol__v {
    font-family: 'Nunito Sans', sans-serif;
    font-weight: 600;
    font-size: 15px;
    color: var(--color-text-primary);
}

.ops-wcol__v--amber { color: oklch(0.48 0.17 70); }
.ops-wcol__v--blue  { color: oklch(0.42 0.14 245); }

/* Forecast cells */
.ops-wfc__cells {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 10px;
}

.ops-wfc__p {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 5px;
}

.ops-wfc__ph {
    font-size: 9px;
    font-weight: 800;
    color: var(--color-text-dim);
}

.ops-wfc__pi {
    font-size: 20px;
    line-height: 1;
}

.ops-wfc__pt {
    font-family: 'Nunito Sans', sans-serif;
    font-weight: 700;
    font-size: 15px;
    color: var(--color-text-primary);
}

.ops-wfc__pt--dim {
    font-size: 15px;
    color: var(--color-text-dim);
    opacity: 0.45;
}

.ops-wfc__pw {
    font-size: 9px;
    font-weight: 600;
    color: oklch(0.48 0.17 70);
}

.ops-wfc__pw--dim {
    color: var(--color-text-dim);
    opacity: 0.35;
}

.ops-wfc__p--dim .ops-wfc__ph {
    opacity: 0.4;
}

/* Position band */
.ops-posband {
    display: grid;
    grid-template-columns: 1.8fr 0.85fr;
    gap: 32px;
    align-items: stretch;
}

.ops-cmap {
    min-height: 200px;
    border-radius: 14px;
    position: relative;
    overflow: hidden;
    border: 1px solid var(--color-border);
    background: linear-gradient(165deg, oklch(0.9 0.035 215), oklch(0.84 0.05 205));
}

/* Leaflet container fills the card */
.ops-cmap :deep(.leaflet-container) {
    width: 100%;
    height: 100%;
    min-height: 200px;
    border-radius: 14px;
    z-index: 0;
}

.ops-cmap__chip {
    position: absolute;
    z-index: 10;
    background: oklch(0.99 0.004 205 / 0.92);
    border: 1px solid var(--color-border);
    border-radius: 10px;
    padding: 6px 10px;
    box-shadow: 0 2px 8px oklch(0.2 0.02 205 / 0.1);
    font-size: 10px;
    font-weight: 700;
    color: var(--color-text-secondary);
    pointer-events: none;
}

.ops-cmap__chip--tr {
    top: 12px;
    right: 12px;
}

.ops-cmap__chip--bl {
    bottom: 12px;
    left: 12px;
}

/* Sailing & nav */
.ops-saildata {
    display: flex;
    flex-direction: column;
}

.ops-saildata > .ops-seclabel {
    display: block;
    margin-bottom: 10px;
}

.ops-sdrows {
    display: grid;
    grid-template-columns: 1fr;
}

.ops-sdrow {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    padding: 7px 0;
    border-bottom: 1px solid var(--color-border);
}

.ops-sdrow:last-child {
    border-bottom: none;
}

.ops-sdrow__l {
    font-size: 11px;
    font-weight: 500;
    color: var(--color-text-dim);
}

.ops-sdrow__v {
    font-family: 'Nunito Sans', sans-serif;
    font-weight: 600;
    font-size: 15px;
    color: var(--color-text-primary);
}

.ops-sdrow__v--teal  { color: oklch(0.42 0.14 178); }
.ops-sdrow__v--amber { color: oklch(0.48 0.17 70); }
.ops-sdrow__v--blue  { color: oklch(0.42 0.14 245); }

/* ── Stale state ───────────────────────────────────────────────────────────── */
.ops-stale {
    opacity: 0.45;
}

/* ── Responsive ────────────────────────────────────────────────────────────── */
@media (max-width: 960px) {
    .ops-endgrid,
    .ops-power,
    .ops-wxband,
    .ops-posband {
        grid-template-columns: 1fr;
    }

    .ops-tanks {
        padding-left: 0;
        border-left: none;
        border-top: 1px solid var(--color-border);
        padding-top: 20px;
    }

    .ops-wcol--sea,
    .ops-wfc {
        padding-left: 0;
        border-left: none;
        border-top: 1px solid var(--color-border);
        padding-top: 14px;
    }

    .ops-wnow {
        border-right: none;
        padding-right: 0;
        margin-right: 0;
        width: 100%;
        margin-bottom: 12px;
        padding-bottom: 12px;
        border-bottom: 1px solid var(--color-border);
    }

    .ops-passage {
        margin-left: 0;
        width: 100%;
    }

    .ops-wfc__cells {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 640px) {
    .ops-wfc__cells {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>
