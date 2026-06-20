<template>
    <AdminLayout :breadcrumbs="[{ label: 'Main' }]">
        <Head title="Main Dashboard" />

        <div class="mn-dash">
            <!-- ── Status bar ─────────────────────────────────────────────── -->
            <div class="mn-status">
                <span class="mn-badge">
                    <span class="mn-badge__dot"></span>
                    {{ statusText }}
                </span>
                <span class="mn-hdot" :class="{ 'mn-hdot--off': !trackerOnline }">Tracker</span>
                <span class="mn-hdot" :class="{ 'mn-hdot--off': !streamOnline }">Stream</span>
                <span class="mn-hdot" :class="{ 'mn-hdot--off': !publisherOnline }">Publisher</span>
                <span v-if="passageLabel" class="mn-passage">{{ passageLabel }}</span>
            </div>

            <!-- ── Hero row: map + instrument rail ───────────────────────── -->
            <div class="mn-herorow">

                <!-- MAP HERO -->
                <div class="mn-map">
                    <div ref="mapContainerRef" class="mn-map__leaflet"></div>

                    <!-- TL: Weather cluster -->
                    <div class="mn-cluster mn-cluster--weather">
                        <div class="mn-weather__top">
                            <svg width="28" height="28" viewBox="0 0 34 34" aria-hidden="true">
                                <circle cx="13" cy="13" r="7.5" fill="var(--color-amber)" opacity="0.85" />
                                <ellipse cx="21" cy="22" rx="11.5" ry="6.5" fill="var(--color-surface)" stroke="var(--color-border)" stroke-width="0.8" />
                            </svg>
                            <Link :href="route('admin.data.show', { metric: 'wx_air_temp' })" class="metric-link mn-weather__temp" :class="{ 'mn-stale': stale('wx_air_temp') }">{{ wxTempDisplay }}</Link>
                            <Link :href="route('admin.data.show', { metric: 'wx_wind_speed' })" class="metric-link mn-weather__wind" :class="{ 'mn-stale': stale('wx_wind_speed') }">
                                <span class="mn-seclabel">Wind</span>
                                {{ wxWindKtsDisplay }}
                            </Link>
                        </div>
                        <div class="mn-weather__sub">
                            <span :class="{ 'mn-stale': stale('wx_pressure') }"><b>{{ wxPressureDisplay }}</b> hPa</span>
                            <template v-if="val('wx_wind_gust') != null">
                                · G<b :class="{ 'mn-stale': stale('wx_wind_gust') }">{{ wxGustDisplay }}</b> kts
                            </template>
                        </div>
                    </div>

                    <!-- TR: Position -->
                    <div class="mn-cluster mn-cluster--tr">{{ coordDisplay }}</div>

                    <!-- BL: SOG headline + COG + HDG -->
                    <div class="mn-cluster mn-cluster--bl">
                        <Link :href="route('admin.data.show', { metric: 'speed_sog' })" class="metric-link mn-bl__k">
                            <span class="mn-seclabel" style="color: var(--color-teal)">Speed o.g.</span>
                            <span class="mn-bl__sog" :class="{ 'mn-stale': stale('speed_sog') }">{{ sogRawDisplay }}</span>
                        </Link>
                        <Link :href="route('admin.data.show', { metric: 'cog' })" class="metric-link mn-bl__k mn-bl__k--sm">
                            <span class="mn-seclabel">COG</span>
                            <span class="mn-num" style="color: var(--color-teal)" :class="{ 'mn-stale': stale('cog') }">{{ cogRawDisplay }}</span>
                        </Link>
                        <Link :href="route('admin.data.show', { metric: 'heading_true' })" class="metric-link mn-bl__k mn-bl__k--sm">
                            <span class="mn-seclabel">HDG</span>
                            <span class="mn-num" :class="{ 'mn-stale': stale('heading_true') }">{{ hdgRawDisplay }}</span>
                        </Link>
                    </div>

                    <!-- BR: Waypoint + TTG + ETA -->
                    <div class="mn-cluster mn-cluster--br">
                        <Link :href="route('admin.data.show', { metric: 'wp_distance' })" class="metric-link mn-br__k">
                            <span class="mn-seclabel">To waypoint</span>
                            <span class="mn-num" :class="{ 'mn-stale': stale('wp_distance') }">
                                {{ wpDistDisplay }}<span v-if="val('wp_distance') != null" class="mn-u">nm</span>
                            </span>
                        </Link>
                        <Link :href="route('admin.data.show', { metric: 'wp_ttg' })" class="metric-link mn-br__k">
                            <span class="mn-seclabel">TTG</span>
                            <span class="mn-num" :class="{ 'mn-stale': stale('wp_ttg') }">{{ ttgDisplay }}</span>
                        </Link>
                        <Link :href="route('admin.data.show', { metric: 'wp_ttg' })" class="metric-link mn-br__k">
                            <span class="mn-seclabel">ETA</span>
                            <span class="mn-num" :class="{ 'mn-stale': stale('wp_ttg') }">{{ etaDisplay }}</span>
                        </Link>
                    </div>

                    <div class="mn-attr">Tiles · OpenSeaMap</div>
                </div>

                <!-- INSTRUMENT RAIL -->
                <div class="mn-rail">

                    <!-- Compass rose + point-of-sail -->
                    <div class="mn-rail__compass">
                        <CompassRose
                            :heading="val('heading_true') ?? 0"
                            :cog="val('cog')"
                            :awa="val('wind_angle_apparent')"
                            :twa="compassTwa"
                            :size="196"
                        />
                        <div class="mn-rail__pos">
                            <div class="mn-rail__pos-name" :style="{ color: pointOfSailColor }">{{ pointOfSailText }}</div>
                            <div class="mn-rail__pos-sub">
                                <template v-if="val('wx_wind_speed') != null">
                                    Wind <b>{{ wxWindKtsDisplay }}</b>
                                </template>
                                <template v-else>—</template>
                            </div>
                        </div>
                    </div>

                    <!-- Speed through water + SOG/STW delta + sparkline -->
                    <div class="mn-rail__gblock mn-rail__gblock--speed">
                        <div class="mn-rail__gblock-top">
                            <Link :href="route('admin.data.show', { metric: 'speed_stw' })" class="metric-link mn-rail__stw">
                                <span class="mn-seclabel">Speed thru water</span>
                                <span class="mn-num mn-rail__stw-num" :class="{ 'mn-stale': stale('speed_stw') }">
                                    {{ stwDisplay }}<span class="mn-u">kts</span>
                                </span>
                            </Link>
                            <div v-if="sogStwDelta != null" class="mn-rail__delta">
                                <div class="mn-rail__delta-dn">{{ sogStwDeltaDisplay }}</div>
                                <div v-if="tideFairFoul" class="mn-rail__delta-dl">{{ tideFairFoul }}</div>
                            </div>
                        </div>
                        <Link
                            v-if="speedHistory.length > 0"
                            :href="route('admin.data.show', { metric: 'speed_stw' })"
                            class="metric-link metric-link--block"
                        >
                            <TrendChart
                                :data="speedHistory"
                                variant="area"
                                color="var(--color-teal)"
                                :height="66"
                            />
                        </Link>
                        <div v-else class="mn-rail__empty">No Data</div>
                        <div class="mn-axis">
                            <span>1 h ago</span>
                            <span>now</span>
                        </div>
                    </div>

                    <!-- Depth water column + sparkline -->
                    <div class="mn-rail__gblock mn-rail__gblock--depth">
                        <div class="mn-rail__gblock-top">
                            <Link :href="route('admin.data.show', { metric: 'depth_below_surface' })" class="metric-link mn-rail__depth-head">
                                <span class="mn-seclabel">Depth under keel</span>
                                <span class="mn-num mn-rail__depth-num" :class="{ 'mn-stale': stale('depth_below_surface') }">
                                    {{ depthDisplay }}<span v-if="val('depth_below_surface') != null" class="mn-u">m</span>
                                </span>
                            </Link>
                        </div>
                        <Link
                            v-if="depthHistory.length > 0"
                            :href="route('admin.data.show', { metric: 'depth_below_surface' })"
                            class="metric-link metric-link--block"
                        >
                            <TrendChart
                                :data="depthHistory"
                                variant="water"
                                color="var(--color-blue)"
                                :height="66"
                            />
                        </Link>
                        <div v-else class="mn-rail__empty">No Data</div>
                        <div class="mn-axis">
                            <span>3 h ago</span>
                            <span>now</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Footer: House Battery + Fuel + Water ───────────────────── -->
            <div class="mn-resources">
                <!-- House Battery (bipolar: signed watts) -->
                <div class="mn-res">
                    <div class="mn-res__top">
                        <span class="mn-seclabel">House Battery</span>
                        <span class="mn-res__vals">
                            <span class="mn-res__pct mn-res__pct--g" :class="{ 'mn-stale': stale('house_battery_soc') }">{{ houseSocDisplay }}</span>
                            <span class="mn-res__sub" :class="{ 'mn-stale': stale('house_battery_voltage') }">
                                {{ houseVoltDisplay }} V<template v-if="housePowerLast != null"> · {{ housePowerSignDisplay }} W</template>
                            </span>
                        </span>
                    </div>
                    <TrendChart
                        v-if="housePowerHistory.length > 0"
                        :data="housePowerHistory"
                        variant="bipolar"
                        color-positive="var(--color-green)"
                        color-negative="var(--color-scarlet)"
                        :height="52"
                    />
                    <div v-else class="mn-res__empty">No Data</div>
                    <div class="mn-axis">
                        <span>6 h ago</span>
                        <span>now</span>
                    </div>
                </div>

                <!-- Fuel -->
                <div class="mn-res">
                    <Link :href="route('admin.data.show', { metric: 'fuel_level' })" class="metric-link mn-res__top">
                        <span class="mn-seclabel">Fuel</span>
                        <span class="mn-res__vals">
                            <span class="mn-res__pct mn-res__pct--a" :class="{ 'mn-stale': stale('fuel_level') }">{{ fuelPctDisplay }}</span>
                        </span>
                    </Link>
                    <Link
                        v-if="fuelHistory.length > 0"
                        :href="route('admin.data.show', { metric: 'fuel_level' })"
                        class="metric-link metric-link--block"
                    >
                        <TrendChart
                            :data="fuelHistory"
                            variant="area"
                            color="var(--color-amber)"
                            :height="52"
                        />
                    </Link>
                    <div v-else class="mn-res__empty">No Data</div>
                    <div class="mn-axis">
                        <span>24 h ago</span>
                        <span>now</span>
                    </div>
                </div>

                <!-- Fresh Water -->
                <div class="mn-res">
                    <Link :href="route('admin.data.show', { metric: 'water_fresh_level' })" class="metric-link mn-res__top">
                        <span class="mn-seclabel">Fresh Water</span>
                        <span class="mn-res__vals">
                            <span class="mn-res__pct mn-res__pct--b" :class="{ 'mn-stale': stale('water_fresh_level') }">{{ waterPctDisplay }}</span>
                        </span>
                    </Link>
                    <Link
                        v-if="waterHistory.length > 0"
                        :href="route('admin.data.show', { metric: 'water_fresh_level' })"
                        class="metric-link metric-link--block"
                    >
                        <TrendChart
                            :data="waterHistory"
                            variant="area"
                            color="var(--color-blue)"
                            :height="52"
                        />
                    </Link>
                    <div v-else class="mn-res__empty">No Data</div>
                    <div class="mn-axis">
                        <span>24 h ago</span>
                        <span>now</span>
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
import CompassRose from '@/components/Admin/CompassRose.vue';
import TrendChart from '@/components/Admin/TrendChart.vue';
import { useScarletMetrics } from '@/composables/useScarletMetrics.js';
import { pointOfSail, trueWindAngleSigned } from '@/lib/pointOfSail';

