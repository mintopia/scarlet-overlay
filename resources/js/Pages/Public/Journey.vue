<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { speedToColor, makeBoatIcon, formatCoord, addRouteLayer } from '../../scarlet';
import { fmt, fmtDuration } from '@/composables/useFormatters.js';

const props = defineProps({
    journey: Object,
    routeWaypoints: { type: Array, default: () => [] },
    gpsTrack: { type: Array, default: () => [] },
    trackPoints: { type: Array, default: () => [] },
});

const mapEl = ref(null);
const scrubIndex = ref(0);
const playing = ref(false);
const playbackSpeed = ref(1);
const speedOptions = [1, 2, 5, 10];
let map = null;
let marker = null;
let playInterval = null;

const currentPoint = computed(() => props.trackPoints[scrubIndex.value] ?? null);
const totalPoints = computed(() => props.trackPoints.length);
const progress = computed(() => totalPoints.value > 1 ? scrubIndex.value / (totalPoints.value - 1) : 0);

const startTime = computed(() => {
    if (!props.journey.started_at) return '';
    return new Date(props.journey.started_at).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
});

const endTime = computed(() => {
    if (!props.journey.ended_at) return '';
    return new Date(props.journey.ended_at).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
});

const scrubTime = computed(() => {
    const p = currentPoint.value;
    if (!p?.recorded_at) return '';
    return new Date(p.recorded_at).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
});

const journeyDate = computed(() => {
    if (!props.journey.started_at) return '';
    return new Date(props.journey.started_at).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
});

const durationText = computed(() => {
    const s = props.journey.duration;
    if (s == null) return '';
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    return h > 0 ? `${h}h ${m}m` : `${m}m`;
});

function startPlayInterval() {
    if (playInterval) { clearInterval(playInterval); playInterval = null; }
    playInterval = setInterval(() => {
        if (scrubIndex.value >= totalPoints.value - 1) {
            stopPlay();
            return;
        }
        scrubIndex.value++;
    }, 100 / playbackSpeed.value);
}

function togglePlay() {
    if (playing.value) {
        stopPlay();
    } else {
        if (scrubIndex.value >= totalPoints.value - 1) scrubIndex.value = 0;
        playing.value = true;
        startPlayInterval();
    }
}

function stopPlay() {
    playing.value = false;
    if (playInterval) { clearInterval(playInterval); playInterval = null; }
}

function cycleSpeed() {
    const idx = speedOptions.indexOf(playbackSpeed.value);
    playbackSpeed.value = speedOptions[(idx + 1) % speedOptions.length];
}

watch(playbackSpeed, () => {
    if (playing.value) {
        startPlayInterval();
    }
});

function skipStart() { stopPlay(); scrubIndex.value = 0; }
function skipEnd() { stopPlay(); scrubIndex.value = Math.max(0, totalPoints.value - 1); }

watch(scrubIndex, (idx) => {
    const p = props.trackPoints[idx];
    if (!p || !map) return;
    const pos = [p.latitude, p.longitude];
    const heading = p.heading ?? p.cog ?? 0;
    const icon = makeBoatIcon(heading);
    if (marker) {
        marker.setLatLng(pos).setIcon(icon);
    } else {
        marker = L.marker(pos, { icon }).addTo(map);
    }
    map.panTo(pos, { animate: true, duration: 0.3 });
});

onMounted(() => {
    if (!mapEl.value || !props.gpsTrack.length) return;

    const firstPt = props.gpsTrack[0];
    map = L.map(mapEl.value, { zoomControl: false, attributionControl: false })
        .setView([firstPt[0], firstPt[1]], 14);
    L.tileLayer('/openseamap/{z}/{x}/{y}', { maxZoom: 18 }).addTo(map);

    for (let i = 1; i < props.gpsTrack.length; i++) {
        L.polyline(
            [[props.gpsTrack[i - 1][0], props.gpsTrack[i - 1][1]], [props.gpsTrack[i][0], props.gpsTrack[i][1]]],
            { color: speedToColor(props.gpsTrack[i][2]), weight: 3, opacity: 0.85 }
        ).addTo(map);
    }

    const bounds = L.latLngBounds(props.gpsTrack.map(p => [p[0], p[1]]));
    map.fitBounds(bounds, { padding: [60, 60] });

    if (props.routeWaypoints.length) {
        addRouteLayer(map, props.routeWaypoints);
    }

    if (props.trackPoints.length) {
        const first = props.trackPoints[0];
        const icon = makeBoatIcon(first.heading ?? first.cog ?? 0);
        marker = L.marker([first.latitude, first.longitude], { icon }).addTo(map);
    }
});

