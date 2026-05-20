<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const props = defineProps({
    initialMetrics: Object,
    boatName: String,
    passageFrom: String,
    passageTo: String,
    portName: String,
    tileUrl: String,
    reverb: Object,
    reverbKey: String,
});

const mapContainer = ref(null);
const boat = ref(props.initialMetrics?.boat ?? {});
const gps = ref(props.initialMetrics?.gps ?? {});
const weather = ref(props.initialMetrics?.weather ?? null);
const clock = ref('--:--');
const clockDate = ref('');
const lastUpdate = ref(props.initialMetrics ? new Date() : null);

let map = null;
let boatMarker = null;
let trackSegments = [];
let trackPoints = [];
let clockInterval = null;
let userPanned = false;

const fmt = (val, decimals = 1) => val != null ? Number(val).toFixed(decimals) : '--';

const coordText = computed(() => {
    const lat = gps.value?.latitude;
    const lon = gps.value?.longitude;
    if (lat == null || lon == null) return '--';
    const latDir = lat >= 0 ? 'N' : 'S';
    const lonDir = lon >= 0 ? 'E' : 'W';
    return `${Math.abs(lat).toFixed(4)}°${latDir}  ${Math.abs(lon).toFixed(4)}°${lonDir}`;
});

const statusText = computed(() => {
    const sog = boat.value?.speed_sog;
    const rpm = boat.value?.engine_rpm;
    if (sog != null && sog < 0.5 && props.portName) return 'In Port';
    if (rpm != null && rpm > 0) return 'Under Power';
    return 'Under Sail';
});