const props = defineProps({
    contracts: { type: Object, default: () => ({}) },
    housePowerHistory: { type: Array, default: () => [] },
    fuelHistory: { type: Array, default: () => [] },
    waterHistory: { type: Array, default: () => [] },
    depthHistory: { type: Array, default: () => [] },
    speedHistory: { type: Array, default: () => [] },
    gps: { type: Object, default: () => ({}) },
    gpsTrack: { type: Array, default: () => [] },
    reverb: { type: Object, default: null },
    reverbKey: { type: String, default: null },
});

// ── Map ref ───────────────────────────────────────────────────────────────────
const mapContainerRef = ref(null);
let map = null;

// ── Live updates via Echo ─────────────────────────────────────────────────────
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

// Merge SSR props with live canonical updates
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
        map = initMap(mapContainerRef.value, { interactive: true });
        addMapTarget(map, {});
    }
});

onUnmounted(() => {
    map?.remove();
    map = null;
});

// ── Passage label ─────────────────────────────────────────────────────────────
const passageLabel = computed(() => {
    if (passageFrom.value && passageTo.value) {
        return `${passageFrom.value} → ${passageTo.value}`;
    }
    if (passageFrom.value) return `From ${passageFrom.value}`;
    if (passageTo.value) return `To ${passageTo.value}`;
    return '';
});

