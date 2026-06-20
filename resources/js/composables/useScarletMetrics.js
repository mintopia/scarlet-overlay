// resources/js/composables/useScarletMetrics.js
import { ref, computed, unref, watch, onUnmounted } from 'vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { speedToColor, makeBoatIcon, formatCoord, getWeatherIcon, getWeatherLabel, addRouteLayer } from '../scarlet';
import { theme } from './useTheme.js';
import { isTrackGap, trackSegments, buildInitialTrackPoints } from '../track.js';

export function useScarletMetrics(options = {}) {
    const {
        initialMetrics = null,
        portName: initialPortName = '',
        passageFrom: initialPassageFrom = '',
        passageTo: initialPassageTo = '',
        boatName: initialBoatName = '',
        gpsTrack = [],
        routeWaypoints = [],
    } = options;

    // ── Reactive state ──────────────────────────────────────────────────
    const boat = ref(initialMetrics?.boat ?? {});
    const canonical = ref(initialMetrics?.canonical ?? {});
    const gps = ref(initialMetrics?.gps ?? {});
    const weather = ref(initialMetrics?.weather ?? null);
    const sun = ref(options.initialSun ?? null);
    const lastUpdate = ref(initialMetrics ? new Date() : null);
    const staleKeys = ref(new Set());
    const clock = ref('--:--');
    const clockDate = ref('');

    // ── Settings (auto-updated via WebSocket) ───────────────────────────
    const portName = ref(initialMetrics?.settings?.port_name ?? initialPortName);
    const passageFrom = ref(initialMetrics?.settings?.passage_from ?? initialPassageFrom);
    const passageTo = ref(initialMetrics?.settings?.passage_to ?? initialPassageTo);
    const boatName = ref(initialMetrics?.settings?.boat_name ?? initialBoatName);

    // ── Computed ─────────────────────────────────────────────────────────
    const coordText = computed(() => formatCoord(gps.value?.latitude, gps.value?.longitude));

    const isOffline = computed(() => {
        if (!lastUpdate.value) return false;
        return Date.now() - lastUpdate.value.getTime() > 2 * 60 * 60 * 1000;
    });

    const statusText = computed(() => {
        if (isOffline.value) return 'Offline';
        const sog = boat.value?.speed_sog;
        if ((sog == null || sog < 0.5) && portName.value) return 'In Port';
        const current = boat.value?.house_battery_current;
        if (current != null && current > 0) return 'Under Power';
        return 'Under Sail';
    });

    const statusClass = computed(() => {
        const map = {
            'Offline': 'status-offline',
            'In Port': 'status-port',
            'Under Sail': 'status-sail',
            'Under Power': 'status-power',
        };
        return map[statusText.value] ?? 'status-sail';
    });

    const lastUpdateText = computed(() => {
        if (!lastUpdate.value) return '';
        const tz = weather.value?.timezone || 'UTC';
        return new Intl.DateTimeFormat('en-GB', { hour: '2-digit', minute: '2-digit', timeZone: tz }).format(lastUpdate.value);
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
        const tz = weather.value?.timezone || 'UTC';
        const now = new Date();
        const timeFmt = new Intl.DateTimeFormat('en-GB', { hour: '2-digit', minute: '2-digit', timeZone: tz });
        const dayFmt = new Intl.DateTimeFormat('en-GB', { day: 'numeric', month: 'short', timeZone: tz });
        const tzShort = new Intl.DateTimeFormat('en-GB', { timeZoneName: 'short', timeZone: tz });
        const tzLabel = tzShort.formatToParts(now).find(p => p.type === 'timeZoneName')?.value ?? '';
        clock.value = timeFmt.format(now);
        clockDate.value = `${dayFmt.format(now)} · ${tzLabel}`;
    }

    updateClock();
    const clockInterval = setInterval(updateClock, 1000);

    // ── Map management ───────────────────────────────────────────────────
    const mapTargets = [];
    const trackPoints = [];

    function tileUrlForTheme(t) {
        return t === 'dark' || t === 'night'
            ? '/openseamap-dark/{z}/{x}/{y}'
            : '/openseamap/{z}/{x}/{y}';
    }

    function initMap(el, opts = {}) {
        const interactive = opts.interactive !== false;
        const tiles = opts.tiles !== false;
        const mapOpts = {
            zoomControl: interactive,
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
        const map = L.map(el, mapOpts).setView(initialPos, 16);

        if (tiles) {
            let tileLayer = L.tileLayer(tileUrlForTheme(theme.value), { maxZoom: 18 }).addTo(map);

            watch(theme, (t) => {
                map.removeLayer(tileLayer);
                tileLayer = L.tileLayer(tileUrlForTheme(t), { maxZoom: 18 }).addTo(map);
            });
        }

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

        if (!trackPoints.length && (gpsTrack.length || gps.value?.latitude != null)) {
            // Seed the shared track once, bridging the historical track to the live
            // marker so the drawn line meets the boat instead of stopping short.
            const cur = gps.value?.latitude != null ? [gps.value.latitude, gps.value.longitude] : null;
            const curSpeed = boat.value?.speed_sog ?? canonical.value?.speed_sog?.value ?? 0;
            buildInitialTrackPoints(gpsTrack, cur, curSpeed).forEach(p => trackPoints.push(p));
        }

        if (trackPoints.length > 1) {
            // trackSegments splits the track at telemetry gaps so we never bridge
            // two distant fixes with a straight line.
            for (const s of trackSegments(trackPoints)) {
                const seg = L.polyline([s.from, s.to], {
                    color: speedToColor(s.speed),
                    weight: 3,
                    opacity: 0.85,
                }).addTo(target.map);
                target.segments.push(seg);
            }
        } else if (gps.value?.latitude && !trackPoints.length) {
            trackPoints.push({ pos: [gps.value.latitude, gps.value.longitude], speed: boat.value?.speed_sog ?? 0 });
        }

        if (routeWaypoints.length) {
            addRouteLayer(target.map, routeWaypoints);
        }

        if (gps.value?.latitude) {
            const pos = [gps.value.latitude, gps.value.longitude];
            target.marker = L.marker(pos, { icon: makeBoatIcon(resolveHeading()) }).addTo(target.map);
        }
    }

    function removeMapTarget(map) {
        const idx = mapTargets.findIndex(t => t.map === map);
        if (idx !== -1) mapTargets.splice(idx, 1);
    }

    // Resolve the boat marker's heading. Dashboards built on the canonical read
    // contract (Main/Skipper/Ops) pass only `canonical`, with no `boat` object, so
    // boat.cog/heading are absent and the arrow would lock to 0° (straight up).
    // Fall back to the canonical `cog`/`heading_true` contracts in that case.
    function resolveHeading(newBoat = null) {
        const b = newBoat ?? boat.value;
        const c = canonical.value ?? {};

        return b?.cog
            ?? b?.heading
            ?? c.cog?.value
            ?? c.heading_true?.value
            ?? 0;
    }

    function updateSingleMap(target, newGps, newBoat) {
        const pos = [newGps.latitude, newGps.longitude];
        const icon = makeBoatIcon(resolveHeading(newBoat));

        if (target.marker) {
            target.marker.setLatLng(pos).setIcon(icon);
        } else {
            target.marker = L.marker(pos, { icon }).addTo(target.map);
        }

        if (trackPoints.length > 1) {
            const prev = trackPoints[trackPoints.length - 2];
            const curr = trackPoints[trackPoints.length - 1];
            if (!isTrackGap(prev.pos, pos)) {
                const seg = L.polyline([prev.pos, pos], {
                    color: speedToColor(curr.speed),
                    weight: 3,
                    opacity: 0.85,
                }).addTo(target.map);
                target.segments.push(seg);
            }
        }

        const ac = unref(target.autoCenter);
        if (ac) target.map.setView(pos);
    }

    function updateAllMaps(newGps, newBoat) {
        if (newGps?.latitude == null || newGps?.longitude == null) return;
        if (Math.abs(newGps.latitude) < 0.1 && Math.abs(newGps.longitude) < 0.1) return;
        const speed = newBoat?.speed_sog ?? 0;
        const newPos = [newGps.latitude, newGps.longitude];

        if (trackPoints.length > 0) {
            const last = trackPoints[trackPoints.length - 1].pos;
            if (isTrackGap(last, newPos)) {
                // Genuine telemetry gap: drop the stale in-memory track AND the
                // already-drawn polylines so the track restarts at the boat
                // rather than leaving an orphaned line floating on the map.
                trackPoints.length = 0;
                mapTargets.forEach(t => {
                    t.segments.forEach(seg => t.map.removeLayer(seg));
                    t.segments = [];
                });
            }
        }

        trackPoints.push({ pos: newPos, speed });
        mapTargets.forEach(t => updateSingleMap(t, newGps, newBoat));
    }

    // ── Live ingest ──────────────────────────────────────────────────────
    // Shared by the WebSocket event and the HTTP fallback poll so both paths
    // update state and the map identically.
    function ingest(data) {
        if (!data) return;
        const newStale = new Set();
        const merged = { ...boat.value };
        for (const [k, v] of Object.entries(data.boat ?? {})) {
            if (v != null) {
                merged[k] = v;
            } else if (merged[k] != null) {
                newStale.add(k);
            }
        }
        boat.value = merged;
        if (data.canonical) canonical.value = data.canonical;
        staleKeys.value = newStale;
        if (data.gps) gps.value = data.gps;
        if (data.weather) weather.value = data.weather;
        if (data.sun) sun.value = data.sun;
        if (data.settings) {
            portName.value = data.settings.port_name ?? '';
            passageFrom.value = data.settings.passage_from ?? '';
            passageTo.value = data.settings.passage_to ?? '';
            boatName.value = data.settings.boat_name ?? boatName.value;
        }
        lastUpdate.value = new Date();
        if (data.gps) updateAllMaps(data.gps, data.boat);
    }

    // ── HTTP fallback ────────────────────────────────────────────────────
    // The WebSocket is the primary transport, but it can silently die (proxy
    // idle timeout, Reverb restart, a backgrounded/throttled OBS source) and
    // the push loop can stall server-side. Without a fallback the UI freezes
    // until a hard refresh. Poll the same payload the broadcast carries.
    const pushInterval = (window.scarletConfig?.metrics?.pushInterval ?? 15) * 1000;
    const staleAfterMs = options.staleAfterMs ?? pushInterval * 2;
    const wsConnected = ref(false);
    let snapshotInFlight = false;

    async function refreshSnapshot() {
        if (snapshotInFlight || typeof window === 'undefined' || !window.axios) return;
        snapshotInFlight = true;
        try {
            const { data } = await window.axios.get('/api/v1/metrics');
            ingest(data);
        } catch {
            // Leave existing state; the watchdog will retry on the next tick.
        } finally {
            snapshotInFlight = false;
        }
    }

    function isStale() {
        if (!lastUpdate.value) return true;
        return Date.now() - lastUpdate.value.getTime() > staleAfterMs;
    }

    // ── WebSocket ────────────────────────────────────────────────────────
    let echoChannel = null;
    if (window.Echo) {
        echoChannel = window.Echo.channel('metrics');
        echoChannel.listen('.metrics.updated', ingest);
        echoChannel.listen('.force-reload', () => {
            window.location.reload();
        });

        // Recover the data missed while the socket was down. pusher-js
        // reconnects and re-subscribes the channel on its own, but the app must
        // back-fill the gap — re-entering 'connected' triggers a fresh snapshot.
        try {
            const conn = window.Echo.connector?.pusher?.connection;
            conn?.bind('state_change', ({ current }) => {
                const wasConnected = wsConnected.value;
                wsConnected.value = current === 'connected';
                if (current === 'connected' && !wasConnected) refreshSnapshot();
            });
        } catch {
            // Connector internals vary by driver; the watchdog still covers us.
        }
    }

    // Watchdog: poll whenever updates have gone quiet — covers both a dead
    // socket and a stalled server-side push loop (which the socket can't detect).
    const watchdog = setInterval(() => {
        if (isStale()) refreshSnapshot();
    }, pushInterval);

    // Recover immediately when the tab is shown again or the network returns,
    // rather than waiting for the next watchdog tick.
    function onVisible() {
        if (typeof document !== 'undefined' && document.visibilityState === 'visible' && isStale()) {
            refreshSnapshot();
        }
    }
    if (typeof document !== 'undefined') {
        document.addEventListener('visibilitychange', onVisible);
    }
    if (typeof window !== 'undefined') {
        window.addEventListener('online', refreshSnapshot);
    }

    // ── Cleanup ──────────────────────────────────────────────────────────
    function cleanup() {
        clearInterval(clockInterval);
        clearInterval(watchdog);
        if (typeof document !== 'undefined') {
            document.removeEventListener('visibilitychange', onVisible);
        }
        if (typeof window !== 'undefined') {
            window.removeEventListener('online', refreshSnapshot);
        }
        if (echoChannel) {
            window.Echo.leave('metrics');
            echoChannel = null;
        }
    }

    onUnmounted(cleanup);

    return {
        boat, gps, weather, sun, lastUpdate, staleKeys, canonical, wsConnected,
        clock, clockDate,
        coordText, isOffline, statusText, statusClass, lastUpdateText,
        wxTemp, wxCondition, wxIcon, wxSeaTemp, wxWindSpeed, wxWindDir, wxWaveHeight, wxWavePeriod,
        portName, passageFrom, passageTo, boatName,
        initMap, addMapTarget, removeMapTarget,
        cleanup,
    };
}
