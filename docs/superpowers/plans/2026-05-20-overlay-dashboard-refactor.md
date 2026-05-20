# Overlay + Dashboard Refactor Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Unify overlay and dashboard into two Vue/Inertia pages sharing a `useScarletMetrics()` composable for WebSocket, maps, weather, status, and clock.

**Architecture:** Extract all shared reactive state into `useScarletMetrics()` composable. Extract video logic into `useVideoFeed()` composable. Convert overlay from Blade+vanilla JS to Vue/Inertia page with bare layout. Both pages become thin templates.

**Tech Stack:** Vue 3 (Composition API), Inertia.js, Leaflet, Laravel Echo/Reverb, Vite

---

### Task 1: Create `useScarletMetrics` composable

**Files:**
- Create: `resources/js/composables/useScarletMetrics.js`

This is the core shared composable. It provides reactive state for boat/gps/weather data, WebSocket subscription, clock, status computation, weather formatting, and multi-map management with boat markers and speed-coloured track.

- [ ] **Step 1: Create the composable file**

```js
// resources/js/composables/useScarletMetrics.js
import { ref, computed, unref, onUnmounted } from 'vue';
import L from 'leaflet';
import { speedToColor, makeBoatIcon, formatCoord, getWeatherIcon, getWeatherLabel } from '../scarlet';

export function useScarletMetrics(options = {}) {
    const {
        initialMetrics = null,
        portName = '',
        utcOffset = null,
        timeLabel = null,
    } = options;

    // ── Reactive state ──────────────────────────────────────────────────
    const boat = ref(initialMetrics?.boat ?? {});
    const gps = ref(initialMetrics?.gps ?? {});
    const weather = ref(initialMetrics?.weather ?? null);
    const lastUpdate = ref(initialMetrics ? new Date() : null);
    const clock = ref('--:--');
    const clockDate = ref('');

    // ── Computed ─────────────────────────────────────────────────────────
    const coordText = computed(() => formatCoord(gps.value?.latitude, gps.value?.longitude));

    const isOffline = computed(() => {
        if (!lastUpdate.value) return false;
        return Date.now() - lastUpdate.value.getTime() > 2 * 60 * 60 * 1000;
    });

    const statusText = computed(() => {
        if (isOffline.value) return 'Offline';
        const sog = boat.value?.speed_sog;
        const rpm = boat.value?.engine_rpm;
        if ((sog == null || sog < 0.5) && portName) return 'In Port';
        if (rpm != null && rpm > 0) return 'Under Power';
        return 'Under Sail';
    });

    const statusClass = computed(() => {
        const map = {
            'Offline': 'status-offline',
            'In Port': 'status-port',
            'Under Power': 'status-power',
            'Under Sail': 'status-sail',
        };
        return map[statusText.value] ?? 'status-sail';
    });

    const lastUpdateText = computed(() => {
        if (!lastUpdate.value) return '';
        return lastUpdate.value.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
    });

    // ── Weather computed ─────────────────────────────────────────────────
    const wxTemp = computed(() => weather.value?.temp != null ? `${Number(weather.value.temp).toFixed(1)}°` : '--');
    const wxCondition = computed(() => getWeatherLabel(weather.value?.summary));
    const wxIcon = computed(() => getWeatherIcon(weather.value?.summary));
    const wxSeaTemp = computed(() => weather.value?.seaTemp != null ? `${Number(weather.value.seaTemp).toFixed(1)}°` : '--');
    const wxWindSpeed = computed(() => weather.value?.wind?.speed != null ? `${Math.round(weather.value.wind.speed)} kn` : '--');
    const wxWindDir = computed(() => weather.value?.wind?.direction ?? '');
    const wxWaveHeight = computed(() => weather.value?.waves?.height != null ? `${Number(weather.value.waves.height).toFixed(1)} m` : '--');
    const wxWavePeriod = computed(() => weather.value?.waves?.period != null ? `${Math.round(weather.value.waves.period)} s` : '');

    // ── Clock ────────────────────────────────────────────────────────────
    function updateClock() {
        if (utcOffset != null) {
            const now = new Date();
            const utcMs = now.getTime() + now.getTimezoneOffset() * 60000;
            const local = new Date(utcMs + utcOffset * 3600000);
            const hh = String(local.getHours()).padStart(2, '0');
            const mm = String(local.getMinutes()).padStart(2, '0');
            const day = local.getDate();
            const mon = local.toLocaleDateString('en-GB', { month: 'short' });
            clock.value = `${hh}:${mm}`;
            clockDate.value = timeLabel ? `${day} ${mon} · ${timeLabel}` : `${day} ${mon}`;
        } else {
            const now = new Date();
            clock.value = now.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
            clockDate.value = now.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
        }
    }

    updateClock();
    const clockInterval = setInterval(updateClock, 1000);

    // ── Map management ───────────────────────────────────────────────────
    const mapTargets = [];
    const trackPoints = [];

    function initMap(el, opts = {}) {
        const interactive = opts.interactive !== false;
        const mapOpts = {
            zoomControl: false,
            attributionControl: false,
        };
        if (!interactive) {
            Object.assign(mapOpts, {
                dragging: false,
                scrollWheelZoom: false,
                touchZoom: false,
                doubleClickZoom: false,
                keyboard: false,
            });
        }
        const initialPos = gps.value?.latitude
            ? [gps.value.latitude, gps.value.longitude]
            : [50.6931, -1.6433];
        const map = L.map(el, mapOpts).setView(initialPos, 14);
        L.tileLayer('/openseamap/{z}/{x}/{y}', { maxZoom: 18 }).addTo(map);
        return map;
    }

    function addMapTarget(map, opts = {}) {
        const target = {
            map,
            marker: null,
            segments: [],
            autoCenter: opts.autoCenter ?? true,
        };
        mapTargets.push(target);
        if (gps.value?.latitude) {
            updateSingleMap(target, gps.value, boat.value);
        }
    }

    function removeMapTarget(map) {
        const idx = mapTargets.findIndex(t => t.map === map);
        if (idx !== -1) mapTargets.splice(idx, 1);
    }

    function updateSingleMap(target, newGps, newBoat) {
        const pos = [newGps.latitude, newGps.longitude];
        const heading = newBoat?.heading ?? newBoat?.cog ?? 0;
        const icon = makeBoatIcon(heading);

        if (target.marker) {
            target.marker.setLatLng(pos).setIcon(icon);
        } else {
            target.marker = L.marker(pos, { icon }).addTo(target.map);
        }

        if (trackPoints.length > 1) {
            const prev = trackPoints[trackPoints.length - 2];
            const curr = trackPoints[trackPoints.length - 1];
            const seg = L.polyline([prev.pos, pos], {
                color: speedToColor(curr.speed),
                weight: 3,
                opacity: 0.85,
            }).addTo(target.map);
            target.segments.push(seg);
        }

        const ac = unref(target.autoCenter);
        if (ac) target.map.setView(pos);
    }

    function updateAllMaps(newGps, newBoat) {
        if (!newGps?.latitude || !newGps?.longitude) return;
        const speed = newBoat?.speed_sog ?? 0;
        trackPoints.push({ pos: [newGps.latitude, newGps.longitude], speed });
        mapTargets.forEach(t => updateSingleMap(t, newGps, newBoat));
    }

    // ── WebSocket ────────────────────────────────────────────────────────
    let echoChannel = null;
    if (window.Echo) {
        echoChannel = window.Echo.channel('metrics');
        echoChannel.listen('.metrics.updated', (data) => {
            boat.value = data.boat;
            gps.value = data.gps;
            if (data.weather) weather.value = data.weather;
            lastUpdate.value = new Date();
            updateAllMaps(data.gps, data.boat);
        });
    }

    // ── Cleanup ──────────────────────────────────────────────────────────
    function cleanup() {
        clearInterval(clockInterval);
        if (echoChannel) {
            window.Echo.leave('metrics');
            echoChannel = null;
        }
    }

    onUnmounted(cleanup);

    return {
        boat, gps, weather, lastUpdate,
        clock, clockDate,
        coordText, isOffline, statusText, statusClass, lastUpdateText,
        wxTemp, wxCondition, wxIcon, wxSeaTemp, wxWindSpeed, wxWindDir, wxWaveHeight, wxWavePeriod,
        initMap, addMapTarget, removeMapTarget,
        cleanup,
    };
}
```