onUnmounted(() => {
    stopPlay();
    map?.remove();
});
</script>

<template>
    <Head :title="journey.title" />

    <div class="journey-view">
        <div ref="mapEl" class="journey-map"></div>

        <!-- Journey title overlay -->
        <div class="title-overlay">
            <div class="title-name">{{ journey.title }}</div>
            <div class="title-meta">{{ journeyDate }} · {{ durationText }} · {{ journey.distance }} nm</div>
        </div>

        <!-- Metric pills -->
        <div class="metrics-overlay" v-if="currentPoint">
            <div class="metric-pill">
                <div class="metric-label">SPEED</div>
                <div class="metric-value">{{ fmt(currentPoint.speed_sog) }} kn</div>
            </div>
            <div class="metric-pill">
                <div class="metric-label">HEADING</div>
                <div class="metric-value">{{ fmt(currentPoint.heading ?? currentPoint.cog, 0) }}°</div>
            </div>
            <div class="metric-pill">
                <div class="metric-label">DEPTH</div>
                <div class="metric-value">{{ fmt(currentPoint.depth) }} m</div>
            </div>
            <div class="metric-pill">
                <div class="metric-label">WIND</div>
                <div class="metric-value">{{ fmt(currentPoint.wind_speed_true ?? currentPoint.wind_speed_apparent) }} kn</div>
            </div>
            <div class="metric-pill">
                <div class="metric-label">HEEL</div>
                <div class="metric-value">{{ fmt(currentPoint.heel, 0) }}°</div>
            </div>
        </div>

        <!-- Scrub time -->
        <div class="scrub-time" v-if="currentPoint">{{ scrubTime }}</div>

        <!-- Timeline scrubber -->
        <div class="timeline" v-if="totalPoints > 0">
            <span class="timeline-time">{{ startTime }}</span>
            <div class="timeline-track">
                <input
                    type="range"
                    :min="0"
                    :max="totalPoints - 1"
                    v-model.number="scrubIndex"
                    class="timeline-slider"
                    @input="stopPlay()"
                />
                <div class="timeline-fill" :style="{ width: (progress * 100) + '%' }"></div>
            </div>
            <span class="timeline-time">{{ endTime }}</span>
            <div class="timeline-controls">
                <button class="tl-btn" @click="skipStart" title="Skip to start" aria-label="Skip to start">⏮</button>
                <button class="tl-btn" @click="togglePlay" :title="playing ? 'Pause' : 'Play'" :aria-label="playing ? 'Pause' : 'Play'">{{ playing ? '⏸' : '▶' }}</button>
                <button class="tl-btn" @click="skipEnd" title="Skip to end" aria-label="Skip to end">⏭</button>
                <button class="tl-btn tl-speed" @click="cycleSpeed" :title="`Playback speed: ${playbackSpeed}x`" :aria-label="`Playback speed ${playbackSpeed}x, click to change`">{{ playbackSpeed }}x</button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.journey-view {
    position: fixed;
    inset: 0;
    font-family: 'Outfit', system-ui, sans-serif;
    font-variant-numeric: tabular-nums;
    color: oklch(0.96 0.005 70);
    overflow: hidden;
}

.journey-map { position: absolute; inset: 0; z-index: 0; background: oklch(0.12 0.02 210); }

.title-overlay {
    position: absolute;
    top: 10px;
    left: 10px;
    right: 10px;
    z-index: 10;
    background: oklch(0.08 0.008 40 / 0.72);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    border-radius: 10px;
    padding: 10px 14px;
    border: 1px solid oklch(0.32 0.01 40 / 0.18);
}

@media (min-width: 640px) {
    .title-overlay { top: 16px; left: 16px; right: auto; padding: 12px 18px; }
}

.title-name { font-size: 16px; font-weight: 600; }
.title-meta { font-size: 11px; color: oklch(0.62 0.008 70); margin-top: 3px; }

@media (min-width: 640px) {
    .title-name { font-size: 18px; }
    .title-meta { font-size: 12px; }
}

