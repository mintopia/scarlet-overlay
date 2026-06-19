<template>
    <AdminLayout :breadcrumbs="[{ label: 'Skipper' }]">
        <Head title="Skipper Dashboard" />

        <div class="sk-dash">
            <!-- Status bar -->
            <div class="sk-status">
                <span class="sk-badge">
                    <span class="sk-badge__dot"></span>
                    {{ statusText }}
                </span>
                <span class="sk-hdot">Tracker</span>
                <span class="sk-hdot">SignalK</span>
                <span class="sk-hdot">Autopilot</span>
                <span v-if="passageLabel" class="sk-passage">{{ passageLabel }}</span>
            </div>

            <!-- HERO: compass / point-of-sail + dense sailing block -->
            <div class="sk-hero">
                <!-- Left: compass rose + point-of-sail readout -->
                <div class="sk-cwrap">
                    <CompassRose
                        :heading="val('heading_true') ?? 0"
                        :cog="val('cog')"
                        :awa="val('wind_angle_apparent')"
                        :size="252"
                    />
                    <div class="sk-pos">
                        <div class="sk-pos__name" :style="{ color: pointOfSailColor }">{{ pointOfSailText }}</div>
                        <div class="sk-pos__sub">
                            AWA <b>{{ awaDisplay }}</b>
                            <template v-if="val('wind_speed_apparent') != null"> · TWS <b>{{ twsDisplay }}</b> kts</template>
                        </div>
                    </div>
                </div>

                <!-- Right: speed cluster + tide + lower (graph + instrument list) -->
                <div class="sk-sail">
                    <!-- Speed head + tidal set/drift -->
                    <div class="sk-speed-head">
                        <div class="sk-k sk-k--sog">
                            <span class="sk-seclabel">SOG</span>
                            <span class="sk-num sk-num--sog" :class="{ 'sk-stale': stale('speed_sog') }">
                                {{ sogDisplay }}<span class="sk-u">kts</span>
                            </span>
                        </div>
                        <div class="sk-k sk-k--stw">
                            <span class="sk-seclabel">STW</span>
                            <span class="sk-num sk-num--stw" :class="{ 'sk-stale': stale('speed_stw') }">
                                {{ stwDisplay }}<span class="sk-u">kts</span>
                            </span>
                        </div>
                        <div class="sk-k sk-k--vmg">
                            <span class="sk-seclabel">VMG → WP</span>
                            <span class="sk-num sk-num--vmg" :class="{ 'sk-stale': stale('vmg') }">
                                {{ vmgDisplay }}<span class="sk-u">kts</span>
                            </span>
                        </div>
                        <div class="sk-tide">
                            <div class="sk-tide__delta">
                                <div class="sk-tide__dn" :class="{ 'sk-stale': stale('current_drift') }">{{ tideDeltaDisplay }}</div>
                                <div class="sk-tide__dl">{{ tideFairFoul }}</div>
                            </div>
                            <div class="sk-tide__setdrift" :class="{ 'sk-stale': stale('current_set_true') }">
                                Tide set <b>{{ tideSetDisplay }}</b> · drift <b>{{ tideDriftDisplay }}</b> kt
                            </div>
                        </div>
                    </div>

                    <!-- Lower: speed graph beside instrument list -->
                    <div class="sk-lower">
                        <div class="sk-graphwrap">
                            <TrendChart
                                v-if="speedHistory.length > 0"
                                :data="speedHistory"
                                variant="line"
                                color="var(--color-teal)"
                                :height="80"
                            />
                            <div v-else class="sk-graph-empty">No speed history</div>
                            <div class="sk-axis">
                                <span>SOG · 6 h</span>
                                <span>now</span>
                            </div>
                        </div>
                        <div class="sk-instlist">
                            <div class="sk-ir">
                                <span class="sk-ir__l">True Wind</span>
                                <span class="sk-ir__v sk-ir__v--amber" :class="{ 'sk-stale': stale('wind_speed_apparent') }">
                                    {{ twsDisplay }}<span class="sk-u">kts</span>
                                </span>
                            </div>
                            <div class="sk-ir">
                                <span class="sk-ir__l">App. Wind Angle</span>
                                <span class="sk-ir__v" :class="{ 'sk-stale': stale('wind_angle_apparent') }">
                                    {{ awaDisplay }}<span v-if="val('wind_angle_apparent') != null" class="sk-u">°</span>
                                </span>
                            </div>
                            <div class="sk-ir">
                                <span class="sk-ir__l">Heel</span>
                                <span class="sk-ir__v" :class="{ 'sk-stale': stale('heel') }">
                                    {{ heelDisplay }}<span v-if="val('heel') != null" class="sk-u">°</span>
                                </span>
                            </div>
                            <div class="sk-ir">
                                <span class="sk-ir__l">Pitch</span>
                                <span class="sk-ir__v" :class="{ 'sk-stale': stale('pitch') }">
                                    {{ pitchDisplay }}<span v-if="val('pitch') != null" class="sk-u">°</span>
                                </span>
                            </div>
                            <div class="sk-ir">
                                <span class="sk-ir__l">Rate of turn</span>
                                <span class="sk-ir__v" :class="{ 'sk-stale': stale('rate_of_turn') }">
                                    {{ rotDisplay }}<span v-if="val('rate_of_turn') != null" class="sk-u">°/min</span>
                                </span>
                            </div>
                            <div class="sk-ir">
                                <span class="sk-ir__l">Trip log</span>
                                <span class="sk-ir__v">—<span class="sk-u">nm</span></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECONDARY: contoured chart + nav panel -->
            <div class="sk-secondary">
                <!-- Map -->
                <div class="sk-map">
                    <div ref="mapContainerRef" class="sk-map__leaflet"></div>
                    <!-- Corner clusters -->
                    <div class="sk-cluster sk-cluster--tr">{{ coordDisplay }}</div>
                    <div class="sk-cluster sk-cluster--xte">
                        <span class="sk-seclabel">Cross-track</span>
                        <span class="sk-num sk-num--xte" :class="{ 'sk-stale': stale('xte') }">
                            {{ xteDisplay }}
                        </span>
                    </div>
                    <div class="sk-cluster sk-cluster--br">
                        <div class="sk-cluster__k">
                            <span class="sk-seclabel">To WP</span>
                            <span class="sk-num sk-num--sm" :class="{ 'sk-stale': stale('wp_distance') }">
                                {{ wpDistDisplay }}<span v-if="val('wp_distance') != null" class="sk-u">nm</span>
                            </span>
                        </div>
                        <div class="sk-cluster__k">
                            <span class="sk-seclabel">ETA</span>
                            <span class="sk-num sk-num--sm" :class="{ 'sk-stale': stale('wp_ttg') }">{{ etaDisplay }}</span>
                        </div>
                    </div>
                    <div class="sk-attr">Tiles · OpenSeaMap</div>
                </div>

                <!-- Nav panel + autopilot strip -->
                <div class="sk-navpanel">
                    <!-- Autopilot strip -->
                    <div class="sk-ap" :class="apStateClass">
                        <span class="sk-ap__dot"></span>
                        <span class="sk-ap__txt">Autopilot · {{ apStateDisplay }}</span>
                        <span class="sk-ap__rud">
                            Rudder <b>{{ rudderDisplay }}</b>
                        </span>
                        <span class="sk-ap__hdg" :class="{ 'sk-stale': stale('heading_true') }">
                            {{ headingTDisplay }}<span class="sk-u">°</span>
                        </span>
                    </div>

                    <!-- Nav grid -->
                    <div class="sk-navgrid">
                        <div class="sk-nr">
                            <span class="sk-nr__l">Heading (T)</span>
                            <span class="sk-nr__v sk-nr__v--teal" :class="{ 'sk-stale': stale('heading_true') }">{{ headingTDisplay }}°</span>
                        </div>
                        <div class="sk-nr">
                            <span class="sk-nr__l">Heading (M)</span>
                            <span class="sk-nr__v" :class="{ 'sk-stale': stale('heading_magnetic') }">{{ headingMDisplay }}°</span>
                        </div>
                        <div class="sk-nr">
                            <span class="sk-nr__l">COG</span>
                            <span class="sk-nr__v sk-nr__v--teal" :class="{ 'sk-stale': stale('cog') }">{{ cogDisplay }}°</span>
                        </div>
                        <div class="sk-nr">
                            <span class="sk-nr__l">Variation</span>
                            <span class="sk-nr__v" :class="{ 'sk-stale': stale('magnetic_variation') }">{{ variationDisplay }}</span>
                        </div>
                        <div class="sk-nr">
                            <span class="sk-nr__l">Rate of turn</span>
                            <span class="sk-nr__v" :class="{ 'sk-stale': stale('rate_of_turn') }">{{ rotNavDisplay }}</span>
                        </div>
                        <div class="sk-nr">
                            <span class="sk-nr__l">Bearing → WP</span>
                            <span class="sk-nr__v" :class="{ 'sk-stale': stale('bearing_to_wp_true') }">{{ bearingWpDisplay }}</span>
                        </div>
                        <div class="sk-nr">
                            <span class="sk-nr__l">Track bearing</span>
                            <span class="sk-nr__v" :class="{ 'sk-stale': stale('track_bearing_true') }">{{ trackBearingDisplay }}</span>
                        </div>
                        <div class="sk-nr">
                            <span class="sk-nr__l">XTE</span>
                            <span class="sk-nr__v sk-nr__v--amber" :class="{ 'sk-stale': stale('xte') }">{{ xteNavDisplay }}</span>
                        </div>
                        <div class="sk-nr">
                            <span class="sk-nr__l">Dist → WP</span>
                            <span class="sk-nr__v" :class="{ 'sk-stale': stale('wp_distance') }">{{ wpDistNavDisplay }}</span>
                        </div>
                        <div class="sk-nr">
                            <span class="sk-nr__l">TTG</span>
                            <span class="sk-nr__v" :class="{ 'sk-stale': stale('wp_ttg') }">{{ ttgDisplay }}</span>
                        </div>
                        <div class="sk-nr">
                            <span class="sk-nr__l">VMG → WP</span>
                            <span class="sk-nr__v sk-nr__v--teal" :class="{ 'sk-stale': stale('vmg') }">{{ vmgNavDisplay }}</span>
                        </div>
                        <div class="sk-nr">
                            <span class="sk-nr__l">Trip log</span>
                            <span class="sk-nr__v">—</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SAFETY: depth + pressure trends -->
            <div class="sk-safety">
                <div class="sk-gblock">
                    <div class="sk-gblock__top">
                        <span class="sk-seclabel">Depth below surface</span>
                        <span class="sk-num sk-num--depth" :class="{ 'sk-stale': stale('depth_below_surface') }">
                            {{ depthDisplay }}<span v-if="val('depth_below_surface') != null" class="sk-u">m</span>
                        </span>
                    </div>
                    <TrendChart
                        v-if="depthHistory.length > 0"
                        :data="depthHistory"
                        variant="water"
                        color="var(--color-blue)"
                        :height="78"
                    />
                    <div v-else class="sk-chart-empty">No depth history</div>
                    <div class="sk-axis">
                        <span>Depth · 6 h</span>
                        <span>now</span>
                    </div>
                </div>
                <div class="sk-gblock">
                    <div class="sk-gblock__top">
                        <span class="sk-seclabel">Barometric pressure</span>
                        <span class="sk-num sk-num--depth">—<span class="sk-u">hPa</span></span>
                    </div>
                    <TrendChart
                        v-if="pressureHistory.length > 0"
                        :data="pressureHistory"
                        variant="line"
                        color="var(--color-blue)"
                        :height="78"
                    />
                    <div v-else class="sk-chart-empty">No pressure history</div>
                    <div class="sk-axis">
                        <span>Pressure · 6 h</span>
                        <span>now</span>
                    </div>
                </div>
            </div>

            <!-- FOOTER: house battery + engine battery + fuel -->
            <div class="sk-resources">
                <!-- House battery -->
                <div class="sk-res">
                    <div class="sk-res__top">
                        <span class="sk-seclabel">House Battery</span>
                        <span class="sk-res__vals">
                            <span class="sk-res__pct sk-res__pct--g" :class="{ 'sk-stale': stale('house_battery_soc') }">{{ houseSocDisplay }}</span>
                            <span class="sk-res__sub" :class="{ 'sk-stale': stale('house_battery_voltage') }">{{ houseVoltDisplay }} V</span>
                        </span>
                    </div>
                    <TrendChart
                        v-if="housePowerHistory.length > 0"
                        :data="housePowerHistory"
                        variant="bipolar"
                        color-positive="var(--color-green)"
                        color-negative="var(--color-scarlet)"
                        :height="70"
                    />
                    <div v-else class="sk-res__empty">No history</div>
                    <div class="sk-axis">
                        <span>net power · 6 h</span>
                        <span>now</span>
                    </div>
                </div>
                <!-- Engine battery -->
                <div class="sk-res">
                    <div class="sk-res__top">
                        <span class="sk-seclabel">Engine Battery</span>
                        <span class="sk-res__vals">
                            <span class="sk-res__pct sk-res__pct--g" :class="{ 'sk-stale': stale('engine_battery_voltage') }">
                                {{ engineVoltDisplay }}<span class="sk-u" style="font-size: 13px">V</span>
                            </span>
                        </span>
                    </div>
                    <div class="sk-res__empty sk-res__empty--sm">No history</div>
                    <div class="sk-axis">
                        <span>voltage · live</span>
                        <span>now</span>
                    </div>
                </div>
                <!-- Fuel -->
                <div class="sk-res">
                    <div class="sk-res__top">
                        <span class="sk-seclabel">Fuel</span>
                        <span class="sk-res__vals">
                            <span class="sk-res__pct sk-res__pct--a" :class="{ 'sk-stale': stale('fuel_level') }">{{ fuelDisplay }}</span>
                        </span>
                    </div>
                    <div class="sk-res__empty sk-res__empty--sm">No history</div>
                    <div class="sk-axis">
                        <span>fuel level · live</span>
                        <span>now</span>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import CompassRose from '@/components/Admin/CompassRose.vue';
