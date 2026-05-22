<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useScarletMetrics } from '../../composables/useScarletMetrics';
import { useSpringValue, useAngleSpring } from '../../composables/useSpringValue';
import ScarletWeather from '../../components/ScarletWeather.vue';
import ScarletLiveBadge from '../../components/ScarletLiveBadge.vue';
import ScarletBottomBadges from '../../components/ScarletBottomBadges.vue';
import ScarletLowerThird from '../../components/ScarletLowerThird.vue';
import ScarletCompass from '../../components/ScarletCompass.vue';

const props = defineProps({
    initialMetrics: Object,
    boatName: String,
    passageFrom: String,
    passageTo: String,
    portName: String,
    tileUrl: String,
    reverb: Object,
    reverbKey: String,
    tripOffset: { type: Number, default: 0 },
});

const mapContainer = ref(null);
const autoCenter = ref(true);
let map = null;

const {
    boat, gps, weather,
    clock, clockDate,
    coordText, statusText, statusClass, lastUpdateText,
    wxTemp, wxCondition, wxIcon, wxSeaTemp, wxWindSpeed, wxWindDir, wxWaveHeight, wxWavePeriod,
    initMap, addMapTarget,
} = useScarletMetrics({
    initialMetrics: props.initialMetrics,
    portName: props.portName,
});

const windAngleSide = computed(() => {
    const angle = boat.value?.wind_angle_apparent;
    if (angle == null) return '';
    return angle < 0 ? 'port' : 'starboard';
});

const heelSide = computed(() => {
    const heel = boat.value?.heel;
    if (heel == null) return '';
    return heel < 0 ? 'port' : 'starboard';
});

const weatherProps = computed(() => ({
    wxIcon: wxIcon.value,
    wxTemp: wxTemp.value,
    wxCondition: wxCondition.value,
    wxSeaTemp: wxSeaTemp.value,
    wxWindSpeed: wxWindSpeed.value,
    wxWindDir: wxWindDir.value,
    wxWaveHeight: wxWaveHeight.value,
    wxWavePeriod: wxWavePeriod.value,
    rawTemp: weather.value?.temp != null ? Number(weather.value.temp) : null,
    rawSeaTemp: weather.value?.seaTemp != null ? Number(weather.value.seaTemp) : null,
    rawWindSpeed: weather.value?.wind?.speed != null ? Number(weather.value.wind.speed) : null,
    rawWaveHeight: weather.value?.waves?.height != null ? Number(weather.value.waves.height) : null,
    rawWavePeriod: weather.value?.waves?.period != null ? Number(weather.value.waves.period) : null,
}));

const compassHeading = computed(() => boat.value?.heading ?? boat.value?.cog ?? 0);
const compassWind = computed(() => {
    const dir = weather.value?.wind?.direction;
    return dir != null ? Number(dir) : null;
});

const animAppWind = useSpringValue(() => boat.value?.wind_speed_apparent, { tension: 80, friction: 12 });
const animWindAngle = useAngleSpring(() => Math.abs(boat.value?.wind_angle_apparent ?? 0));
const animHeel = useSpringValue(() => Math.abs(boat.value?.heel ?? 0), { tension: 80, friction: 12 });
const adjustedTrip = computed(() => {
    const raw = boat.value?.trip_log;
    if (raw == null) return null;
    return Math.max(0, raw - props.tripOffset);
});
const animTrip = useSpringValue(() => adjustedTrip.value, { tension: 60, friction: 10 });

function fmtSpring(anim, raw, decimals = 1) {
    if (raw == null) return '--';
    return Number(anim).toFixed(decimals);
}

function zoomIn() { map?.zoomIn(); }
function zoomOut() { map?.zoomOut(); }

function recentre() {
    if (!gps.value?.latitude || !gps.value?.longitude) return;
    map?.setView([gps.value.latitude, gps.value.longitude]);
    autoCenter.value = true;
}

onMounted(() => {
    map = initMap(mapContainer.value);
    map.on('dragstart', () => { autoCenter.value = false; });
    addMapTarget(map, { autoCenter });
});

onUnmounted(() => {
    map?.remove();
});
</script>

