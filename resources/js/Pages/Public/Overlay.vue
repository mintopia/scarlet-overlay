<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useScarletMetrics } from '../../composables/useScarletMetrics';
import { useVideoFeed } from '../../composables/useVideoFeed';
import { formatVal } from '../../scarlet';

const props = defineProps({
    initialMetrics: Object,
    boatName: String,
    passageFrom: String,
    passageTo: String,
    portName: String,
    utcOffset: Number,
    timeLabel: String,
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
    utcOffset: props.utcOffset,
    timeLabel: props.timeLabel,
});

const videoRef = ref(null);
const mapPipEl = ref(null);
const mapFullEl = ref(null);

const { videoActive, videoChecked, connect: connectVideo } = useVideoFeed(videoRef);

let mapPip = null;
let mapFull = null;

const overlayState = computed(() => {
    if (isOffline.value) return 'offline';
    if (statusText.value === 'In Port') return 'port';
    if (videoActive.value) return 'video-live';
    if (videoChecked.value) return 'no-video';
    return 'loading';
});

const liveBadgeText = computed(() => {
    if (overlayState.value === 'offline') return 'OFFLINE';
    return 'LIVE';
});

const liveBadgeExt = computed(() => {
    if (overlayState.value === 'offline') return 'Telemetry Unavailable';
    if (overlayState.value === 'no-video') return 'Video Offline';
    if (overlayState.value === 'port') return lastUpdateText.value ? `Updated ${lastUpdateText.value}` : '';
    return '';
});

const showLiveExt = computed(() => {
    return ['no-video', 'port', 'offline'].includes(overlayState.value);
});

const offlineLastUpdate = computed(() => {
    if (!lastUpdate.value) return 'Last update received —';
    const ago = lastUpdate.value;
    const hh = String(ago.getHours()).padStart(2, '0');
    const mm = String(ago.getMinutes()).padStart(2, '0');
    const dd = ago.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
    return `Last update received ${hh}:${mm} · ${dd}`;
});

function scaleToViewport() {
    const overlay = document.getElementById('overlay');
    if (!overlay) return;
    const scaleX = window.innerWidth / 1920;
    const scaleY = window.innerHeight / 1080;
    overlay.style.transform = `scale(${Math.min(scaleX, scaleY)})`;
    setTimeout(() => {
        mapPip?.invalidateSize();
        mapFull?.invalidateSize();
    }, 100);
}

onMounted(() => {
    scaleToViewport();
    window.addEventListener('resize', scaleToViewport);

    mapPip = initMap(mapPipEl.value, { interactive: false });
    mapFull = initMap(mapFullEl.value);

    addMapTarget(mapPip, { autoCenter: true });
    addMapTarget(mapFull, { autoCenter: computed(() => overlayState.value !== 'video-live') });

    connectVideo();
});

onUnmounted(() => {
    window.removeEventListener('resize', scaleToViewport);
    mapPip?.remove();
    mapFull?.remove();
});
</script>

