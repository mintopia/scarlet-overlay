import './bootstrap';

/* ════════════════════════════════════════════════════════════════════════
   Scarlet Overlay — State Machine, WebSocket, Leaflet Map
   States: loading → video-live | no-video | offline | port
   ════════════════════════════════════════════════════════════════════════ */

const Overlay = {
    // ── State ───────────────────────────────────────────────────────────
    state: 'loading',
    lastMetricsTime: null,
    lastMetricsData: null,
    clockTimer: null,
    weatherTimer: null,
    elements: {},

    // ── Maps ────────────────────────────────────────────────────────────
    maps: { pip: null, full: null },
    trackPoints: [],
    boatMarkerPip: null,
    boatMarkerFull: null,
    trackLine: [],

    // ── Element cache ───────────────────────────────────────────────────
    el(id) {
        if (!this.elements[id]) {
            this.elements[id] = document.getElementById(id);
        }
        return this.elements[id];
    },

    // ── State machine ───────────────────────────────────────────────────
    setState(newState) {
        if (this.state === newState) return;
        this.state = newState;
        document.getElementById('overlay').dataset.state = newState;
    },

    determineState(data) {
        if (!data || !this.lastMetricsTime) return 'loading';

        const ageMs = Date.now() - this.lastMetricsTime;
        const twoHours = 2 * 60 * 60 * 1000;

        if (ageMs > twoHours) return 'offline';

        const speed = data.boat?.speed_sog;
        const portName = window.scarletConfig?.portName;

        // Port: no speed (null or 0) AND a port name is configured
        if ((speed === null || speed === undefined || speed === 0) && portName) {
            return 'port';
        }

        // Video live / no-video distinguished by videoFeedActive flag
        if (window.scarletConfig?.videoFeedActive) return 'video-live';

        return 'no-video';
    },

    // ── WebSocket ───────────────────────────────────────────────────────
    initWebSocket() {
        if (!window.Echo) {
            console.warn('[Overlay] Echo not available — WebSocket disabled');
            return;
        }
        window.Echo.channel('metrics').listen('.metrics.updated', (data) => {
            this.onMetrics(data);
        });
        console.log('[Overlay] WebSocket listening on metrics channel');
    },

    onMetrics(data) {
        this.lastMetricsTime = Date.now();
        this.lastMetricsData = data;
        const newState = this.determineState(data);
        this.setState(newState);
        this.updateMetricsDOM(data, newState);
        this.updateLiveBadge(newState);
        if (data.gps) {
            this.updateMap(data.gps, data.boat);
        }
    },

    // ── Live badge ──────────────────────────────────────────────────────
    updateLiveBadge(state) {
        const extEl = this.el('live-extension');
        const dotEl = document.querySelector('.live-dot');
        const textEl = document.querySelector('.live-text');

        if (state === 'offline') {
            if (textEl) textEl.textContent = 'OFFLINE';
            if (extEl)  extEl.textContent = 'Telemetry Unavailable';
            if (dotEl)  dotEl.style.background = 'oklch(0.65 0.06 55)';
        } else if (state === 'no-video') {
            if (textEl) textEl.textContent = 'LIVE';
            if (extEl)  extEl.textContent = 'Video Offline';
            if (dotEl)  dotEl.style.background = '';
        } else if (state === 'port') {
            const now = new Date();
            const hh = String(now.getHours()).padStart(2, '0');
            const mm = String(now.getMinutes()).padStart(2, '0');
            if (textEl) textEl.textContent = 'LIVE';
            if (extEl)  extEl.textContent = `Updated ${hh}:${mm}`;
            if (dotEl)  dotEl.style.background = '';
        } else {
            // video-live: just "LIVE", no extension
            if (textEl) textEl.textContent = 'LIVE';
            if (extEl)  extEl.textContent = '';
            if (dotEl)  dotEl.style.background = '';
        }
    },

    // ── Metrics DOM ─────────────────────────────────────────────────────
    updateMetricsDOM(data, state) {
        const boat = data.boat || {};
        const gps  = data.gps  || {};

        if (state === 'offline') {
            // Show dashes — do not display stale numbers
            const vals = ['metric-speed', 'metric-heading', 'metric-depth'];
            vals.forEach(id => {
                const el = this.el(id)?.querySelector('.lt-metric-value');
                if (el) el.textContent = '—';
            });
        } else {
            // Speed
            const speedEl = this.el('metric-speed')?.querySelector('.lt-metric-value');
            if (speedEl) {
                const spd = boat.speed_sog;
                speedEl.textContent = spd != null ? spd.toFixed(1) + ' kn' : '--';
            }
            // Heading
            const hdgEl = this.el('metric-heading')?.querySelector('.lt-metric-value');
            if (hdgEl) {
                const hdg = boat.heading;
                hdgEl.textContent = hdg != null ? Math.round(hdg) + '°' : '--';
            }
            // Depth
            const dptEl = this.el('metric-depth')?.querySelector('.lt-metric-value');
            if (dptEl) {
                const dpt = boat.depth;
                dptEl.textContent = dpt != null ? dpt.toFixed(1) + ' m' : '--';
            }
        }

        // Status pill
        const statusPill = this.el('status-pill');
        if (statusPill) {
            if (state === 'port') {
                statusPill.textContent = 'In Port';
                statusPill.className = 'lt-status lt-status--port';
            } else if (state === 'offline') {
                statusPill.textContent = 'Offline';
                statusPill.className = 'lt-status lt-status--offline';
            } else if (boat.engine_rpm && boat.engine_rpm > 0) {
                statusPill.textContent = 'Under Power';
                statusPill.className = 'lt-status lt-status--power';
            } else {
                statusPill.textContent = 'Under Sail';
                statusPill.className = 'lt-status lt-status--sail';
            }
        }

        // Coordinate badge
        const coordEl = this.el('coord-badge');
        if (coordEl && gps.latitude != null && gps.longitude != null) {
            const lat = gps.latitude.toFixed(4);
            const lon = Math.abs(gps.longitude).toFixed(4);
            const lonDir = gps.longitude < 0 ? 'W' : 'E';
            coordEl.innerHTML = `${lat}&deg;N &ensp; ${lon}&deg;${lonDir}`;
        }

        // Offline card last update timestamp
        if (state === 'offline' && this.lastMetricsTime) {
            const lastEl = this.el('offline-last-update');
            if (lastEl) {
                const ago = new Date(this.lastMetricsTime);
                const hh  = String(ago.getHours()).padStart(2, '0');
                const mm  = String(ago.getMinutes()).padStart(2, '0');
                const dd  = ago.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
                lastEl.textContent = `Last update received ${hh}:${mm} · ${dd}`;
            }
        }
    },

    // ── Clock ───────────────────────────────────────────────────────────
    updateClock() {
        const now    = new Date();
        const offset = window.scarletConfig?.utcOffset ?? 0;
        const label  = window.scarletConfig?.timeLabel ?? 'UTC';

        const utcMs  = now.getTime() + now.getTimezoneOffset() * 60000;
        const local  = new Date(utcMs + offset * 3600000);

        const hh  = String(local.getHours()).padStart(2, '0');
        const mm  = String(local.getMinutes()).padStart(2, '0');
        const day = local.getDate();
        const mon = local.toLocaleDateString('en-GB', { month: 'short' });

        const timeEl = this.el('clock-time');
        const dateEl = this.el('clock-date');
        if (timeEl) timeEl.textContent = `${hh}:${mm}`;
        if (dateEl) dateEl.textContent = `${day} ${mon} · ${label}`;
    },

    // ── Weather ─────────────────────────────────────────────────────────
    async fetchWeather() {
        try {
            const response = await fetch('/api/v1/weather');
            if (!response.ok) return;
            const data = await response.json();
            this.updateWeatherDOM(data);
        } catch (e) {
            console.warn('[Overlay] Weather fetch failed:', e);
        }
    },

    updateWeatherDOM(data) {
        const airEl  = this.el('wx-air-val');
        const seaEl  = this.el('wx-sea-val');
        const windEl = this.el('wx-wind-val');
        const windDir = this.el('wx-wind-dir');
        const wavesEl = this.el('wx-waves-val');
        const wavesPer = this.el('wx-waves-period');

        if (airEl)   airEl.innerHTML  = `${data.temp}&deg;`;
        if (seaEl)   seaEl.innerHTML  = `${data.seaTemp}&deg;`;
        if (windEl)  windEl.textContent = `${data.wind?.speed ?? '--'} kn`;
        if (windDir) windDir.textContent = data.wind?.direction ?? '';
        if (wavesEl) wavesEl.textContent = `${data.waves?.height ?? '--'} m`;
        if (wavesPer) wavesPer.textContent = data.waves?.period ? `${data.waves.period}s` : '';
    },

    // ── Leaflet maps ────────────────────────────────────────────────────
    initMaps() {
        const tileUrl = window.scarletConfig?.tileUrl || '/openseamap/{z}/{x}/{y}';
        const defaultView = [
            window.scarletConfig?.home?.lat ?? 51.534,
            window.scarletConfig?.home?.lon ?? -0.138,
        ];
        const zoom = 14;

        // PiP map (top-left, video-live state)
        this.maps.pip = L.map('map-pip', {
            zoomControl: false,
            attributionControl: false,
            dragging: false,
            scrollWheelZoom: false,
            touchZoom: false,
            doubleClickZoom: false,
            keyboard: false,
        }).setView(defaultView, zoom);

        // Full-screen map (no-video / offline / port states)
        this.maps.full = L.map('map-full', {
            zoomControl: false,
            attributionControl: false,
        }).setView(defaultView, zoom);

        // Tile layers on both maps
        [this.maps.pip, this.maps.full].forEach(map => {
            L.tileLayer(tileUrl, { maxZoom: 18 }).addTo(map);
        });
    },

    // ── Boat icon helper ────────────────────────────────────────────────
    makeBoatIcon(heading) {
        const deg = heading ?? 0;
        return L.divIcon({
            className: 'boat-marker',
            html: `<svg width="24" height="24" viewBox="0 0 24 24" style="transform:rotate(${deg}deg);overflow:visible">
                <polygon points="12,2 20,20 12,16 4,20"
                    fill="oklch(0.54 0.22 27)"
                    stroke="oklch(0.96 0.005 70)"
                    stroke-width="1.5"
                    stroke-linejoin="round"/>
            </svg>`,
            iconSize: [24, 24],
            iconAnchor: [12, 12],
        });
    },

    // ── Update map position + track ──────────────────────────────────────
    updateMap(gps, boat) {
        if (gps == null || gps.latitude == null || gps.longitude == null) return;

        const pos     = [gps.latitude, gps.longitude];
        const speed   = boat?.speed_sog ?? 0;
        const heading = boat?.heading ?? 0;

        this.trackPoints.push({ pos, speed });

        const boatIcon = this.makeBoatIcon(
            this.state === 'port' ? 0 : heading
        );

        if (!this.boatMarkerPip) {
            // First fix — place markers
            this.boatMarkerPip  = L.marker(pos, { icon: boatIcon }).addTo(this.maps.pip);
            this.boatMarkerFull = L.marker(pos, { icon: boatIcon }).addTo(this.maps.full);
        } else {
            this.boatMarkerPip.setLatLng(pos).setIcon(boatIcon);
            this.boatMarkerFull.setLatLng(pos).setIcon(boatIcon);
        }

        this.maps.pip.setView(pos);

        // Only pan full map if it's actually visible
        if (this.state !== 'video-live') {
            this.maps.full.setView(pos);
        }

        this.updateTrack();
    },

    // ── Speed → colour interpolation ────────────────────────────────────
    speedToColor(speed) {
        const ratio = Math.min(Math.max(speed, 0) / 10, 1);
        if (ratio <= 0.5) {
            const t = ratio * 2; // 0→1 for 0–5 kn
            // blue (260°) → green (155°)
            const l = 0.52 + t * 0.10;
            const c = 0.10 + t * 0.04;
            const h = 260 - t * 105;
            return `oklch(${l} ${c} ${h})`;
        }
        const t = (ratio - 0.5) * 2; // 0→1 for 5–10 kn
        // green (155°) → scarlet (27°)
        const l = 0.62 - t * 0.06;
        const c = 0.14 + t * 0.06;
        const h = 155 - t * 128;
        return `oklch(${l} ${c} ${h})`;
    },

    // ── Redraw speed-coloured track ──────────────────────────────────────
    updateTrack() {
        // Remove old segments
        this.trackLine.forEach(seg => seg.remove());
        this.trackLine = [];

        for (let i = 1; i < this.trackPoints.length; i++) {
            const prev = this.trackPoints[i - 1];
            const curr = this.trackPoints[i];
            const color = this.speedToColor(curr.speed);

            const opts = { color, weight: 3, opacity: 0.85 };
            const segPip  = L.polyline([prev.pos, curr.pos], opts);
            const segFull = L.polyline([prev.pos, curr.pos], opts);

            segPip.addTo(this.maps.pip);
            segFull.addTo(this.maps.full);

            this.trackLine.push(segPip, segFull);
        }
    },

    // ── Init ─────────────────────────────────────────────────────────────
    init() {
        this.clockTimer = setInterval(() => this.updateClock(), 1000);
        this.updateClock();

        this.fetchWeather();
        this.weatherTimer = setInterval(() => this.fetchWeather(), 5 * 60 * 1000);

        this.initMaps();
        this.initWebSocket();
    },
};

document.addEventListener('DOMContentLoaded', () => Overlay.init());