- [ ] **Step 2: Verify the file has no syntax errors**

Run: `node -c resources/js/composables/useScarletMetrics.js`
Expected: no output (success)

- [ ] **Step 3: Commit**

```bash
git add resources/js/composables/useScarletMetrics.js
git commit -m "feat: add useScarletMetrics composable for shared overlay/dashboard logic"
```

---

### Task 2: Create `useVideoFeed` composable

**Files:**
- Create: `resources/js/composables/useVideoFeed.js`

Extracts all WHEP/HLS video connection logic, watchdog, and retry from the overlay into a standalone composable.

- [ ] **Step 1: Create the composable file**

```js
// resources/js/composables/useVideoFeed.js
import { ref, onUnmounted } from 'vue';

export function useVideoFeed(videoEl) {
    const videoActive = ref(false);
    const videoChecked = ref(false);

    let peerConnection = null;
    let retryTimer = null;
    let watchdogTimer = null;
    let lastFramesDecoded = null;
    let videoStallCount = 0;

    const WHEP_URL = '/rtc/live/whep';
    const HLS_URL = '/hls/live/index.m3u8';
    const RETRY_DELAY = 5000;

    function getVideoElement() {
        return videoEl.value;
    }

    async function startWhep() {
        const pc = new RTCPeerConnection();
        let timeoutId;

        try {
            pc.addTransceiver('video', { direction: 'recvonly' });
            pc.addTransceiver('audio', { direction: 'recvonly' });

            const video = getVideoElement();
            pc.ontrack = (e) => {
                if (video && e.streams[0]) {
                    video.srcObject = e.streams[0];
                }
            };

            const connected = new Promise((resolve, reject) => {
                timeoutId = setTimeout(() => reject(new Error('WHEP timeout')), 5000);
                pc.addEventListener('connectionstatechange', () => {
                    if (pc.connectionState === 'connected') { clearTimeout(timeoutId); resolve(); }
                    if (pc.connectionState === 'failed')    { clearTimeout(timeoutId); reject(new Error('WHEP failed')); }
                });
            });

            const offer = await pc.createOffer();
            await pc.setLocalDescription(offer);

            const res = await fetch(WHEP_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/sdp' },
                body: offer.sdp,
            });
            if (!res.ok) throw new Error(`WHEP ${res.status}`);

            const answer = await res.text();
            await pc.setRemoteDescription({ type: 'answer', sdp: answer });
            await connected;
            return pc;
        } catch (e) {
            clearTimeout(timeoutId);
            try { pc.close(); } catch {}
            throw e;
        }
    }

    function startHls() {
        const video = getVideoElement();
        if (!video) return Promise.reject(new Error('No video element'));

        if (!video.canPlayType('application/vnd.apple.mpegurl')) {
            return Promise.reject(new Error('HLS not supported'));
        }

        return new Promise((resolve, reject) => {
            const timeout = setTimeout(() => {
                video.removeEventListener('playing', onPlaying);
                video.removeEventListener('error', onError);
                reject(new Error('HLS timeout'));
            }, 10000);

            const onPlaying = () => {
                clearTimeout(timeout);
                video.removeEventListener('error', onError);
                resolve();
            };
            const onError = () => {
                clearTimeout(timeout);
                video.removeEventListener('playing', onPlaying);
                reject(new Error('HLS playback error'));
            };

            video.addEventListener('playing', onPlaying, { once: true });
            video.addEventListener('error', onError, { once: true });
            video.src = HLS_URL;
            video.play().catch(() => reject(new Error('HLS play rejected')));
        });
    }

    function teardownPlayer() {
        stopWatchdog();

        if (peerConnection) {
            try { peerConnection.close(); } catch {}
            peerConnection = null;
        }

        const video = getVideoElement();
        if (video) {
            video.srcObject = null;
            video.removeAttribute('src');
            video.load();
        }
    }

    function startWatchdog() {
        stopWatchdog();
        lastFramesDecoded = null;
        videoStallCount = 0;

        watchdogTimer = setInterval(async () => {
            if (!videoActive.value || !peerConnection) return;

            try {
                const stats = await peerConnection.getStats();
                let currentFrames = 0;
                stats.forEach(report => {
                    if (report.type === 'inbound-rtp' && report.kind === 'video') {
                        currentFrames = report.framesDecoded || 0;
                    }
                });

                if (lastFramesDecoded !== null && currentFrames <= lastFramesDecoded) {
                    videoStallCount++;
                    if (videoStallCount >= 3) {
                        setActive(false);
                        scheduleRetry();
                    }
                } else {
                    videoStallCount = 0;
                }
                lastFramesDecoded = currentFrames;
            } catch {
                // PC closed
            }
        }, 1000);
    }

    function stopWatchdog() {
        if (watchdogTimer) {
            clearInterval(watchdogTimer);
            watchdogTimer = null;
        }
        lastFramesDecoded = null;
        videoStallCount = 0;
    }

    function scheduleRetry() {
        if (retryTimer) return;
        retryTimer = setTimeout(() => {
            retryTimer = null;
            connect();
        }, RETRY_DELAY);
    }

    function setActive(active) {
        videoChecked.value = true;
        videoActive.value = active;
    }

    async function connect() {
        teardownPlayer();

        try {
            peerConnection = await startWhep();
            setActive(true);
            startWatchdog();

            peerConnection.addEventListener('connectionstatechange', () => {
                const s = peerConnection?.connectionState;
                if (s === 'failed' || s === 'disconnected' || s === 'closed') {
                    setActive(false);
                    scheduleRetry();
                }
            });
            return;
        } catch (e) {
            if (e.message.includes('404')) {
                setActive(false);
                scheduleRetry();
                return;
            }
        }

        try {
            await startHls();
            setActive(true);
        } catch {
            setActive(false);
            scheduleRetry();
        }
    }

    function cleanup() {
        if (retryTimer) { clearTimeout(retryTimer); retryTimer = null; }
        teardownPlayer();
    }

    onUnmounted(cleanup);

    return { videoActive, videoChecked, connect, cleanup };
}
```