<template>
    <Head title="Scarlet Overlay" />

    <div id="overlay" :data-state="overlayState">
        <!-- Video feed -->
        <video ref="videoRef" id="video-feed" autoplay muted playsinline></video>

        <!-- PiP map -->
        <div ref="mapPipEl" id="map-pip"></div>

        <!-- Full-screen map -->
        <div ref="mapFullEl" id="map-full"></div>

        <!-- LIVE / OFFLINE badge -->
        <div id="live-badge">
            <div class="live-scarlet">
                <span class="live-dot"></span>
                <span class="live-text">{{ liveBadgeText }}</span>
            </div>
            <span v-if="showLiveExt" id="live-extension" class="live-ext">{{ liveBadgeExt }}</span>
        </div>

        <!-- Weather pills -->
        <div id="weather-strip">
            <div class="weather-pill weather-pill--hero">
                <span class="wx-icon">{{ wxIcon }}</span>
                <div>
                    <div class="wx-val">{{ wxTemp }}</div>
                    <div class="wx-sub">{{ wxCondition }}</div>
                </div>
            </div>
            <div class="weather-pill">
                <div class="wx-label">SEA</div>
                <div class="wx-val">{{ wxSeaTemp }}</div>
            </div>
            <div class="weather-pill">
                <div class="wx-label">WIND</div>
                <div class="wx-val">{{ wxWindSpeed }}</div>
                <div class="wx-sub">{{ wxWindDir }}</div>
            </div>
            <div class="weather-pill">
                <div class="wx-label">WAVES</div>
                <div class="wx-val">{{ wxWaveHeight }}</div>
                <div class="wx-sub">{{ wxWavePeriod }}</div>
            </div>
        </div>

        <!-- Bottom-left cluster -->
        <div id="bottom-badges">
            <div id="coord-badge">{{ coordText }}</div>
            <div id="speed-legend">
                <span>0 kn</span>
                <div class="speed-legend-track"></div>
                <span>10 kn</span>
            </div>
        </div>

        <!-- Offline card -->
        <div id="offline-card">
            <div class="offline-card-inner">
                <p class="offline-title">Telemetry Unavailable</p>
                <p class="offline-time">{{ offlineLastUpdate }}</p>
            </div>
        </div>

        <!-- Lower third -->
        <div id="lower-third">
            <div class="lt-brand">
                <span class="lt-name">{{ boatName }}</span>
            </div>
            <div class="lt-body">
                <template v-if="overlayState !== 'port'">
                    <div class="lt-metric">
                        <div class="lt-metric-label">SPEED</div>
                        <div class="lt-metric-value">{{ isOffline ? '—' : `${formatVal(boat?.speed_sog)} kn` }}</div>
                    </div>
                    <div class="lt-sep"></div>
                    <div class="lt-metric">
                        <div class="lt-metric-label">HEADING</div>
                        <div class="lt-metric-value">{{ isOffline ? '—' : `${formatVal(boat?.heading, 0)}°` }}</div>
                    </div>
                    <div class="lt-sep"></div>
                    <div class="lt-metric">
                        <div class="lt-metric-label">DEPTH</div>
                        <div class="lt-metric-value">{{ isOffline ? '—' : `${formatVal(boat?.depth)} m` }}</div>
                    </div>
                    <div class="lt-sep"></div>
                </template>

                <div class="lt-status" :class="statusClass">{{ statusText }}</div>

                <div class="lt-sep"></div>

                <div v-if="overlayState === 'port' && portName" class="lt-port">
                    <span class="lt-port-label">Currently at</span> {{ portName }}
                </div>
                <div v-else-if="passageFrom || passageTo" class="lt-passage">
                    <strong>{{ passageFrom }}</strong>
                    <span v-if="passageFrom && passageTo"> &rarr; </span>
                    <strong>{{ passageTo }}</strong>
                </div>

                <div class="lt-clock">
                    <div class="lt-time">{{ clock }}</div>
                    <div class="lt-date">{{ clockDate }}</div>
                </div>
            </div>
        </div>
    </div>
</template>

<style>
/* ════════════════════════════════════════════════════════════════════════
   Scarlet Overlay — broadcast overlay for OBS compositing
   Design canvas: 1920×1080, scaled to fit viewport via transform.
   ════════════════════════════════════════════════════════════════════════ */

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

:root {
    /* Brand */
    --scarlet:       oklch(0.54 0.22 27);
    --scarlet-light: oklch(0.62 0.18 27);
    --scarlet-glow:  oklch(0.54 0.22 27 / 0.25);

    /* Chrome (glass panels) */
    --chrome-55:    oklch(0.10 0.008 40 / 0.55);
    --chrome-72:    oklch(0.08 0.008 40 / 0.72);
    --chrome-border: oklch(0.32 0.01 40 / 0.18);

    /* Text */
    --text-bright: oklch(0.96 0.005 70);
    --text-mid:    oklch(0.75 0.008 70);
    --text-dim:    oklch(0.62 0.008 70);
    --text-stale:  oklch(0.45 0.005 70);

    /* Status colours */
    --status-sail:  oklch(0.78 0.12 155);
    --status-power: oklch(0.75 0.10 70);
    --status-port:  oklch(0.72 0.08 230);
    --status-offline: oklch(0.65 0.06 55);

    /* Speed gradient stops */
    --speed-0:  oklch(0.58 0.20 260);
    --speed-5:  oklch(0.72 0.22 155);
    --speed-10: oklch(0.62 0.28 27);

    /* Radii */
    --radius:    10px;
    --radius-sm: 7px;

    /* Typography */
    --font: 'Outfit', system-ui, sans-serif;

    /* Blur */
    --blur: blur(24px);
}