.metrics-overlay {
    position: absolute;
    bottom: 60px;
    right: 10px;
    z-index: 10;
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 4px;
    max-width: 60vw;
}

@media (min-width: 640px) {
    .metrics-overlay { bottom: 70px; right: 16px; gap: 5px; flex-wrap: nowrap; max-width: none; }
}

.metric-pill {
    background: oklch(0.08 0.008 40 / 0.72);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    border-radius: 7px;
    padding: 7px 12px;
    text-align: center;
    border: 1px solid oklch(0.32 0.01 40 / 0.18);
    min-width: 52px;
}

.metric-label {
    font-size: 8px;
    font-weight: 600;
    letter-spacing: 0.04em;
    color: oklch(0.62 0.008 70);
    margin-bottom: 2px;
}

.metric-value {
    font-size: 14px;
    font-weight: 500;
    color: oklch(0.96 0.005 70);
    line-height: 1.1;
}

.scrub-time {
    position: absolute;
    bottom: 60px;
    left: 10px;
    z-index: 10;
    background: oklch(0.08 0.008 40 / 0.72);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    border-radius: 7px;
    padding: 6px 10px;
    border: 1px solid oklch(0.32 0.01 40 / 0.18);
    font-size: 13px;
    font-weight: 500;
}

@media (min-width: 640px) {
    .scrub-time { bottom: 70px; left: 16px; padding: 7px 12px; font-size: 14px; }
}

.timeline {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 10;
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 10px;
    background: oklch(0.06 0.008 40 / 0.85);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    border-top: 1px solid oklch(0.20 0.01 40 / 0.3);
}

@media (min-width: 640px) {
    .timeline { gap: 12px; padding: 12px 16px; }
}

.timeline-time {
    font-size: 11px;
    color: oklch(0.62 0.008 70);
    white-space: nowrap;
    min-width: 36px;
}

@media (min-width: 640px) {
    .timeline-time { font-size: 12px; min-width: 40px; }
}

.timeline-track {
    flex: 1;
    position: relative;
    height: 10px;
    background: oklch(0.25 0.005 40);
    border-radius: 5px;
    overflow: hidden;
}

@media (min-width: 640px) {
    .timeline-track { height: 6px; border-radius: 3px; }
}

.timeline-fill {
    position: absolute;
    top: 0;
    left: 0;
    height: 100%;
    border-radius: 3px;
    background: oklch(0.54 0.22 27);
    pointer-events: none;
}

.timeline-slider {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    cursor: pointer;
    margin: 0;
    -webkit-appearance: none;
    z-index: 2;
}

.timeline-controls {
    display: flex;
    gap: 6px;
    margin-left: 4px;
}

.tl-btn {
    width: 44px;
    height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: oklch(0.20 0.005 40 / 0.6);
    border-radius: 6px;
    border: 1px solid oklch(0.30 0.01 40 / 0.3);
    color: oklch(0.85 0.005 70);
    font-size: 13px;
    cursor: pointer;
    transition: background 0.12s;
}

.tl-speed {
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.02em;
    color: oklch(0.54 0.22 27);
    border-color: oklch(0.54 0.22 27 / 0.35);
}

@media (min-width: 640px) {
    .tl-btn { width: 44px; height: 44px; }
}

.tl-btn:hover { background: oklch(0.28 0.005 40 / 0.7); }
.tl-btn:focus-visible { outline: 2px solid oklch(0.54 0.22 27); outline-offset: 2px; }

.timeline-slider:focus-visible + .timeline-fill {
    box-shadow: 0 0 0 2px oklch(0.54 0.22 27);
}

@media (prefers-reduced-motion: reduce) {
    .tl-btn { transition: none; }
}
</style>

<style>
.boat-marker { background: transparent !important; border: none !important; }
.route-tooltip {
    background: oklch(0.08 0.008 40 / 0.85) !important;
    color: oklch(0.90 0.005 70) !important;
    border: 1px solid oklch(0.32 0.01 40 / 0.3) !important;
    border-radius: 5px !important;
    font-family: 'Outfit', system-ui, sans-serif !important;
    font-size: 12px !important;
    padding: 4px 8px !important;
    box-shadow: none !important;
}
.route-tooltip::before { border-top-color: oklch(0.32 0.01 40 / 0.3) !important; }
</style>