- [ ] **Step 2: Verify the file has no syntax errors**

Run: `node -c resources/js/composables/useVideoFeed.js`
Expected: no output (success)

- [ ] **Step 3: Commit**

```bash
git add resources/js/composables/useVideoFeed.js
git commit -m "feat: add useVideoFeed composable for WHEP/HLS video with watchdog"
```

---

### Task 3: Create bare Inertia layout for overlay

**Files:**
- Create: `resources/views/overlay-app.blade.php`

This is the minimal Inertia shell for the overlay page -- no admin sidebar, no Tailwind, no Ziggy routes.

- [ ] **Step 1: Create the layout file**

```blade
{{-- resources/views/overlay-app.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9/dist/leaflet.js"></script>
    <script>
        window.scarletConfig = {
            reverb: {
                key: @json(config('broadcasting.connections.reverb.key')),
                host: @json(config('scarlet.reverb.host')),
                port: @json(config('scarlet.reverb.port')),
                scheme: @json(config('scarlet.reverb.scheme')),
            },
        };
    </script>
    @vite(['resources/js/app.js'])
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
```

- [ ] **Step 2: Commit**

```bash
git add resources/views/overlay-app.blade.php
git commit -m "feat: add bare Inertia layout for overlay page"
```

---

### Task 4: Update OverlayController to use Inertia