/* ── Base ──────────────────────────────────────────────────────────────── */

html, body {
    width: 100vw;
    height: 100vh;
    overflow: hidden;
    background: oklch(0.05 0.01 40);
    font-family: var(--font);
    font-variant-numeric: tabular-nums;
    color: var(--text-bright);
}

#overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 1920px;
    height: 1080px;
    overflow: hidden;
    transform-origin: top left;
}

/* ── Video feed ───────────────────────────────────────────────────────── */

#video-feed {
    position: absolute;
    inset: 0;
    width: 1920px;
    height: 1080px;
    object-fit: cover;
    z-index: 0;
    display: none;
    background: oklch(0.05 0.01 40);
}

[data-state="video-live"] #video-feed { display: block; }

/* ── Maps ──────────────────────────────────────────────────────────────── */

#map-pip {
    position: absolute;
    top: 32px;
    left: 32px;
    width: 380px;
    height: 260px;
    border-radius: var(--radius);
    overflow: hidden;
    border: 1.5px solid var(--chrome-border);
    z-index: 10;
    background: oklch(0.12 0.02 210);
}

#map-full {
    position: absolute;
    inset: 0;
    width: 1920px;
    height: 1080px;
    z-index: 1;
    display: none;
    background: oklch(0.12 0.02 210);
}

[data-state="video-live"] #map-pip  { display: block; }
[data-state="video-live"] #map-full { display: none;  }

[data-state="no-video"] #map-pip    { display: none;  }
[data-state="no-video"] #map-full   { display: block; }

[data-state="offline"] #map-pip     { display: none;  }
[data-state="offline"] #map-full    { display: block; opacity: 0.6; }

[data-state="port"] #map-pip        { display: none;  }
[data-state="port"] #map-full       { display: block; }

[data-state="port"] .leaflet-overlay-pane { display: none; }

[data-state="offline"] .leaflet-overlay-pane { opacity: 0.5; }
[data-state="offline"] .boat-marker          { opacity: 0.5; }

.boat-marker {
    background: none !important;
    border: none !important;
}

/* ── LIVE / OFFLINE badge ──────────────────────────────────────────────── */

#live-badge {
    position: absolute;
    top: 32px;
    left: 32px;
    z-index: 20;
    display: flex;
    align-items: stretch;
    border-radius: var(--radius-sm);
    overflow: hidden;
    box-shadow: 0 2px 12px var(--scarlet-glow);
}

[data-state="video-live"] #live-badge {
    left: 424px;
}

.live-scarlet {
    display: flex;
    align-items: center;
    gap: 7px;
    background: var(--scarlet);
    padding: 7px 14px 7px 10px;
}

.live-dot {
    width: 9px;
    height: 9px;
    border-radius: 50%;
    background: var(--text-bright);
    animation: live-pulse 2s ease-in-out infinite;
    flex-shrink: 0;
}

@keyframes live-pulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50%       { opacity: 0.5; transform: scale(0.85); }
}

.live-text {
    font-size: 14px;
    font-weight: 700;
    letter-spacing: 0.08em;
    color: var(--text-bright);
}

#live-extension {
    display: none;
    align-items: center;
    background: var(--chrome-72);
    backdrop-filter: var(--blur);
    -webkit-backdrop-filter: var(--blur);
    padding: 7px 12px;
    font-size: 13px;
    font-weight: 600;
    color: var(--text-dim);
    letter-spacing: 0.03em;
}

[data-state="no-video"] #live-extension,
[data-state="port"]     #live-extension {
    display: flex;
}

[data-state="offline"] #live-badge {
    box-shadow: none;
}