// ── GPS position display ──────────────────────────────────────────────────────
const coordDisplay = computed(() => {
    const track = props.gpsTrack;
    if (!track || track.length === 0) return '— · —';
    const last = track[track.length - 1];
    if (!last) return '— · —';
    const lat = last[0];
    const lon = last[1];
    if (lat == null || lon == null) return '— · —';
    const latD = Math.abs(lat).toFixed(4);
    const lonD = Math.abs(lon).toFixed(4);
    const latH = lat >= 0 ? 'N' : 'S';
    const lonH = lon >= 0 ? 'E' : 'W';
    return `${latD}°${latH} · ${lonD}°${lonH}`;
});

// ── Speed ─────────────────────────────────────────────────────────────────────
/** Raw SOG value for the large BL cluster (number only, no unit suffix) */
const sogRawDisplay = computed(() => fmtFixed(val('speed_sog')));
const stwDisplay = computed(() => fmtFixed(val('speed_stw')));

/** COG and HDG in degrees */
const cogRawDisplay = computed(() => fmtDeg(val('cog')));
const hdgRawDisplay = computed(() => fmtDeg(val('heading_true')));

// ── SOG/STW delta ─────────────────────────────────────────────────────────────
const sogStwDelta = computed(() => {
    const sog = val('speed_sog');
    const stw = val('speed_stw');
    if (sog == null || stw == null) return null;
    return sog - stw;
});

