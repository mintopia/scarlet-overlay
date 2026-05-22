<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useScarletMetrics } from '../../composables/useScarletMetrics';
import { useVideoFeed } from '../../composables/useVideoFeed';
import { useSpringValue } from '../../composables/useSpringValue';
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
});

const {
    boat, gps, weather, lastUpdate,
    clock, clockDate,
    coordText, isOffline, statusText, statusClass, lastUpdateText,
    wxTemp, wxCondition, wxIcon, wxSeaTemp, wxWindSpeed, wxWindDir, wxWaveHeight, wxWavePeriod,
    initMap, addMapTarget,
} = useScarletMetrics({
    initialMetrics: props.initialMetrics,
    portName: props.portName,
});

const videoRef = ref(null);
const mapPipEl = ref(null);
const mapFullEl = ref(null);

const { videoActive, videoChecked, connect: connectVideo } = useVideoFeed(videoRef);

let mapPip = null;
let mapFull = null;

const overlayState = computed(() => {
    if (isOffline.value) return 'offline';
    if (videoActive.value) return 'video-live';
    if (statusText.value === 'In Port') return 'port';
    if (videoChecked.value) return 'no-video';
    return 'loading';
});

const liveBadgeText = computed(() => {
    if (isOffline.value) return 'OFFLINE';
    return 'LIVE';
});

const liveBadgeExt = computed(() => {
    if (isOffline.value) return 'Telemetry Unavailable';
    if (videoChecked.value && !videoActive.value) return 'Video Offline';
    return '';
});

const showBadgeExt = computed(() => !!liveBadgeExt.value);

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

const animHeel = useSpringValue(() => Math.abs(boat.value?.heel ?? 0), { tension: 80, friction: 12 });

const heelSide = computed(() => {
    const heel = boat.value?.heel;
    if (heel == null) return '';
    return heel < 0 ? 'port' : 'starboard';
});

function fmtSpring(anim, raw, decimals = 1) {
    if (raw == null) return '--';
    return Number(anim).toFixed(decimals);
}

const offlineLastUpdate = computed(() => {
    if (!lastUpdate.value) return 'Last update received —';
    const ago = lastUpdate.value;
    const hh = String(ago.getHours()).padStart(2, '0');
    const mm = String(ago.getMinutes()).padStart(2, '0');
    const dd = ago.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
    return `Last update received ${hh}:${mm} · ${dd}`;
});

watch(overlayState, () => {
    nextTick(() => {
        mapPip?.invalidateSize();
        mapFull?.invalidateSize();
    });
});

onMounted(() => {
    mapPip = initMap(mapPipEl.value, { interactive: false });
    mapFull = initMap(mapFullEl.value, { interactive: false });

    addMapTarget(mapPip, { autoCenter: true });
    addMapTarget(mapFull, { autoCenter: true });

    connectVideo();
});

onUnmounted(() => {
    mapPip?.remove();
    mapFull?.remove();
});
</script>

<template>
    <Head title="Scarlet Overlay" />

    <div class="overlay" :data-state="overlayState">
        <!-- Video feed: full-screen behind all chrome -->
        <video ref="videoRef" class="video-feed" autoplay muted playsinline></video>

        <!-- PiP map (visible when video is live) -->
        <div ref="mapPipEl" class="map-pip"></div>

        <!-- Full-screen map (visible when no video) -->
        <div ref="mapFullEl" class="map-full"></div>

        <!-- TOP-LEFT: LIVE badge -->
        <div class="tl-cluster">
            <ScarletLiveBadge
                :text="liveBadgeText"
                :extension="liveBadgeExt"
                :show-extension="showBadgeExt"
            />
        </div>

        <!-- TOP-RIGHT: Weather -->
        <div class="wx-cluster">
            <ScarletWeather v-bind="weatherProps" />
        </div>

        <!-- BOTTOM-LEFT: Compass + coords + speed legend -->
        <div class="bl-cluster">
            <ScarletCompass :heading="compassHeading" :wind-direction="compassWind" :size="110" />
            <ScarletBottomBadges :coord-text="coordText" />
        </div>

        <!-- BOTTOM-RIGHT: Heel angle pill -->
        <div class="heel-pill">
            <div class="heel-lbl">HEEL</div>
            <div class="heel-val">{{ fmtSpring(animHeel, boat?.heel, 0) }}°</div>
            <div class="heel-sub">{{ heelSide }}</div>
        </div>

        <!-- Offline card -->
        <Transition name="offline-fade">
            <div v-if="overlayState === 'offline'" class="offline-card">
                <div class="offline-inner">
                    <p class="offline-title">Telemetry Unavailable</p>
                    <p class="offline-time">{{ offlineLastUpdate }}</p>
                </div>
            </div>
        </Transition>

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
.overlay {
    position: fixed;
    inset: 0;
    font-family: 'Outfit', system-ui, sans-serif;
    font-variant-numeric: tabular-nums;
    color: oklch(0.96 0.005 70);
    overflow: hidden;
    background: oklch(0.05 0.01 40);
}

