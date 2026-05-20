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