<template>
    <Head :title="`${boatName} — Live Dashboard`" />

    <div class="dashboard">
        <div ref="mapContainer" class="map-container"></div>

        <!-- TOP-LEFT: LIVE badge + map controls -->
        <div class="tl-cluster">
            <ScarletLiveBadge
                :extension="lastUpdateText ? `Updated ${lastUpdateText}` : 'Connecting…'"
            />
            <div class="map-controls">
                <button class="map-ctrl map-ctrl--top" @click="zoomIn" title="Zoom in">+</button>
                <button class="map-ctrl map-ctrl--bottom" @click="zoomOut" title="Zoom out">&minus;</button>
            </div>
            <div class="map-controls" style="margin-top: 6px;">
                <button class="map-ctrl map-ctrl--single" @click="recentre" title="Re-centre on boat">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        <circle cx="12" cy="12" r="3"/>
                        <line x1="12" y1="2" x2="12" y2="6"/>
                        <line x1="12" y1="18" x2="12" y2="22"/>
                        <line x1="2" y1="12" x2="6" y2="12"/>
                        <line x1="18" y1="12" x2="22" y2="12"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- TOP-RIGHT: Weather -->
        <div class="wx-cluster">
            <ScarletWeather v-bind="weatherProps" />
        </div>

        <!-- BOTTOM-LEFT: Compass + coords + speed legend -->
        <div class="bl-cluster">
            <ScarletCompass :heading="compassHeading" :wind-direction="compassWind" :size="76" />
            <ScarletBottomBadges :coord-text="coordText" />
        </div>

        <!-- BOTTOM-RIGHT: Sailing instrument pills -->
        <div class="instruments">
            <div class="pill pill--compound">
                <div class="pill-cell">
                    <div class="pill-lbl">APP. WIND</div>
                    <div class="pill-val">{{ fmtSpring(animAppWind, boat?.wind_speed_apparent) }} kn</div>
                </div>
                <div class="pill-cell">
                    <div class="pill-lbl">ANGLE</div>
                    <div class="pill-val">{{ fmtSpring(animWindAngle, boat?.wind_angle_apparent, 0) }}°</div>
                    <div class="pill-sub">{{ windAngleSide }}</div>
                </div>
            </div>
            <div class="pill">
                <div class="pill-lbl">HEEL</div>
                <div class="pill-val">{{ fmtSpring(animHeel, boat?.heel, 0) }}°</div>
                <div class="pill-sub">{{ heelSide }}</div>
            </div>
            <div class="pill">
                <div class="pill-lbl">TRIP</div>
                <div class="pill-val">{{ fmtSpring(animTrip, adjustedTrip) }} nm</div>
            </div>
        </div>

        <!-- LOWER THIRD -->
        <div class="lt-position">
            <ScarletLowerThird
                :boat-name="boatName"
                :boat="boat"
                :status-text="statusText"
                :status-class="statusClass"
                :port-name="portName"
                :passage-from="passageFrom"
                :passage-to="passageTo"
                :clock="clock"
                :clock-date="clockDate"
            />
        </div>
    </div>
</template>

<style scoped>
/* Full-viewport dashboard frame */
.dashboard {
    position: fixed;
    inset: 0;
    font-family: 'Outfit', system-ui, sans-serif;
    font-variant-numeric: tabular-nums;
    color: oklch(0.96 0.005 70);
    overflow: hidden;
}

.map-container {
    position: absolute;
    inset: 0;
    z-index: 0;
}

/* TOP-LEFT */
.tl-cluster {
    position: absolute;
    top: 16px;
    left: 16px;
    z-index: 10;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

/* Map controls */
.map-controls {
    display: flex;
    flex-direction: column;
}

.map-ctrl {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: oklch(0.08 0.008 40 / 0.72);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    border: 1px solid oklch(0.32 0.01 40 / 0.18);
    color: oklch(0.96 0.005 70);
    font-size: 18px;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.15s ease-out;
    user-select: none;
    -webkit-user-select: none;
    font-family: 'Outfit', system-ui, sans-serif;
}

.map-ctrl:hover {
    background: oklch(0.14 0.008 40 / 0.82);
}

.map-ctrl--top {
    border-radius: 7px 7px 0 0;
    border-bottom: none;
}

.map-ctrl--bottom {
    border-radius: 0 0 7px 7px;
}

.map-ctrl--single {
    border-radius: 7px;
    font-size: 14px;
    stroke: oklch(0.75 0.008 70);
    color: oklch(0.75 0.008 70);
}

/* TOP-RIGHT */
.wx-cluster {
    position: absolute;
    top: 16px;
    right: 16px;
    z-index: 10;
}

/* BOTTOM-LEFT */
.bl-cluster {
    position: absolute;
    bottom: 68px;
    left: 16px;
    z-index: 10;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
}

/* BOTTOM-RIGHT: instruments */
.instruments {
    position: absolute;
    bottom: 68px;
    right: 16px;
    z-index: 10;
    display: flex;
    gap: 5px;
    align-items: stretch;
}

/* Pill styles for instruments (dashboard-only) */
.pill {
    background: oklch(0.08 0.008 40 / 0.72);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    border-radius: 7px;
    padding: 7px 12px;
    text-align: center;
    border: 1px solid oklch(0.32 0.01 40 / 0.18);
    min-width: 52px;
}

.pill-lbl {
    font-size: 8px;
    font-weight: 600;
    letter-spacing: 0.04em;
    color: oklch(0.62 0.008 70);
    margin-bottom: 2px;
}

.pill-val {
    font-size: 14px;
    font-weight: 500;
    color: oklch(0.96 0.005 70);
    line-height: 1.1;
}

.pill-sub {
    font-size: 9px;
    color: oklch(0.62 0.008 70);
    margin-top: 2px;
}

.pill--compound {
    display: flex;
    gap: 0;
    padding: 0;
    overflow: hidden;
}

.pill-cell {
    padding: 7px 11px;
    text-align: center;
    min-width: 48px;
}

.pill-cell + .pill-cell {
    border-left: 1px solid oklch(0.32 0.01 40 / 0.18);
}

/* LOWER THIRD positioning */
.lt-position {
    position: absolute;
    bottom: 12px;
    left: 12px;
    right: 12px;
    z-index: 10;
}
</style>

<style>
/* Global overrides for Leaflet boat marker — not scoped */
.boat-marker {
    background: transparent !important;
    border: none !important;
}
</style>