/* ── Video ──────────────────────────────── */
.video-feed {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    z-index: 0;
    display: none;
    background: oklch(0.05 0.01 40);
}

[data-state="video-live"] .video-feed { display: block; }

/* ── Maps ───────────────────────────────── */
.map-pip {
    position: absolute;
    top: 16px;
    left: 16px;
    width: 280px;
    height: 200px;
    border-radius: 10px;
    overflow: hidden;
    border: 1.5px solid oklch(0.32 0.01 40 / 0.18);
    z-index: 5;
    display: none;
    background: oklch(0.12 0.02 210);
}

.map-full {
    position: absolute;
    inset: 0;
    z-index: 0;
    display: none;
    background: oklch(0.12 0.02 210);
}

[data-state="video-live"] .map-pip  { display: block; }

[data-state="no-video"] .map-full   { display: block; }
[data-state="offline"] .map-full    { display: block; opacity: 0.6; }
[data-state="port"] .map-full       { display: block; }

/* ── Chrome clusters ────────────────────── */
.tl-cluster {
    position: absolute;
    top: 16px;
    left: 16px;
    z-index: 10;
    transition: opacity 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}

[data-state="video-live"] .tl-cluster {
    left: 308px;
}

.wx-cluster {
    position: absolute;
    top: 16px;
    right: 16px;
    z-index: 10;
    transition: opacity 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}

.bl-cluster {
    position: absolute;
    bottom: 88px;
    left: 16px;
    z-index: 10;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
    opacity: 0;
    visibility: hidden;
    transform: translateY(6px);
    transition: opacity 0.4s cubic-bezier(0.16, 1, 0.3, 1),
                visibility 0.4s,
                transform 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}