[data-state="offline"] .live-scarlet {
    background: var(--chrome-72);
    backdrop-filter: var(--blur);
    -webkit-backdrop-filter: var(--blur);
    border: 1px solid var(--chrome-border);
    border-right: none;
    border-radius: var(--radius-sm) 0 0 var(--radius-sm);
}

[data-state="offline"] .live-dot {
    background: var(--status-offline);
    animation: none;
}

[data-state="offline"] .live-text {
    color: var(--text-dim);
}

[data-state="offline"] #live-extension {
    display: flex;
    color: var(--text-stale);
    border: 1px solid var(--chrome-border);
    border-left: 1px solid var(--chrome-border);
    border-radius: 0 var(--radius-sm) var(--radius-sm) 0;
}

/* ── Weather pills ─────────────────────────────────────────────────────── */

#weather-strip {
    position: absolute;
    top: 32px;
    right: 32px;
    display: flex;
    gap: 8px;
    z-index: 15;
}

.weather-pill {
    background: var(--chrome-72);
    backdrop-filter: var(--blur);
    -webkit-backdrop-filter: var(--blur);
    border-radius: var(--radius-sm);
    padding: 10px 18px;
    text-align: center;
    border: 1px solid var(--chrome-border);
    min-width: 80px;
}

.weather-pill--hero {
    display: flex;
    gap: 12px;
    align-items: center;
    text-align: left;
    padding: 10px 20px;
}

.wx-icon {
    font-size: 30px;
    line-height: 1;
    flex-shrink: 0;
}

.weather-pill--hero .wx-val {
    font-size: 26px;
    font-weight: 700;
}

.wx-label {
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.04em;
    color: var(--text-dim);
    margin-bottom: 3px;
}

.wx-val {
    font-size: 22px;
    font-weight: 500;
    color: var(--text-bright);
    line-height: 1.1;
}

.wx-sub {
    font-size: 14px;
    color: var(--text-dim);
    margin-top: 2px;
}

[data-state="offline"] #weather-strip {
    opacity: 0.5;
}

[data-state="offline"] .wx-label {
    color: var(--text-stale);
}

[data-state="offline"] .wx-val {
    color: var(--text-dim);
}

/* ── Bottom-left cluster (coord + speed legend) ──────────────────────── */

#bottom-badges {
    position: absolute;
    bottom: 148px;
    left: 32px;
    z-index: 15;
    display: none;
    flex-direction: column;
    gap: 8px;
}

[data-state="no-video"] #bottom-badges,
[data-state="offline"]  #bottom-badges,
[data-state="port"]     #bottom-badges {
    display: flex;
}

#coord-badge {
    font-size: 22px;
    font-weight: 500;
    color: var(--text-bright);
    background: var(--chrome-72);
    backdrop-filter: var(--blur);
    -webkit-backdrop-filter: var(--blur);
    padding: 10px 20px;
    border-radius: var(--radius-sm);
    border: 1px solid var(--chrome-border);
    letter-spacing: 0.02em;
}

[data-state="offline"] #coord-badge {
    color: var(--text-dim);
}

/* ── Speed legend (inline) ────────────────────────────────────────────── */

#speed-legend {
    display: none;
    align-items: center;
    gap: 10px;
    font-size: 14px;
    font-weight: 500;
    color: var(--text-dim);
    background: var(--chrome-72);
    backdrop-filter: var(--blur);
    -webkit-backdrop-filter: var(--blur);
    padding: 8px 16px;
    border-radius: var(--radius-sm);
    border: 1px solid var(--chrome-border);
    width: fit-content;
}

[data-state="no-video"] #speed-legend {
    display: flex;
}

.speed-legend-track {
    width: 80px;
    height: 4px;
    border-radius: 2px;
    background: linear-gradient(to right, var(--speed-0), var(--speed-5), var(--speed-10));
}

/* ── Offline card ──────────────────────────────────────────────────────── */

#offline-card {
    display: none;
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    z-index: 20;
}

[data-state="offline"] #offline-card {
    display: block;
}

.offline-card-inner {
    background: var(--chrome-72);
    backdrop-filter: var(--blur);
    -webkit-backdrop-filter: var(--blur);
    border: 1px solid var(--chrome-border);
    border-radius: var(--radius);
    padding: 40px 56px;
    text-align: center;
}