const sogStwDeltaDisplay = computed(() => {
    const d = sogStwDelta.value;
    if (d == null) return '—';
    const sign = d >= 0 ? '+' : '−';
    return `${sign}${fmtFixed(Math.abs(d))} kt`;
});

// ── Tide fair/foul (derived from current_set_true vs COG — real values) ───────
const tideFairFoul = computed(() => {
    const set = val('current_set_true');
    const cog = val('cog');
    if (set == null || cog == null) return '';
    const diff = Math.abs(((set - cog) + 360) % 360);
    const aligned = diff < 90 || diff > 270;
    return aligned ? 'fair tide ▲' : 'foul tide ▼';
});

// ── Point of sail (canonical: true wind, apparent fallback — shared module) ──
const compassTwa = computed(() => trueWindAngleSigned(val('wind_direction_true'), val('heading_true')));
const pos = computed(() => pointOfSail({
    windDirectionTrue: val('wind_direction_true'),
    headingTrue: val('heading_true'),
    windAngleApparent: val('wind_angle_apparent'),
}));
const pointOfSailText = computed(() => pos.value.text);
const pointOfSailColor = computed(() => pos.value.color);

// ── Weather ───────────────────────────────────────────────────────────────────
const wxTempDisplay = computed(() => {
    const v = val('wx_air_temp');
    return v == null ? '—°' : `${fmtFixed(v, 0)}°`;
});

const wxWindKtsDisplay = computed(() => {
    const v = val('wx_wind_speed');
    return v == null ? '—' : `${fmtInt(v)} kts`;
});

const wxGustDisplay = computed(() => fmtInt(val('wx_wind_gust')));

const wxPressureDisplay = computed(() => {
    const v = val('wx_pressure');
    return v == null ? '—' : fmtInt(v);
});

// ── Waypoint / TTG / ETA ─────────────────────────────────────────────────────
const wpDistDisplay = computed(() => {
    const v = val('wp_distance');
    return v == null ? '—' : fmtFixed(v);
});

