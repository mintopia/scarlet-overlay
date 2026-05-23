// resources/js/composables/useScarletMetrics.js
import { ref, computed, unref, onUnmounted } from 'vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { speedToColor, makeBoatIcon, formatCoord, getWeatherIcon, getWeatherLabel, addRouteLayer } from '../scarlet';

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
    const gps = ref(initialMetrics?.gps ?? {});
    const weather = ref(initialMetrics?.weather ?? null);
    const lastUpdate = ref(initialMetrics ? new Date() : null);
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
        const voltage = boat.value?.house_battery_voltage;
        if (current != null && voltage != null && current > 0 && voltage > 13.2) return 'Under Power';
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
        const map = L.map(el, mapOpts).setView(initialPos, 16);
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

        if (!trackPoints.length && gpsTrack.length) {
            gpsTrack.forEach(p => trackPoints.push({ pos: [p[0], p[1]], speed: p[2] ?? 0 }));
        }

        if (trackPoints.length > 1) {
            for (let i = 1; i < trackPoints.length; i++) {
                const seg = L.polyline([trackPoints[i - 1].pos, trackPoints[i].pos], {
                    color: speedToColor(trackPoints[i].speed),
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
        if (newGps?.latitude == null || newGps?.longitude == null) return;
        if (Math.abs(newGps.latitude) < 0.1 && Math.abs(newGps.longitude) < 0.1) return;
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
            if (data.settings) {
                portName.value = data.settings.port_name ?? '';
                passageFrom.value = data.settings.passage_from ?? '';
                passageTo.value = data.settings.passage_to ?? '';
                boatName.value = data.settings.boat_name ?? boatName.value;
            }
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
        portName, passageFrom, passageTo, boatName,
        initMap, addMapTarget, removeMapTarget,
        cleanup,
    };
}