import TrendChart from '@/components/Admin/TrendChart.vue';
import { useScarletMetrics } from '@/composables/useScarletMetrics.js';

const props = defineProps({
    contracts: { type: Object, default: () => ({}) },
    depthHistory: { type: Array, default: () => [] },
    pressureHistory: { type: Array, default: () => [] },
    speedHistory: { type: Array, default: () => [] },
    housePowerHistory: { type: Array, default: () => [] },
    routeLegs: { type: Array, default: () => [] },
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
    initialMetrics: { canonical: props.contracts },
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

// ── Point of sail (derived from AWA — valid derivation from a real sensor value) ──
const pointOfSailText = computed(() => {
    const awa = val('wind_angle_apparent');
    if (awa == null) return '—';
    const abs = awa > 180 ? 360 - awa : awa;
    if (abs < 30) return 'In Irons';
    if (abs < 55) return 'Close Hauled';
    if (abs < 80) return 'Close Reach';
    if (abs < 105) return 'Beam Reach';
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
const sogDisplay = computed(() => fmtFixed(val('speed_sog')));
const stwDisplay = computed(() => fmtFixed(val('speed_stw')));
const vmgDisplay = computed(() => fmtFixed(val('vmg')));

// ── Wind ──────────────────────────────────────────────────────────────────────
const twsDisplay = computed(() => {
    const v = val('wind_speed_apparent');
    return v == null ? '—' : fmtInt(v);
});

const awaDisplay = computed(() => {
    const v = val('wind_angle_apparent');
    return v == null ? '—' : fmtFixed(v, 0);
});

// ── Tidal set / drift ─────────────────────────────────────────────────────────
const tideSetDisplay = computed(() => {
    const v = val('current_set_true');
    return v == null ? '—' : `${fmtInt(v)}°`;
});

const tideDriftDisplay = computed(() => fmtFixed(val('current_drift')));

const tideDeltaDisplay = computed(() => {
    const drift = val('current_drift');
    if (drift == null) return '—';
    const sign = drift >= 0 ? '+' : '−';
    return `${sign}${fmtFixed(Math.abs(drift))} kt`;
});

const tideFairFoul = computed(() => {
    const set = val('current_set_true');
    const cog = val('cog');
    if (set == null || cog == null) return 'tidal current';
    const diff = Math.abs(((set - cog) + 360) % 360);
    const aligned = diff < 90 || diff > 270;
    return aligned ? 'fair tide ▲' : 'foul tide ▼';
});

// ── Instrument list ───────────────────────────────────────────────────────────
const heelDisplay = computed(() => fmtFixed(val('heel'), 0));
const pitchDisplay = computed(() => fmtFixed(val('pitch'), 0));

const rotDisplay = computed(() => {
    const v = val('rate_of_turn');
    return v == null ? '—' : fmtFixed(Math.abs(v), 0);
});

// ── Heading / COG / variation ─────────────────────────────────────────────────
const headingTDisplay = computed(() => {
    const v = val('heading_true');
    return v == null ? '—' : fmtInt(v);
});

const headingMDisplay = computed(() => {
    const v = val('heading_magnetic');
    return v == null ? '—' : fmtInt(v);
});

const cogDisplay = computed(() => {
    const v = val('cog');
    return v == null ? '—' : fmtInt(v);
});

const variationDisplay = computed(() => {
    const v = val('magnetic_variation');
    if (v == null) return '—';
    const abs = Math.abs(v).toFixed(1);
    const dir = v >= 0 ? 'E' : 'W';
    return `${abs}°${dir}`;
});

// ── Rate of turn for nav panel ────────────────────────────────────────────────
const rotNavDisplay = computed(() => {
    const v = val('rate_of_turn');
    if (v == null) return '—';
    const abs = fmtFixed(Math.abs(v), 0);
    const dir = v > 0 ? ' P' : v < 0 ? ' S' : '';
    return `${abs}°/min${dir}`;
});

// ── Nav: bearing / XTE / WP ──────────────────────────────────────────────────
const bearingWpDisplay = computed(() => fmtDeg(val('bearing_to_wp_true')));
const trackBearingDisplay = computed(() => fmtDeg(val('track_bearing_true')));

const xteDisplay = computed(() => {
    const v = val('xte');
    if (v == null) return '—';
    return `${fmtFixed(Math.abs(v))} nm`;
});

const xteNavDisplay = computed(() => {
    const v = val('xte');
    if (v == null) return '—';
    return `${fmtFixed(Math.abs(v))} nm`;
});

const wpDistDisplay = computed(() => {
    const v = val('wp_distance');
    return v == null ? '—' : fmtFixed(v);
});

const wpDistNavDisplay = computed(() => {
    const v = val('wp_distance');
    return v == null ? '—' : `${fmtFixed(v)} nm`;
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

const vmgNavDisplay = computed(() => {
    const v = val('vmg');
    return v == null ? '—' : `${fmtFixed(v)} kts`;
});

// ── Depth ─────────────────────────────────────────────────────────────────────
const depthDisplay = computed(() => fmtFixed(val('depth_below_surface')));

// ── Autopilot ─────────────────────────────────────────────────────────────────
const apStateDisplay = computed(() => {
    const v = val('autopilot_state');
    return v == null ? 'Off' : String(v);
});

const apStateClass = computed(() => {
    const v = val('autopilot_state');
    if (v == null) return 'sk-ap--off';
    const s = String(v).toLowerCase();
    if (s === 'auto' || s === 'wind' || s === 'track') return 'sk-ap--active';
    return 'sk-ap--standby';
});

const rudderDisplay = computed(() => {
    const v = val('rudder_angle');
    if (v == null) return '—';
    const abs = fmtFixed(Math.abs(v), 0);
    const side = v > 0 ? 'P' : v < 0 ? 'S' : '';
    return `${abs}°${side}`;
});

// ── Footer resources ──────────────────────────────────────────────────────────
const houseSocDisplay = computed(() => {
    const v = val('house_battery_soc');
    return v == null ? '—%' : `${fmtInt(v)}%`;
});

const houseVoltDisplay = computed(() => {
    const v = val('house_battery_voltage');
    return v == null ? '—' : Number(v).toFixed(1);
});

const engineVoltDisplay = computed(() => {
    const v = val('engine_battery_voltage');
    return v == null ? '—' : Number(v).toFixed(1);
});

const fuelDisplay = computed(() => {
    const v = val('fuel_level');
    return v == null ? '—' : `${fmtInt(v)}%`;
});
</script>

<style scoped>
/* ── Shell ─────────────────────────────────────────────────────────────────── */
.sk-dash {
    max-width: 1100px;
    font-family: 'DM Sans', sans-serif;
}

/* ── Stale treatment ────────────────────────────────────────────────────────── */
.sk-stale {
    opacity: 0.38;
    filter: grayscale(0.6);
}

/* ── Status bar ─────────────────────────────────────────────────────────────── */
.sk-status {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 18px;
    flex-wrap: wrap;
}

.sk-badge {
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

.sk-badge__dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: oklch(0.45 0.16 150);
}

.sk-hdot {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 11px;
    font-weight: 600;
    color: var(--color-text-dim);
}

.sk-hdot::before {
    content: '';
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: oklch(0.45 0.16 150);
}

.sk-passage {
    margin-left: auto;
    font-size: 13px;
    font-weight: 700;
    color: var(--color-text-secondary);
}

/* ── Shared: seclabel, num, u, axis ─────────────────────────────────────────── */
.sk-seclabel {
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 2px;
    text-transform: uppercase;
    color: var(--color-text-dim);
    display: block;
    margin-bottom: 5px;
}

.sk-num {
    font-family: 'Nunito Sans', sans-serif;
    font-weight: 700;
    line-height: 0.95;
    letter-spacing: -1px;
}

.sk-u {
    font-family: 'DM Sans', sans-serif;
    font-weight: 500;
    font-size: 11px;
    color: var(--color-text-dim);
    margin-left: 3px;
    letter-spacing: 0;
}

.sk-axis {
    display: flex;
    justify-content: space-between;
    margin-top: 6px;
    font-size: 9.5px;
    font-weight: 600;
    color: var(--color-text-dim);
}

/* ── Hero ───────────────────────────────────────────────────────────────────── */
.sk-hero {
    display: grid;
    grid-template-columns: 270px 1fr;
    gap: 30px;
    align-items: center;
    padding-bottom: 18px;
    border-bottom: 1px solid var(--color-border-light);
    margin-bottom: 18px;
}

.sk-cwrap {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.sk-pos {
    margin-top: 8px;
    text-align: center;
}

.sk-pos__name {
    font-size: 18px;
    font-weight: 800;
    letter-spacing: 0.5px;
}

.sk-pos__sub {
    margin-top: 4px;
    font-size: 11px;
    font-weight: 600;
    color: var(--color-text-dim);
}

.sk-pos__sub b {
    font-family: 'Nunito Sans', sans-serif;
    font-weight: 700;
    color: var(--color-text-secondary);
}

/* ── Sail block ─────────────────────────────────────────────────────────────── */
.sk-sail {
    min-width: 0;
}

.sk-speed-head {
    display: flex;
    align-items: flex-end;
    gap: 22px;
}

.sk-k {
    display: flex;
    flex-direction: column;
}

.sk-num--sog {
    font-size: 52px;
    color: var(--color-teal);
}

.sk-num--stw {
    font-size: 34px;
    color: var(--color-text-primary);
}

.sk-num--vmg {
    font-size: 34px;
    color: var(--color-teal);
}

.sk-tide {
    margin-left: auto;
    display: flex;
    flex-direction: column;
    gap: 5px;
    align-items: flex-end;
    text-align: right;
}

.sk-tide__dn {
    font-family: 'Nunito Sans', sans-serif;
    font-weight: 800;
    font-size: 19px;
    color: oklch(0.4 0.13 150);
}

.sk-tide__dl {
    font-size: 9px;
    font-weight: 700;
    letter-spacing: 0.6px;
    text-transform: uppercase;
    color: var(--color-text-dim);
}

.sk-tide__setdrift {
    font-size: 11px;
    font-weight: 600;
    color: var(--color-text-dim);
}

.sk-tide__setdrift b {
    font-family: 'Nunito Sans', sans-serif;
    font-weight: 700;
    color: var(--color-text-secondary);
}

/* Lower row: speed graph + instrument list */
.sk-lower {
    display: grid;
    grid-template-columns: 1.35fr 1fr;
    gap: 26px;
    margin-top: 14px;
    align-items: stretch;
}

.sk-graphwrap {
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
}

.sk-graph-empty {
    height: 80px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    color: var(--color-text-dim);
    background: var(--color-bg);
    border-radius: 6px;
}

.sk-instlist {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding-left: 24px;
    border-left: 1px solid var(--color-border-light);
}

.sk-ir {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    padding: 3px 0;
}

.sk-ir__l {
    font-size: 11px;
    font-weight: 500;
    color: var(--color-text-dim);
}

.sk-ir__v {
    font-family: 'Nunito Sans', sans-serif;
    font-weight: 700;
    font-size: 18px;
    color: var(--color-text-primary);
}

.sk-ir__v--amber { color: var(--color-amber); }
.sk-ir__v--teal { color: var(--color-teal); }

/* ── Secondary: map + nav panel ─────────────────────────────────────────────── */
.sk-secondary {
    display: grid;
    grid-template-columns: 1.65fr 1fr;
    gap: 16px;
    align-items: stretch;
    margin-bottom: 16px;
}

/* Map */
.sk-map {
    min-height: 380px;
    border-radius: 16px;
    position: relative;
    overflow: hidden;
    border: 1px solid var(--color-border);
    background: linear-gradient(165deg, oklch(0.9 0.035 220), oklch(0.84 0.055 210) 55%, oklch(0.8 0.06 205));
}

.sk-map__leaflet {
    width: 100%;
    height: 100%;
    min-height: 380px;
}

.sk-map :deep(.leaflet-container) {
    width: 100%;
    height: 100%;
    min-height: 380px;
    border-radius: 16px;
    z-index: 0;
}

/* Map clusters */
.sk-cluster {
    position: absolute;
    z-index: 10;
    background: oklch(0.99 0.004 205 / 0.92);
    border: 1px solid var(--color-border);
    border-radius: 12px;
    padding: 8px 11px;
    box-shadow: 0 2px 10px oklch(0.2 0.02 205 / 0.12);
}

.sk-cluster--tr {
    top: 12px;
    right: 12px;
    font-family: 'Nunito Sans', sans-serif;
    font-weight: 700;
    font-size: 11px;
    color: var(--color-text-primary);
}

.sk-cluster--xte {
    bottom: 12px;
    left: 12px;
}

.sk-num--xte {
    font-size: 17px;
    color: var(--color-amber);
}

.sk-cluster--br {
    bottom: 12px;
    right: 12px;
    display: flex;
    gap: 16px;
    align-items: flex-end;
}

.sk-cluster__k {
    display: flex;
    flex-direction: column;
}

.sk-num--sm {
    font-size: 18px;
    color: var(--color-text-primary);
}

.sk-attr {
    position: absolute;
    bottom: 6px;
    left: 10px;
    font-size: 8px;
    font-weight: 600;
    color: oklch(0.3 0.04 220 / 0.6);
    z-index: 2;
}

/* Nav panel */
.sk-navpanel {
    display: flex;
    flex-direction: column;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: 16px;
    padding: 16px 18px;
}

/* Autopilot strip */
.sk-ap {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 9px 11px;
    border-radius: 10px;
    margin-bottom: 12px;
}

.sk-ap--active {
    background: oklch(0.93 0.05 150);
}

.sk-ap--standby {
    background: oklch(0.93 0.04 80);
}

.sk-ap--off {
    background: var(--color-bg);
    border: 1px solid var(--color-border-light);
}

.sk-ap__dot {
    width: 9px;
    height: 9px;
    border-radius: 50%;
    background: var(--color-green);
    box-shadow: 0 0 0 4px oklch(0.6 0.14 150 / 0.18);
    flex-shrink: 0;
}

.sk-ap--off .sk-ap__dot {
    background: var(--color-text-dim);
    box-shadow: none;
}

.sk-ap__txt {
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    color: oklch(0.28 0.1 150);
}

.sk-ap--off .sk-ap__txt {
    color: var(--color-text-dim);
}

.sk-ap--standby .sk-ap__txt {
    color: oklch(0.35 0.08 70);
}

.sk-ap__rud {
    margin-left: auto;
    font-size: 11px;
    font-weight: 700;
    color: oklch(0.32 0.09 150);
}

.sk-ap__rud b {
    font-family: 'Nunito Sans', sans-serif;
}

.sk-ap__hdg {
    font-family: 'Nunito Sans', sans-serif;
    font-weight: 700;
    font-size: 18px;
    color: oklch(0.28 0.1 150);
    padding-left: 12px;
    margin-left: 12px;
    border-left: 1px solid oklch(0.6 0.1 150 / 0.4);
}

/* Nav grid */
.sk-navgrid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2px 18px;
    flex: 1;
}

.sk-nr {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    padding: 6px 0;
    border-bottom: 1px solid var(--color-border-light);
}

.sk-nr__l {
    font-size: 11px;
    font-weight: 500;
    color: var(--color-text-dim);
}

.sk-nr__v {
    font-family: 'Nunito Sans', sans-serif;
    font-weight: 600;
    font-size: 15px;
    color: var(--color-text-primary);
}

.sk-nr__v--teal { color: var(--color-teal); }
.sk-nr__v--amber { color: var(--color-amber); }

/* ── Safety trends ───────────────────────────────────────────────────────────── */
.sk-safety {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 16px;
}

.sk-gblock {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: 16px;
    padding: 14px 16px;
}

.sk-gblock__top {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    margin-bottom: 10px;
}

.sk-num--depth {
    font-size: 24px;
    color: var(--color-blue);
}

.sk-chart-empty {
    height: 78px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    color: var(--color-text-dim);
}

/* ── Footer resources ───────────────────────────────────────────────────────── */
.sk-resources {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 30px;
    padding-top: 16px;
    border-top: 1px solid var(--color-border-light);
}

.sk-res__top {
    display: flex;
    align-items: baseline;
    gap: 12px;
    margin-bottom: 8px;
}

.sk-res__vals {
    margin-left: auto;
    font-family: 'Nunito Sans', sans-serif;
    font-weight: 700;
    font-size: 15px;
    color: var(--color-text-secondary);
    letter-spacing: -0.3px;
    display: flex;
    align-items: baseline;
    gap: 4px;
}

.sk-res__pct {
    font-size: 22px;
}

.sk-res__pct--g { color: var(--color-green); }
.sk-res__pct--a { color: var(--color-amber); }

.sk-res__sub {
    font-family: 'DM Sans', sans-serif;
    font-weight: 500;
    font-size: 11px;
    color: var(--color-text-dim);
    letter-spacing: 0;
}

.sk-res__empty {
    height: 70px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    color: var(--color-text-dim);
}

.sk-res__empty--sm {
    height: 50px;
}

/* ── Responsive ─────────────────────────────────────────────────────────────── */
@media (max-width: 920px) {
    .sk-hero,
    .sk-secondary,
    .sk-safety,
    .sk-resources {
        grid-template-columns: 1fr;
    }

    .sk-map {
        min-height: 260px;
    }

    .sk-map__leaflet {
        min-height: 260px;
    }

    .sk-map :deep(.leaflet-container) {
        min-height: 260px;
    }

    .sk-passage {
        margin-left: 0;
        width: 100%;
    }

    .sk-lower {
        grid-template-columns: 1fr;
    }

    .sk-instlist {
        padding-left: 0;
        border-left: none;
        border-top: 1px solid var(--color-border-light);
        padding-top: 12px;
    }
}
</style>