**Files:**
- Modify: `app/Http/Controllers/OverlayController.php`

Switch from `view()` to `Inertia::render()`. Add `MetricsService` injection. Set the bare layout.

- [ ] **Step 1: Rewrite the controller**

Replace the entire content of `app/Http/Controllers/OverlayController.php` with:

```php
<?php

namespace App\Http\Controllers;

use App\Models\BoatSetting;
use App\Services\MetricsService;
use Inertia\Inertia;

class OverlayController extends Controller
{
    public function index(MetricsService $metrics)
    {
        Inertia::setRootView('overlay-app');

        return Inertia::render('Public/Overlay', [
            'initialMetrics' => $metrics->getAllMetrics(),
            'boatName' => BoatSetting::getValue('boat_name', config('scarlet.name')),
            'passageFrom' => BoatSetting::getValue('passage_from', ''),
            'passageTo' => BoatSetting::getValue('passage_to', ''),
            'portName' => BoatSetting::getValue('port_name', ''),
            'utcOffset' => config('scarlet.time.offset'),
            'timeLabel' => config('scarlet.time.label'),
        ]);
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Http/Controllers/OverlayController.php
git commit -m "refactor: switch OverlayController from Blade to Inertia"
```

---

### Task 5: Create `Overlay.vue` page

**Files:**
- Create: `resources/js/Pages/Public/Overlay.vue`

The overlay as a Vue/Inertia page. Uses `useScarletMetrics` for all shared data and `useVideoFeed` for video. The state machine, viewport scaling, and all CSS from `overlay.css` live here.

- [ ] **Step 1: Create the overlay Vue component**