[data-state="video-live"] .bl-cluster,
[data-state="no-video"] .bl-cluster,
[data-state="offline"] .bl-cluster,
[data-state="port"] .bl-cluster {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

[data-state="video-live"] .bl-cluster {
    top: 224px;
    bottom: auto;
    flex-direction: column-reverse;
}

.lt-position {
    position: absolute;
    bottom: 12px;
    left: 12px;
    right: 12px;
    z-index: 10;
    transition: opacity 0.4s cubic-bezier(0.16, 1, 0.3, 1),
                transform 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}

/* ── Offline card ───────────────────────── */
.offline-card {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    z-index: 20;
}

.offline-inner {
    background: oklch(0.08 0.008 40 / 0.72);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    border: 1px solid oklch(0.32 0.01 40 / 0.18);
    border-radius: 10px;
    padding: 40px 60px;
    text-align: center;
}

.offline-title {
    font-size: 30px;
    font-weight: 600;
    color: oklch(0.96 0.005 70);
    margin-bottom: 10px;
}

.offline-time {
    font-size: 21px;
    font-weight: 500;
    color: oklch(0.75 0.008 70);
}

/* ── Heel pill ─────────────────────────── */
.heel-pill {
    position: absolute;
    bottom: 88px;
    right: 16px;
    z-index: 10;
    background: oklch(0.08 0.008 40 / 0.72);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    border-radius: 10px;
    border: 1.5px solid oklch(0.32 0.01 40 / 0.18);
    padding: 10px 16px;
    text-align: center;
    min-width: 72px;
    opacity: 0;
    visibility: hidden;
    transform: translateY(6px);
    transition: opacity 0.4s cubic-bezier(0.16, 1, 0.3, 1),
                visibility 0.4s,
                transform 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}

[data-state="video-live"] .heel-pill,
[data-state="no-video"] .heel-pill,
[data-state="port"] .heel-pill {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.heel-lbl {
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.04em;
    color: oklch(0.62 0.008 70);
    margin-bottom: 2px;
}

.heel-val {
    font-size: 21px;
    font-weight: 500;
    color: oklch(0.96 0.005 70);
    line-height: 1.1;
}

.heel-sub {
    font-size: 14px;
    color: oklch(0.62 0.008 70);
    margin-top: 2px;
}

/* ── State-dependent opacity ────────────── */
[data-state="offline"] .wx-cluster { opacity: 0.5; }
[data-state="offline"] .heel-pill { opacity: 0.5; visibility: visible; transform: translateY(0); }

[data-state="loading"] .tl-cluster,
[data-state="loading"] .wx-cluster,
[data-state="loading"] .bl-cluster,
[data-state="loading"] .heel-pill,
[data-state="loading"] .lt-position {
    opacity: 0;
    transform: translateY(6px);
}

/* ── Offline card entrance ─────────────── */
.offline-fade-enter-active,
.offline-fade-leave-active {
    transition: opacity 0.5s cubic-bezier(0.16, 1, 0.3, 1),
                transform 0.5s cubic-bezier(0.16, 1, 0.3, 1);
}
.offline-fade-enter-from,
.offline-fade-leave-to {
    opacity: 0;
    transform: translate(-50%, -50%) scale(0.96);
}

/* ── Reduced motion ────────────────────── */
@media (prefers-reduced-motion: reduce) {
    .tl-cluster,
    .wx-cluster,
    .bl-cluster,
    .lt-position,
    .offline-fade-enter-active,
    .offline-fade-leave-active {
        transition: none;
    }
}

/* ── Overlay text scale (~1.5x) ────────── */

/* Weather pills */
.overlay :deep(.pill-lbl) { font-size: 12px; }
.overlay :deep(.pill-val) { font-size: 21px; }
.overlay :deep(.pill-sub) { font-size: 14px; }
.overlay :deep(.pill) { padding: 10px 16px; min-width: 72px; }
.overlay :deep(.pill--hero) { padding: 10px 18px; }
.overlay :deep(.pill--hero .pill-val) { font-size: 24px; }
.overlay :deep(.wx-icon) { font-size: 30px; }

/* Lower third */
.overlay :deep(.lower-third) { min-height: 64px; border-radius: 12px; }
.overlay :deep(.lt-brand) { padding: 0 30px; }
.overlay :deep(.lt-brand::after) { right: -22px; width: 22px; }
.overlay :deep(.lt-name) { font-size: 27px; }
.overlay :deep(.lt-body) { padding: 14px 24px 14px 40px; gap: 14px; }
.overlay :deep(.lt-label) { font-size: 12px; }
.overlay :deep(.lt-val) { font-size: 24px; }
.overlay :deep(.lt-sep) { height: 32px; }
.overlay :deep(.lt-status) { font-size: 15px; padding: 6px 14px; }
.overlay :deep(.lt-passage) { font-size: 21px; }
.overlay :deep(.lt-port-label) { font-size: 16px; }
.overlay :deep(.lt-time) { font-size: 24px; }
.overlay :deep(.lt-date) { font-size: 14px; }

/* Bottom badges */
.overlay :deep(.coord-badge) { font-size: 20px; padding: 9px 18px; }
.overlay :deep(.speed-legend) { font-size: 15px; padding: 7px 16px; }
.overlay :deep(.legend-gradient) { width: 72px; }

/* Compass heading readout */
.overlay :deep(.compass-heading) { font-size: 15px; padding: 2px 8px; }
</style>

<style>
.boat-marker {
    background: transparent !important;
    border: none !important;
}

[data-state="port"] .leaflet-overlay-pane { display: none; }
[data-state="offline"] .leaflet-overlay-pane { opacity: 0.5; }
[data-state="offline"] .boat-marker { opacity: 0.5; }
</style>