const ttgDisplay = computed(() => {
    const seconds = val('wp_ttg');
    if (seconds == null || seconds <= 0) return '—';
    const totalMin = Math.round(seconds / 60);
    if (totalMin < 60) return `${totalMin} min`;
    const h = Math.floor(totalMin / 60);
    const m = totalMin % 60;
    return `${h} h ${m.toString().padStart(2, '0')} m`;
});

const etaDisplay = computed(() => {
    const seconds = val('wp_ttg');
    if (seconds == null || seconds <= 0) return '—';
    const now = new Date();
    const eta = new Date(now.getTime() + seconds * 1000);
    return new Intl.DateTimeFormat('en-GB', { hour: '2-digit', minute: '2-digit' }).format(eta);
});

// ── Depth ─────────────────────────────────────────────────────────────────────
const depthDisplay = computed(() => fmtFixed(val('depth_below_surface')));

// ── House Battery ─────────────────────────────────────────────────────────────
const houseSocDisplay = computed(() => {
    const v = val('house_battery_soc');
    return v == null ? '—%' : `${fmtInt(v)}%`;
});

const houseVoltDisplay = computed(() => {
    const v = val('house_battery_voltage');
    return v == null ? '—' : Number(v).toFixed(1);
});

const housePowerLast = computed(() => {
    const hist = props.housePowerHistory;
    if (!hist || hist.length === 0) return null;
    const last = hist[hist.length - 1];
    const w = typeof last === 'object' ? last.v : last;
    return w ?? null;
});

const housePowerSignDisplay = computed(() => {
    const w = housePowerLast.value;
    if (w == null) return '—';
    const abs = Math.abs(w).toFixed(0);
    return w >= 0 ? `+${abs}` : `−${abs}`;
});

// ── Tanks ─────────────────────────────────────────────────────────────────────
const fuelPctDisplay = computed(() => {
    const v = val('fuel_level');
    return v == null ? '—' : `${fmtInt(v)}%`;
});

const waterPctDisplay = computed(() => {
    const v = val('water_fresh_level');
    return v == null ? '—' : `${fmtInt(v)}%`;
});

// ── Service pill online state ─────────────────────────────────────────────────
const trackerOnline = computed(() =>
    val('tracker_battery_voltage') != null && !stale('tracker_battery_voltage'),
);

const streamOnline = computed(() =>
    !!val('srt_up') && !stale('srt_up'),
);

const publisherOnline = computed(() =>
    !!val('srt_pub_connected') && !stale('srt_pub_connected'),
);
</script>

<style scoped>
/* ── Metric → data-explorer links ───────────────────────────────────────────── */
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

/* ── Shell ──────────────────────────────────────────────────────────────────── */
.mn-dash {
    max-width: 1100px;
    margin: 0 auto;
    font-family: 'DM Sans', sans-serif;
}

/* ── Status bar ─────────────────────────────────────────────────────────────── */
.mn-status {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 18px;
    flex-wrap: wrap;
}

.mn-badge {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    background: var(--color-green-bg);
    color: var(--color-green);
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 1px;
    text-transform: uppercase;
    padding: 5px 11px;
    border-radius: 20px;
}

.mn-badge__dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: var(--color-green);
}

.mn-hdot {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 11px;
    font-weight: 600;
    color: var(--color-text-dim);
}

.mn-hdot::before {
    content: '';
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: var(--color-green);
}

.mn-hdot--off::before {
    background: var(--color-text-dim);
    opacity: 0.45;
}

.mn-passage {
    margin-left: auto;
    font-size: 13px;
    font-weight: 700;
    color: var(--color-text-dim);
}

/* ── Hero row ───────────────────────────────────────────────────────────────── */
.mn-herorow {
    display: grid;
    grid-template-columns: 2.1fr minmax(280px, 1fr);
    gap: 16px;
    align-items: stretch;
}

/* ── Map hero ───────────────────────────────────────────────────────────────── */
.mn-map {
    position: relative;
    height: 100%;
    min-height: 440px;
    border-radius: 16px;
    overflow: hidden;
    border: 1px solid var(--color-border);
    background: linear-gradient(165deg, var(--color-blue-bg), var(--color-surface) 55%, var(--color-blue-bg));
}