```vue
{{-- resources/js/Pages/Public/Overlay.vue --}}
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

<style scoped>
/* All styles from resources/css/overlay.css are placed here verbatim.
   Copy the full content of resources/css/overlay.css into this block.
   The [data-state] selectors work because the root div has :data-state bound. */
</style>

<style>
.boat-marker {
    background: transparent !important;
    border: none !important;
}
</style>
```

**Important:** The `<style scoped>` block above is a placeholder comment. The implementer MUST copy the entire content of `resources/css/overlay.css` (lines 1-604, everything after the `@import` on line 6) into the `<style scoped>` block. Do NOT include the `@import url(...)` line for Outfit -- the font is loaded in `overlay-app.blade.php`. The `:root` block and all rules from `* { margin: 0 }` through the `[data-state="loading"]` rules at the end must be included.

- [ ] **Step 2: Build to verify no errors**

Run: `npx vite build 2>&1 | tail -5`
Expected: `✓ built in` with no errors

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/Public/Overlay.vue
git commit -m "feat: create Overlay.vue page using shared composables"
```

---

### Task 6: Refactor `Dashboard.vue` to use `useScarletMetrics`

**Files:**
- Modify: `resources/js/Pages/Public/Dashboard.vue`

Replace all inline state management with the shared composable. Keep dashboard-specific template, styles, and features (interactive map controls, sailing instruments, recentre button).

- [ ] **Step 1: Rewrite the script block**

Replace the entire `<script setup>` block (lines 1-159) with:

```vue
<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useScarletMetrics } from '../../composables/useScarletMetrics';
import { formatVal } from '../../scarlet';

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
const autoCenter = ref(true);
let map = null;

const {
    boat, gps,
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
```

- [ ] **Step 2: Build to verify no errors**

Run: `npx vite build 2>&1 | tail -5`
Expected: `✓ built in` with no errors

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/Public/Dashboard.vue
git commit -m "refactor: Dashboard.vue now uses useScarletMetrics composable"
```

---

### Task 7: Remove old overlay files and update Vite config

**Files:**
- Delete: `resources/js/overlay.js`
- Delete: `resources/views/overlay.blade.php`
- Delete: `resources/css/overlay.css`
- Delete: `resources/js/composables/useEcho.js`
- Modify: `vite.config.js`

Clean up the old files and remove overlay entry points from Vite.

- [ ] **Step 1: Remove old files**

```bash
git rm resources/js/overlay.js
git rm resources/views/overlay.blade.php
git rm resources/css/overlay.css
git rm resources/js/composables/useEcho.js
```

- [ ] **Step 2: Update vite.config.js**

In `vite.config.js`, change the `input` array from:

```js
input: [
    'resources/css/app.css',
    'resources/js/app.js',
    'resources/css/overlay.css',
    'resources/js/overlay.js',
],
```

to:

```js
input: [
    'resources/css/app.css',
    'resources/js/app.js',
],
```

- [ ] **Step 3: Check for any remaining imports of deleted files**

Run: `grep -r "useEcho\|useMetrics" resources/js/ --include="*.vue" --include="*.js" -l`
Expected: no output (no remaining references). If any files reference `useEcho` or the old `useMetrics` from `useEcho.js`, update them to use `useScarletMetrics` instead.

- [ ] **Step 4: Build to verify everything still works**

Run: `npx vite build 2>&1 | tail -5`
Expected: `✓ built in` with no errors

- [ ] **Step 5: Commit**

```bash
git add vite.config.js
git commit -m "chore: remove old overlay files, clean up Vite entry points"
```

---

### Task 8: Verify complete system

**Files:**
- No file changes -- verification only

- [ ] **Step 1: Full build**

Run: `npx vite build 2>&1`
Expected: Clean build with no errors. Should see `app-*.js` and `app-*.css` bundles but no `overlay-*.js` or `overlay-*.css` bundles.

- [ ] **Step 2: Check no orphaned references**

Run: `grep -r "overlay\.js\|overlay\.css" resources/ app/ --include="*.php" --include="*.vue" --include="*.js" --include="*.blade.php" | grep -v "node_modules" | grep -v "Overlay.vue" | grep -v ".md"`
Expected: no output (no remaining references to the old overlay files)

- [ ] **Step 3: Verify route still works**

Run: `php artisan route:list --name=overlay`
Expected: Shows `GET /overlay` pointing to `OverlayController@index`

- [ ] **Step 4: Commit any fixes**

If any issues were found in steps 1-3, fix and commit. Otherwise skip this step.
