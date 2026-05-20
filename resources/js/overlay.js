import './bootstrap';
import { speedToColor, makeBoatIcon, formatCoord, getWeatherIcon, getWeatherLabel } from './scarlet';

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
    elements: {},

    // ── Video / WHEP ────────────────────────────────────────────────────
    videoChecked: false,
    videoFeedActive: false,
    peerConnection: null,
    whepRetryTimer: null,
    whepRetryDelay: 5000,
    whepUrl: '/rtc/live/whep',
    hlsUrl: '/hls/live/index.m3u8',
    videoWatchdog: null,
    lastFramesDecoded: null,
    videoStallCount: 0,

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
        setTimeout(() => {
            if (this.maps.pip)  this.maps.pip.invalidateSize();
            if (this.maps.full) this.maps.full.invalidateSize();
        }, 50);
    },

    determineState(data) {
        if (!data || !this.lastMetricsTime) {
            if (this.videoFeedActive) return 'video-live';
            if (this.videoChecked) return 'no-video';
            return 'loading';
        }

        const ageMs = Date.now() - this.lastMetricsTime;
        const twoHours = 2 * 60 * 60 * 1000;

        if (ageMs > twoHours) return 'offline';

        const speed = data.boat?.speed_sog;
        const portName = window.scarletConfig?.portName;

        if ((speed == null || speed < 0.5) && portName) {
            return 'port';
        }

        if (this.videoFeedActive) return 'video-live';

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
        if (data.weather) {
            this.updateWeatherDOM(data.weather);
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
            coordEl.textContent = formatCoord(gps.latitude, gps.longitude);
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
    updateWeatherDOM(data) {
        const airEl   = this.el('wx-air-val');
        const seaEl   = this.el('wx-sea-val');
        const windEl  = this.el('wx-wind-val');
        const windDir = this.el('wx-wind-dir');
        const wavesEl = this.el('wx-waves-val');
        const wavesPer = this.el('wx-waves-period');
        const iconEl  = this.el('wx-icon');
        const condEl  = this.el('wx-condition');

        if (airEl)    airEl.innerHTML  = `${data.temp}&deg;`;
        if (seaEl)    seaEl.innerHTML  = `${data.seaTemp}&deg;`;
        if (windEl)   windEl.textContent = `${data.wind?.speed ?? '--'} kn`;
        if (windDir)  windDir.textContent = data.wind?.direction ?? '';
        if (wavesEl)  wavesEl.textContent = `${data.waves?.height ?? '--'} m`;
        if (wavesPer) wavesPer.textContent = data.waves?.period ? `${data.waves.period}s` : '';

        if (iconEl) iconEl.textContent = getWeatherIcon(data.summary);
        if (condEl) condEl.textContent = getWeatherLabel(data.summary);
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

    // ── Update map position + track ──────────────────────────────────────
    updateMap(gps, boat) {
        if (gps == null || gps.latitude == null || gps.longitude == null) return;

        const pos     = [gps.latitude, gps.longitude];
        const speed   = boat?.speed_sog ?? 0;
        const heading = boat?.heading ?? 0;

        this.trackPoints.push({ pos, speed });

        const boatIcon = makeBoatIcon(this.state === 'port' ? 0 : heading);

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

    // ── Redraw speed-coloured track ──────────────────────────────────────
    updateTrack() {
        // Remove old segments
        this.trackLine.forEach(seg => seg.remove());
        this.trackLine = [];

        for (let i = 1; i < this.trackPoints.length; i++) {
            const prev = this.trackPoints[i - 1];
            const curr = this.trackPoints[i];
            const color = speedToColor(curr.speed);

            const opts = { color, weight: 3, opacity: 0.85 };
            const segPip  = L.polyline([prev.pos, curr.pos], opts);
            const segFull = L.polyline([prev.pos, curr.pos], opts);

            segPip.addTo(this.maps.pip);
            segFull.addTo(this.maps.full);

            this.trackLine.push(segPip, segFull);
        }
    },

    // ── Video feed (WHEP + HLS fallback) ──────────────────────────────────
    initVideo() {
        this.connectVideo();
    },

    async connectVideo() {
        this.teardownPlayer();

        try {
            this.peerConnection = await this.startWhep();
            this.setVideoFeedActive(true);
            this.startVideoWatchdog();

            this.peerConnection.addEventListener('connectionstatechange', () => {
                const s = this.peerConnection?.connectionState;
                if (s === 'failed' || s === 'disconnected' || s === 'closed') {
                    console.warn('[Overlay] WebRTC disconnected:', s);
                    this.setVideoFeedActive(false);
                    this.scheduleRetry();
                }
            });
            return;
        } catch (e) {
            console.warn('[Overlay] WHEP failed:', e.message);
            if (e.message.includes('404')) {
                this.setVideoFeedActive(false);
                this.scheduleRetry();
                return;
            }
        }

        try {
            await this.startHls();
            this.setVideoFeedActive(true);
        } catch (e) {
            console.warn('[Overlay] HLS failed:', e.message);
            this.setVideoFeedActive(false);
            this.scheduleRetry();
        }
    },

    async startWhep() {
        const pc = new RTCPeerConnection();
        let timeoutId;

        try {
            pc.addTransceiver('video', { direction: 'recvonly' });
            pc.addTransceiver('audio', { direction: 'recvonly' });

            const video = this.el('video-feed');
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

            const res = await fetch(this.whepUrl, {
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
    },

    startHls() {
        const video = this.el('video-feed');
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
            video.src = this.hlsUrl;
            video.play().catch(() => reject(new Error('HLS play rejected')));
        });
    },

    teardownPlayer() {
        this.stopVideoWatchdog();

        if (this.peerConnection) {
            try { this.peerConnection.close(); } catch {}
            this.peerConnection = null;
        }

        const video = this.el('video-feed');
        if (video) {
            video.srcObject = null;
            video.removeAttribute('src');
            video.load();
        }
    },

    startVideoWatchdog() {
        this.stopVideoWatchdog();
        this.lastFramesDecoded = null;
        this.videoStallCount = 0;

        this.videoWatchdog = setInterval(async () => {
            if (!this.videoFeedActive || !this.peerConnection) return;

            try {
                const stats = await this.peerConnection.getStats();
                let currentFrames = 0;
                stats.forEach(report => {
                    if (report.type === 'inbound-rtp' && report.kind === 'video') {
                        currentFrames = report.framesDecoded || 0;
                    }
                });

                if (this.lastFramesDecoded !== null && currentFrames <= this.lastFramesDecoded) {
                    this.videoStallCount++;
                    if (this.videoStallCount >= 3) {
                        console.warn('[Overlay] Video stalled — no new frames for 3s');
                        this.setVideoFeedActive(false);
                        this.scheduleRetry();
                    }
                } else {
                    this.videoStallCount = 0;
                }
                this.lastFramesDecoded = currentFrames;
            } catch {
                // PC closed
            }
        }, 1000);
    },

    stopVideoWatchdog() {
        if (this.videoWatchdog) {
            clearInterval(this.videoWatchdog);
            this.videoWatchdog = null;
        }
        this.lastFramesDecoded = null;
        this.videoStallCount = 0;
    },

    scheduleRetry() {
        if (this.whepRetryTimer) return;

        this.whepRetryTimer = setTimeout(() => {
            this.whepRetryTimer = null;
            this.connectVideo();
        }, this.whepRetryDelay);
    },

    setVideoFeedActive(active) {
        if (this.videoFeedActive === active && this.videoChecked) return;

        this.videoChecked = true;
        this.videoFeedActive = active;
        console.log('[Overlay] Video feed:', active ? 'active' : 'inactive');

        const newState = this.determineState(this.lastMetricsData);
        this.setState(newState);
        this.updateLiveBadge(newState);
    },

    // ── Viewport scaling ────────────────────────────────────────────────
    scaleToViewport() {
        const overlay = document.getElementById('overlay');
        if (!overlay) return;
        const scaleX = window.innerWidth / 1920;
        const scaleY = window.innerHeight / 1080;
        const scale = Math.min(scaleX, scaleY);
        overlay.style.transform = `scale(${scale})`;
        setTimeout(() => {
            if (this.maps?.pip)  this.maps.pip.invalidateSize();
            if (this.maps?.full) this.maps.full.invalidateSize();
        }, 100);
    },

    // ── Init ─────────────────────────────────────────────────────────────
    init() {
        this.scaleToViewport();
        window.addEventListener('resize', () => this.scaleToViewport());

        this.clockTimer = setInterval(() => this.updateClock(), 1000);
        this.updateClock();

        this.initMaps();
        this.initWebSocket();
        this.initVideo();
    },
};

document.addEventListener('DOMContentLoaded', () => Overlay.init());