.mn-map::after {
    content: '';
    position: absolute;
    inset: 0;
    pointer-events: none;
    z-index: 1;
    background: radial-gradient(120% 90% at 50% 18%, transparent 55%, color-mix(in oklab, var(--color-blue) 16%, transparent));
}

.mn-map__leaflet {
    position: absolute;
    inset: 0;
}

.mn-map :deep(.leaflet-container) {
    width: 100%;
    height: 100%;
    border-radius: 16px;
    background: transparent;
    z-index: 0;
}

/* ── Corner clusters ────────────────────────────────────────────────────────── */
.mn-cluster {
    position: absolute;
    z-index: 2;
    background: color-mix(in oklab, var(--color-surface) 92%, transparent);
    border: 1px solid var(--color-border);
    border-radius: 12px;
    padding: 9px 13px;
    box-shadow: var(--shadow-sm);
}

/* TL — weather */
.mn-cluster--weather {
    top: 14px;
    left: 14px;
    min-width: 140px;
}

.mn-weather__top {
    display: flex;
    align-items: center;
    gap: 8px;
}

.mn-weather__temp {
    font-family: 'Nunito Sans', sans-serif;
    font-weight: 700;
    font-size: 26px;
    letter-spacing: -1px;
    color: var(--color-text-primary);
    line-height: 1;
}

.mn-weather__wind {
    display: flex;
    flex-direction: column;
    gap: 1px;
    font-size: 12px;
    font-weight: 600;
    color: var(--color-text-dim);
}

.mn-weather__sub {
    margin-top: 5px;
    font-size: 10px;
    font-weight: 600;
    color: var(--color-text-dim);
}

.mn-weather__sub b {
    font-family: 'Nunito Sans', sans-serif;
    font-weight: 700;
    color: var(--color-text-primary);
}

/* TR — position */
.mn-cluster--tr {
    top: 14px;
    right: 14px;
    font-family: 'Nunito Sans', sans-serif;
    font-weight: 700;
    font-size: 12px;
    color: var(--color-text-primary);
    white-space: nowrap;
}

/* BL — SOG + COG + HDG */
.mn-cluster--bl {
    bottom: 14px;
    left: 14px;
    display: flex;
    gap: 22px;
    align-items: flex-end;
}

