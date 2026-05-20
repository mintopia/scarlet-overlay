<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useScarletMetrics } from '../../composables/useScarletMetrics';
import { useVideoFeed } from '../../composables/useVideoFeed';
import ScarletWeather from '../../components/ScarletWeather.vue';
import ScarletLiveBadge from '../../components/ScarletLiveBadge.vue';
import ScarletBottomBadges from '../../components/ScarletBottomBadges.vue';
import ScarletLowerThird from '../../components/ScarletLowerThird.vue';

const props = defineProps({
    initialMetrics: Object,
    boatName: String,
    passageFrom: String,
    passageTo: String,
    portName: String,
});

const {
    boat, gps, lastUpdate,
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
}));

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

        <!-- BOTTOM-LEFT: Coords + speed legend -->
        <div class="bl-cluster">
            <ScarletBottomBadges :coord-text="coordText" />
        </div>

        <!-- Offline card -->
        <div v-if="overlayState === 'offline'" class="offline-card">
            <div class="offline-inner">
                <p class="offline-title">Telemetry Unavailable</p>
                <p class="offline-time">{{ offlineLastUpdate }}</p>
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
}

[data-state="video-live"] .tl-cluster {
    left: 308px;
}

.wx-cluster {
    position: absolute;
    top: 16px;
    right: 16px;
    z-index: 10;
}

.bl-cluster {
    position: absolute;
    bottom: 68px;
    left: 16px;
    z-index: 10;
    display: none;
}

[data-state="no-video"] .bl-cluster,
[data-state="offline"] .bl-cluster,
[data-state="port"] .bl-cluster {
    display: block;
}

.lt-position {
    position: absolute;
    bottom: 12px;
    left: 12px;
    right: 12px;
    z-index: 10;
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
    padding: 30px 44px;
    text-align: center;
}

.offline-title {
    font-size: 20px;
    font-weight: 600;
    color: oklch(0.96 0.005 70);
    margin-bottom: 8px;
}

.offline-time {
    font-size: 14px;
    font-weight: 500;
    color: oklch(0.75 0.008 70);
}

/* ── State-dependent opacity ────────────── */
[data-state="offline"] .wx-cluster { opacity: 0.5; }

[data-state="loading"] .tl-cluster,
[data-state="loading"] .wx-cluster,
[data-state="loading"] .bl-cluster,
[data-state="loading"] .lt-position {
    opacity: 0;
}
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
