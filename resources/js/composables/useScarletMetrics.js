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