.mn-bl__k {
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.mn-bl__sog {
    font-family: 'Nunito Sans', sans-serif;
    font-weight: 700;
    font-size: 46px;
    line-height: 0.9;
    letter-spacing: -1.5px;
    color: var(--color-teal);
}

.mn-bl__k--sm .mn-num {
    font-size: 20px;
}

/* BR — Waypoint + TTG + ETA */
.mn-cluster--br {
    bottom: 14px;
    right: 14px;
    display: flex;
    gap: 20px;
    align-items: flex-end;
}

.mn-br__k {
    display: flex;
    flex-direction: column;
    gap: 3px;
}

/* Map attribution */
.mn-attr {
    position: absolute;
    bottom: 4px;
    right: 14px;
    z-index: 3;
    font-size: 9px;
    color: var(--color-text-dim);
    opacity: 0.6;
    pointer-events: none;
}

/* ── Shared number / label tokens ───────────────────────────────────────────── */
.mn-seclabel {
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 2px;
    text-transform: uppercase;
    color: var(--color-text-dim);
    line-height: 1;
}

.mn-num {
    font-family: 'Nunito Sans', sans-serif;
    font-weight: 700;
    color: var(--color-text-primary);
    line-height: 0.95;
    letter-spacing: -1px;
    font-size: 20px;
}

.mn-u {
    font-size: 11px;
    font-weight: 500;
    color: var(--color-text-dim);
    margin-left: 3px;
    letter-spacing: 0;
}

.mn-axis {
    display: flex;
    justify-content: space-between;
    margin-top: 6px;
    font-size: 9.5px;
    font-weight: 600;
    color: var(--color-text-dim);
    letter-spacing: 0.3px;
}

/* ── Instrument rail ────────────────────────────────────────────────────────── */
.mn-rail {
    display: flex;
    flex-direction: column;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: 16px;
    padding: 18px 20px;
}

/* Compass section */
.mn-rail__compass {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding-bottom: 14px;
    border-bottom: 1px solid var(--color-border-light);
}

.mn-rail__pos {
    margin-top: 9px;
    text-align: center;
}

.mn-rail__pos-name {
    font-size: 16px;
    font-weight: 800;
    letter-spacing: 0.5px;
    color: var(--color-amber);
}

.mn-rail__pos-sub {
    margin-top: 3px;
    font-size: 10.5px;
    font-weight: 600;
    color: var(--color-text-dim);
}

.mn-rail__pos-sub b {
    font-family: 'Nunito Sans', sans-serif;
    font-weight: 700;
    color: var(--color-text-primary);
}

/* gblock: speed + depth sections */
.mn-rail__gblock {
    padding: 15px 0;
    border-bottom: 1px solid var(--color-border-light);
}

.mn-rail__gblock:last-child {
    border-bottom: none;
    padding-bottom: 2px;
}

.mn-rail__gblock-top {
    display: flex;
    align-items: flex-end;
    gap: 14px;
    margin-bottom: 0;
}

.mn-rail__gblock-top .mn-seclabel {
    display: block;
    margin-bottom: 5px;
}

/* Speed through water */
.mn-rail__stw {
    display: flex;
    flex-direction: column;
}

.mn-rail__stw-num {
    font-size: 32px;
    color: var(--color-text-primary);
}

/* SOG/STW delta */
.mn-rail__delta {
    margin-left: auto;
    text-align: right;
}

.mn-rail__delta-dn {
    font-family: 'Nunito Sans', sans-serif;
    font-weight: 800;
    font-size: 18px;
    color: var(--color-green);
    letter-spacing: -0.5px;
}

.mn-rail__delta-dl {
    font-size: 8.5px;
    font-weight: 700;
    letter-spacing: 0.6px;
    text-transform: uppercase;
    color: var(--color-text-dim);
}

/* Depth */
.mn-rail__depth-head {
    display: flex;
    flex-direction: column;
}

.mn-rail__depth-num {
    font-size: 28px;
    color: var(--color-blue);
    margin-left: auto;
}

/* Empty states */
.mn-rail__empty {
    position: relative;
    height: 66px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    letter-spacing: 0.04em;
    color: var(--color-text-tertiary, var(--color-text-dim));
}

.mn-rail__empty::before {
    content: '';
    position: absolute;
    left: 4px;
    right: 4px;
    top: 64%;
    border-top: 1px dashed var(--color-border-light);
}

/* ── Footer resources ───────────────────────────────────────────────────────── */
.mn-resources {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 30px;
    margin-top: 16px;
    padding-top: 18px;
    border-top: 1px solid var(--color-border-light);
}

.mn-res__top {
    display: flex;
    align-items: baseline;
    gap: 14px;
    margin-bottom: 8px;
}

.mn-res__vals {
    margin-left: auto;
    font-family: 'Nunito Sans', sans-serif;
    font-weight: 700;
    font-size: 15px;
    color: var(--color-text-dim);
    letter-spacing: -0.3px;
}

.mn-res__pct {
    font-size: 22px;
}

.mn-res__pct--g { color: var(--color-green); }
.mn-res__pct--a { color: var(--color-amber); }
.mn-res__pct--b { color: var(--color-blue); }

.mn-res__sub {
    font-size: 11px;
    font-weight: 500;
    color: var(--color-text-dim);
    margin-left: 5px;
    letter-spacing: 0;
}

.mn-res__empty {
    position: relative;
    height: 52px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    letter-spacing: 0.04em;
    color: var(--color-text-tertiary, var(--color-text-dim));
}

.mn-res__empty::before {
    content: '';
    position: absolute;
    left: 4px;
    right: 4px;
    top: 64%;
    border-top: 1px dashed var(--color-border-light);
}

/* ── Stale state ────────────────────────────────────────────────────────────── */
.mn-stale {
    opacity: 0.45;
}

/* ── Responsive stack at <880px ─────────────────────────────────────────────── */
@media (max-width: 880px) {
    .mn-herorow {
        grid-template-columns: 1fr;
    }

    .mn-map {
        min-height: 300px;
        height: 300px;
        order: -1;
    }

    .mn-resources {
        grid-template-columns: 1fr;
        gap: 18px;
    }

    .mn-passage {
        margin-left: 0;
        width: 100%;
    }
}
</style>