.offline-title {
    font-size: 30px;
    font-weight: 600;
    color: var(--text-bright);
    margin-bottom: 10px;
}

.offline-time {
    font-size: 22px;
    font-weight: 500;
    color: var(--text-mid);
}

/* ── Lower third ───────────────────────────────────────────────────────── */

#lower-third {
    position: absolute;
    bottom: 32px;
    left: 32px;
    right: 32px;
    display: flex;
    align-items: stretch;
    border-radius: var(--radius);
    overflow: hidden;
    z-index: 15;
}

.lt-brand {
    background: var(--scarlet);
    padding: 0 28px;
    display: flex;
    align-items: center;
    position: relative;
    flex-shrink: 0;
    z-index: 1;
}

.lt-brand::after {
    content: '';
    position: absolute;
    right: -22px;
    top: 0;
    width: 22px;
    height: 100%;
    background: var(--scarlet);
    clip-path: polygon(0 0, 0 100%, 100% 100%);
}

.lt-name {
    font-size: 30px;
    font-weight: 700;
    letter-spacing: 0.03em;
    color: var(--text-bright);
    white-space: nowrap;
}

.lt-body {
    flex: 1;
    display: flex;
    align-items: center;
    background: var(--chrome-72);
    backdrop-filter: var(--blur);
    -webkit-backdrop-filter: var(--blur);
    padding: 18px 26px 18px 40px;
    margin-left: -16px;
    gap: 14px;
}

.lt-metric {
    padding: 0 6px;
    flex-shrink: 0;
}

.lt-metric-label {
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.06em;
    color: var(--text-dim);
    line-height: 1;
    white-space: nowrap;
    margin-bottom: 2px;
}

.lt-metric-value {
    font-size: 24px;
    font-weight: 600;
    color: var(--text-bright);
    line-height: 1.2;
    white-space: nowrap;
}

[data-state="offline"] .lt-metric-label {
    color: var(--text-stale);
}

[data-state="offline"] .lt-metric-value {
    color: var(--text-dim);
}

.lt-sep {
    width: 1px;
    height: 34px;
    background: var(--chrome-border);
    flex-shrink: 0;
}

.lt-status {
    font-size: 18px;
    font-weight: 600;
    letter-spacing: 0.03em;
    padding: 6px 16px;
    border-radius: 5px;
    white-space: nowrap;
    flex-shrink: 0;
}

.status-sail {
    color: var(--status-sail);
    background: oklch(0.78 0.12 155 / 0.12);
}

.status-power {
    color: var(--status-power);
    background: oklch(0.75 0.10 70 / 0.12);
}

.status-port {
    color: var(--status-port);
    background: oklch(0.72 0.08 230 / 0.12);
}

.status-offline {
    color: var(--status-offline);
    background: oklch(0.65 0.06 55 / 0.12);
}

.lt-passage {
    font-size: 24px;
    font-weight: 500;
    color: var(--text-mid);
    white-space: nowrap;
    padding: 0 10px;
    flex: 1;
    text-align: center;
}

.lt-passage strong {
    color: var(--text-bright);
    font-weight: 600;
}

.lt-port {
    font-size: 24px;
    font-weight: 600;
    color: var(--text-bright);
    white-space: nowrap;
    padding: 0 10px;
}

.lt-port-label {
    font-size: 17px;
    font-weight: 500;
    color: var(--text-dim);
    margin-right: 8px;
}

.lt-clock {
    text-align: right;
    padding: 0 8px;
    flex-shrink: 0;
}

.lt-time {
    font-size: 28px;
    font-weight: 600;
    line-height: 1;
    color: var(--text-bright);
}

.lt-date {
    font-size: 16px;
    color: var(--text-dim);
    margin-top: 4px;
    letter-spacing: 0.01em;
}

/* ── Loading state: hide everything until connected ────────────────────── */

[data-state="loading"] #lower-third,
[data-state="loading"] #weather-strip,
[data-state="loading"] #coord-badge,
[data-state="loading"] #speed-legend,
[data-state="loading"] #live-badge {
    opacity: 0;
}
</style>