const statusClass = computed(() => {
    if (statusText.value === 'In Port') return 'status-port';
    if (statusText.value === 'Under Power') return 'status-power';
    return 'status-sail';
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

const lastUpdateText = computed(() => {
    if (!lastUpdate.value) return '';
    return lastUpdate.value.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
});

const weatherIcons = {
    'day-sunny': '☀️', 'night-clear': '🌙', 'cloud': '⛅', 'cloudy': '☁️',
    'fog': '🌫️', 'sprinkle': '🌦️', 'rain': '🌧️', 'snow': '❄️',
    'showers': '🌦️', 'thunderstorm': '⛈️', 'na': '🌤️',
};
const weatherLabels = {
    'day-sunny': 'Clear', 'night-clear': 'Clear', 'cloud': 'Partly Cloudy',
    'cloudy': 'Overcast', 'fog': 'Fog', 'sprinkle': 'Drizzle', 'rain': 'Rain',
    'snow': 'Snow', 'showers': 'Showers', 'thunderstorm': 'Thunderstorm', 'na': 'Unknown',
};

const wxTemp = computed(() => weather.value?.temp != null ? `${Number(weather.value.temp).toFixed(1)}°` : '--');
const wxCondition = computed(() => weatherLabels[weather.value?.summary] ?? 'Unknown');
const wxIcon = computed(() => weatherIcons[weather.value?.summary] ?? '🌤');
const wxSeaTemp = computed(() => weather.value?.seaTemp != null ? `${Number(weather.value.seaTemp).toFixed(1)}°` : '--');
const wxWindSpeed = computed(() => weather.value?.wind?.speed != null ? `${Math.round(weather.value.wind.speed)} kn` : '--');
const wxWindDir = computed(() => weather.value?.wind?.direction ?? '');
const wxWaveHeight = computed(() => weather.value?.waves?.height != null ? `${Number(weather.value.waves.height).toFixed(1)} m` : '--');
const wxWavePeriod = computed(() => weather.value?.waves?.period != null ? `${Math.round(weather.value.waves.period)} s` : '');

function speedToColor(speed) {
    const s = speed ?? 0;
    const ratio = Math.min(s / 10, 1);
    if (ratio <= 0.5) {
        const t = ratio * 2;
        return `oklch(${0.55 + t * 0.07} ${0.14 + t * 0.01} ${240 - t * 85})`;
    }
    const t = (ratio - 0.5) * 2;
    return `oklch(${0.62 - t * 0.08} ${0.15 + t * 0.07} ${155 - t * 128})`;
}

function zoomIn() { map?.zoomIn(); }
function zoomOut() { map?.zoomOut(); }

function recentre() {
    if (!gps.value?.latitude || !gps.value?.longitude) return;
    map?.setView([gps.value.latitude, gps.value.longitude]);
    userPanned = false;
}

function updateClock() {
    const now = new Date();
    clock.value = now.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
    clockDate.value = now.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
}

function updateMap(newGps, newBoat) {
    if (!newGps?.latitude || !newGps?.longitude || !map) return;
    const pos = [newGps.latitude, newGps.longitude];
    const heading = newBoat?.heading ?? newBoat?.cog ?? 0;
    const speed = newBoat?.speed_sog ?? 0;

    trackPoints.push({ pos, speed });

    const iconHtml = `<svg width="24" height="24" viewBox="0 0 24 24" style="transform:rotate(${heading}deg);overflow:visible">
        <polygon points="12,2 20,20 12,16 4,20" fill="oklch(0.54 0.22 27)" stroke="oklch(0.96 0.005 70)" stroke-width="1.5"/>
    </svg>`;
    const icon = L.divIcon({
        className: 'boat-marker',
        html: iconHtml,
        iconSize: [24, 24],
        iconAnchor: [12, 12],
    });

    if (boatMarker) {
        boatMarker.setLatLng(pos);
        boatMarker.setIcon(icon);
    } else {
        boatMarker = L.marker(pos, { icon }).addTo(map);
    }

    if (trackPoints.length > 1) {
        const prev = trackPoints[trackPoints.length - 2];
        const seg = L.polyline([prev.pos, pos], {
            color: speedToColor(speed),
            weight: 3,
            opacity: 0.85,
        }).addTo(map);
        trackSegments.push(seg);
    }

    if (!userPanned) {
        map.setView(pos);
    }
}

onMounted(() => {
    map = L.map(mapContainer.value, {
        zoomControl: false,
        attributionControl: false,
    }).setView(
        gps.value?.latitude ? [gps.value.latitude, gps.value.longitude] : [50.6931, -1.6433],
        14
    );

    L.tileLayer(props.tileUrl, { maxZoom: 18 }).addTo(map);

    map.on('dragstart', () => { userPanned = true; });

    if (gps.value?.latitude) {
        updateMap(gps.value, boat.value);
    }

    updateClock();
    clockInterval = setInterval(updateClock, 1000);

    if (window.Echo) {
        window.Echo.channel('metrics').listen('.metrics.updated', (data) => {
            boat.value = data.boat;
            gps.value = data.gps;
            if (data.weather) weather.value = data.weather;
            lastUpdate.value = new Date();
            updateMap(data.gps, data.boat);
        });
    }
});

onUnmounted(() => {
    clearInterval(clockInterval);
    if (window.Echo) window.Echo.leave('metrics');
    map?.remove();
});
</script>

<template>
    <Head :title="`${boatName} — Live Dashboard`" />

    <div class="dashboard">
        <!-- Full-viewport Leaflet map -->
        <div ref="mapContainer" class="map-container"></div>

        <!-- TOP-LEFT: LIVE badge + map controls -->
        <div class="tl-cluster">
            <!-- LIVE badge -->
            <div class="live-badge">
                <div class="live-scarlet">
                    <div class="live-dot"></div>
                    <span class="live-label">LIVE</span>
                </div>
                <div class="live-ext">
                    <span v-if="lastUpdateText">Updated {{ lastUpdateText }}</span>
                    <span v-else>Connecting…</span>
                </div>
            </div>

            <!-- Map controls -->
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

        <!-- TOP-RIGHT: Weather pills -->
        <div class="wx-cluster">
            <div class="pill pill--hero">
                <span class="wx-icon">{{ wxIcon }}</span>
                <div>
                    <div class="pill-val">{{ wxTemp }}</div>
                    <div class="pill-sub">{{ wxCondition }}</div>
                </div>
            </div>
            <div class="pill">
                <div class="pill-lbl">SEA</div>
                <div class="pill-val">{{ wxSeaTemp }}</div>
            </div>
            <div class="pill">
                <div class="pill-lbl">WIND</div>
                <div class="pill-val">{{ wxWindSpeed }}</div>
                <div class="pill-sub">{{ wxWindDir }}</div>
            </div>
            <div class="pill">
                <div class="pill-lbl">WAVES</div>
                <div class="pill-val">{{ wxWaveHeight }}</div>
                <div class="pill-sub">{{ wxWavePeriod }}</div>
            </div>
        </div>

        <!-- BOTTOM-LEFT: Coords + speed legend -->
        <div class="bottom-badges">
            <div class="coord-badge">{{ coordText }}</div>
            <div class="speed-legend">
                <span>0 kn</span>
                <div class="legend-gradient"></div>
                <span>10 kn</span>
            </div>
        </div>

        <!-- BOTTOM-RIGHT: Sailing instrument pills -->
        <div class="instruments">
            <div class="pill pill--compound">
                <div class="pill-cell">
                    <div class="pill-lbl">APP. WIND</div>
                    <div class="pill-val">{{ fmt(boat?.wind_speed_apparent) }} kn</div>
                </div>
                <div class="pill-cell">
                    <div class="pill-lbl">ANGLE</div>
                    <div class="pill-val">{{ fmt(Math.abs(boat?.wind_angle_apparent ?? 0), 0) }}°</div>
                    <div class="pill-sub">{{ windAngleSide }}</div>
                </div>
            </div>
            <div class="pill">
                <div class="pill-lbl">HEEL</div>
                <div class="pill-val">{{ fmt(Math.abs(boat?.heel ?? 0), 0) }}°</div>
                <div class="pill-sub">{{ heelSide }}</div>
            </div>
            <div class="pill">
                <div class="pill-lbl">PRESSURE</div>
                <div class="pill-val">{{ fmt(boat?.pressure, 0) }}</div>
                <div class="pill-sub">hPa</div>
            </div>
            <div class="pill">
                <div class="pill-lbl">TRIP</div>
                <div class="pill-val">{{ fmt(boat?.trip_log) }} nm</div>
            </div>
        </div>

        <!-- LOWER THIRD -->
        <div class="lower-third">
            <div class="lt-brand">
                <span class="lt-name">{{ boatName }}</span>
            </div>
            <div class="lt-body">
                <div class="lt-metric">
                    <div class="lt-label">SPEED</div>
                    <div class="lt-val">{{ fmt(boat?.speed_sog) }} kn</div>
                </div>
                <div class="lt-sep"></div>
                <div class="lt-metric">
                    <div class="lt-label">HEADING</div>
                    <div class="lt-val">{{ fmt(boat?.heading ?? boat?.cog, 0) }}°</div>
                </div>
                <div class="lt-sep"></div>
                <div class="lt-metric">
                    <div class="lt-label">DEPTH</div>
                    <div class="lt-val">{{ fmt(boat?.depth) }} m</div>
                </div>
                <div class="lt-sep"></div>
                <span class="lt-status" :class="statusClass">{{ statusText }}</span>
                <div v-if="statusText === 'In Port' && portName" class="lt-sep"></div>
                <div v-if="statusText === 'In Port' && portName" class="lt-passage-wrap">
                    <div class="lt-passage">
                        <span class="lt-port-label">Currently at</span> <strong>{{ portName }}</strong>
                    </div>
                </div>
                <div v-else-if="passageFrom || passageTo" class="lt-sep"></div>
                <div v-else-if="passageFrom || passageTo" class="lt-passage-wrap">
                    <div class="lt-passage">
                        <strong>{{ passageFrom }}</strong>
                        <span v-if="passageFrom && passageTo"> → </span>
                        <strong>{{ passageTo }}</strong>
                    </div>
                </div>
                <div class="lt-clock">
                    <div class="lt-time">{{ clock }}</div>
                    <div class="lt-date">{{ clockDate }}</div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
:root {
    --scarlet: oklch(0.54 0.22 27);
    --scarlet-light: oklch(0.62 0.18 27);
    --scarlet-glow: oklch(0.54 0.22 27 / 0.25);
    --chrome: oklch(0.08 0.008 40 / 0.72);
    --chrome-border: oklch(0.32 0.01 40 / 0.18);
    --text-bright: oklch(0.96 0.005 70);
    --text-mid: oklch(0.75 0.008 70);
    --text-dim: oklch(0.62 0.008 70);
    --sail-green: oklch(0.78 0.12 155);
    --sail-green-bg: oklch(0.78 0.12 155 / 0.12);
    --power-amber: oklch(0.78 0.16 80);
    --power-amber-bg: oklch(0.78 0.16 80 / 0.12);
    --port-blue: oklch(0.70 0.12 240);
    --port-blue-bg: oklch(0.70 0.12 240 / 0.12);
    --radius: 10px;
    --radius-sm: 7px;
    --blur: blur(24px);
}

/* Reset and base */
*,
*::before,
*::after {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

/* Full-viewport dashboard frame */
.dashboard {
    position: fixed;
    inset: 0;
    font-family: 'Outfit', system-ui, sans-serif;
    font-variant-numeric: tabular-nums;
    color: oklch(0.96 0.005 70);
    overflow: hidden;
}

/* Map fills entire viewport */
.map-container {
    position: absolute;
    inset: 0;
    z-index: 0;
}

/* ── TOP-LEFT CLUSTER ──────────────────────────── */
.tl-cluster {
    position: absolute;
    top: 16px;
    left: 16px;
    z-index: 10;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

/* LIVE badge */
.live-badge {
    display: flex;
    align-items: stretch;
    border-radius: var(--radius-sm);
    overflow: hidden;
    box-shadow: 0 2px 16px oklch(0.54 0.22 27 / 0.25);
}

.live-scarlet {
    display: flex;
    align-items: center;
    gap: 7px;
    background: oklch(0.54 0.22 27);
    padding: 7px 14px 7px 10px;
}

.live-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: oklch(0.96 0.005 70);
    animation: pulse 2s ease-in-out infinite;
    flex-shrink: 0;
}

@keyframes pulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.45; transform: scale(0.8); }
}

.live-label {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.08em;
    color: oklch(0.96 0.005 70);
}

.live-ext {
    display: flex;
    align-items: center;
    background: oklch(0.08 0.008 40 / 0.72);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    padding: 7px 14px;
    font-size: 10px;
    font-weight: 500;
    color: oklch(0.62 0.008 70);
    letter-spacing: 0.02em;
}

/* Map controls */
.map-controls {
    display: flex;
    flex-direction: column;
    gap: 0;
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
    border-radius: var(--radius-sm) var(--radius-sm) 0 0;
    border-bottom: none;
}

.map-ctrl--bottom {
    border-radius: 0 0 var(--radius-sm) var(--radius-sm);
}

.map-ctrl--single {
    border-radius: var(--radius-sm);
    font-size: 14px;
    stroke: oklch(0.75 0.008 70);
    color: oklch(0.75 0.008 70);
}

/* ── TOP-RIGHT: WEATHER ─────────────────────────── */
.wx-cluster {
    position: absolute;
    top: 16px;
    right: 16px;
    z-index: 10;
    display: flex;
    gap: 5px;
    align-items: stretch;
}

/* ── SHARED PILL ────────────────────────────────── */
.pill {
    background: oklch(0.08 0.008 40 / 0.72);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    border-radius: var(--radius-sm);
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

/* Hero weather pill */
.pill--hero {
    display: flex;
    gap: 8px;
    align-items: center;
    text-align: left;
    padding: 7px 14px;
}

.wx-icon {
    font-size: 20px;
    line-height: 1;
    flex-shrink: 0;
}

.pill--hero .pill-val {
    font-size: 16px;
    font-weight: 700;
}

/* Compound pill — two values side by side */
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

/* ── BOTTOM-LEFT ────────────────────────────────── */
.bottom-badges {
    position: absolute;
    bottom: 68px;
    left: 16px;
    z-index: 10;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.coord-badge {
    font-size: 13px;
    font-weight: 500;
    color: oklch(0.96 0.005 70);
    background: oklch(0.08 0.008 40 / 0.72);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    padding: 6px 14px;
    border-radius: var(--radius-sm);
    border: 1px solid oklch(0.32 0.01 40 / 0.18);
    letter-spacing: 0.02em;
}

.speed-legend {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 10px;
    font-weight: 500;
    color: oklch(0.62 0.008 70);
    background: oklch(0.08 0.008 40 / 0.72);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    padding: 5px 12px;
    border-radius: var(--radius-sm);
    border: 1px solid oklch(0.32 0.01 40 / 0.18);
    width: fit-content;
}

.legend-gradient {
    width: 52px;
    height: 3px;
    background: linear-gradient(to right,
        oklch(0.55 0.14 240),
        oklch(0.50 0.14 155),
        oklch(0.54 0.22 27)
    );
    border-radius: 2px;
}

/* ── BOTTOM-RIGHT: INSTRUMENTS ──────────────────── */
.instruments {
    position: absolute;
    bottom: 68px;
    right: 16px;
    z-index: 10;
    display: flex;
    gap: 5px;
    align-items: stretch;
}

/* ── LOWER THIRD ────────────────────────────────── */
.lower-third {
    position: absolute;
    bottom: 12px;
    left: 12px;
    right: 12px;
    z-index: 10;
    display: flex;
    align-items: stretch;
    overflow: hidden;
    min-height: 48px;
    border-radius: var(--radius);
}

.lt-brand {
    background: oklch(0.54 0.22 27);
    padding: 0 22px;
    display: flex;
    align-items: center;
    position: relative;
    flex-shrink: 0;
    z-index: 1;
}

.lt-brand::after {
    content: '';
    position: absolute;
    right: -16px;
    top: 0;
    width: 16px;
    height: 100%;
    background: oklch(0.54 0.22 27);
    clip-path: polygon(0 0, 0 100%, 100% 100%);
}

.lt-name {
    font-size: 18px;
    font-weight: 700;
    letter-spacing: 0.03em;
    color: oklch(0.96 0.005 70);
    white-space: nowrap;
}

.lt-body {
    flex: 1;
    display: flex;
    align-items: center;
    background: oklch(0.08 0.008 40 / 0.72);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    padding: 10px 18px 10px 30px;
    margin-left: -12px;
    gap: 10px;
    overflow: hidden;
}

.lt-metric {
    padding: 0 2px;
    flex-shrink: 0;
}

.lt-label {
    font-size: 8px;
    font-weight: 600;
    letter-spacing: 0.06em;
    color: oklch(0.62 0.008 70);
    line-height: 1;
    white-space: nowrap;
}

.lt-val {
    font-size: 16px;
    font-weight: 600;
    color: oklch(0.96 0.005 70);
    line-height: 1.2;
    white-space: nowrap;
}

.lt-sep {
    width: 1px;
    height: 24px;
    background: oklch(0.32 0.01 40 / 0.18);
    flex-shrink: 0;
}

.lt-status {
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.03em;
    padding: 4px 10px;
    border-radius: 4px;
    white-space: nowrap;
    flex-shrink: 0;
}

.status-sail {
    color: oklch(0.78 0.12 155);
    background: oklch(0.78 0.12 155 / 0.12);
}

.status-power {
    color: oklch(0.78 0.16 80);
    background: oklch(0.78 0.16 80 / 0.12);
}

.status-port {
    color: oklch(0.70 0.12 240);
    background: oklch(0.70 0.12 240 / 0.12);
}

.lt-passage-wrap {
    flex: 1;
    display: flex;
    justify-content: center;
    overflow: hidden;
}

.lt-passage {
    font-size: 14px;
    font-weight: 500;
    color: oklch(0.75 0.008 70);
    white-space: nowrap;
}

.lt-passage strong {
    color: oklch(0.96 0.005 70);
    font-weight: 600;
}

.lt-port-label {
    font-size: 11px;
    font-weight: 500;
    color: oklch(0.62 0.008 70);
    margin-right: 4px;
}

.lt-clock {
    text-align: right;
    flex-shrink: 0;
    padding: 0 2px;
}

.lt-time {
    font-size: 16px;
    font-weight: 600;
    color: oklch(0.96 0.005 70);
    line-height: 1;
}

.lt-date {
    font-size: 9px;
    font-weight: 500;
    color: oklch(0.62 0.008 70);
    margin-top: 2px;
}
</style>

<style>
/* Global overrides for Leaflet boat marker — not scoped */
.boat-marker {
    background: transparent !important;
    border: none !important;
}
</style>
