import { ref, onUnmounted } from 'vue';

export function useVideoFeed(videoEl) {
    const videoActive = ref(false);
    const videoChecked = ref(false);

    const MAX_RETRIES = 20;
    let retryCount = 0;

    let hlsInstance = null;
    let retryTimer = null;
    let watchdogTimer = null;
    let lastPosition = null;
    let videoStallCount = 0;

    // live_web is the H264/AAC transcode of the H265 source, for browser compat.
    const HLS_URL = '/hls/live_web/index.m3u8';
    const RETRY_DELAY = 5000;

    function getVideoElement() {
        return videoEl.value;
    }

    async function startHls() {
        const video = getVideoElement();
        if (!video) throw new Error('No video element');

        if (video.canPlayType('application/vnd.apple.mpegurl')) {
            return startHlsNative(video);
        }

        const { default: Hls } = await import('hls.js');
        if (Hls.isSupported()) {
            return startHlsJs(video, Hls);
        }

        throw new Error('HLS not supported');
    }

    function startHlsNative(video) {
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

    function startHlsJs(video, Hls) {
        return new Promise((resolve, reject) => {
            // Deep buffering for the boat's unreliable uplink: sit several
            // segments behind the live edge and hold a generous forward buffer
            // so short dropouts don't stall playback. Trades latency (~6-10s)
            // for smoothness.
            const hls = new Hls({
                enableWorker: true,
                lowLatencyMode: false,
                backBufferLength: 30,
                liveSyncDurationCount: 4,
                liveMaxLatencyDurationCount: 12,
                maxBufferLength: 30,
                maxMaxBufferLength: 60,
            });

            const timeout = setTimeout(() => {
                hls.destroy();
                reject(new Error('HLS timeout'));
            }, 10000);

            hls.on(Hls.Events.ERROR, (_event, data) => {
                if (data.fatal) {
                    clearTimeout(timeout);
                    hls.destroy();
                    hlsInstance = null;
                    reject(new Error(`HLS fatal: ${data.type}`));
                }
            });

            hls.on(Hls.Events.MANIFEST_PARSED, () => {
                video.play().catch(() => {
                    clearTimeout(timeout);
                    hls.destroy();
                    hlsInstance = null;
                    reject(new Error('HLS play rejected'));
                });
            });

            video.addEventListener('playing', () => {
                clearTimeout(timeout);
                hlsInstance = hls;
                resolve();
            }, { once: true });

            hls.loadSource(HLS_URL);
            hls.attachMedia(video);
        });
    }

    function teardownPlayer() {
        stopWatchdog();

        if (hlsInstance) {
            hlsInstance.destroy();
            hlsInstance = null;
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
        lastPosition = null;
        videoStallCount = 0;

        // With a deep buffer a brief source dropout shouldn't stall playback,
        // so only treat sustained no-progress (playhead frozen) as a stall.
        watchdogTimer = setInterval(() => {
            if (!videoActive.value) return;

            const video = getVideoElement();
            if (video) {
                const pos = video.currentTime;
                if (lastPosition !== null && pos === lastPosition) {
                    videoStallCount++;
                    if (videoStallCount >= 5) {
                        setActive(false);
                        scheduleRetry();
                    }
                } else {
                    videoStallCount = 0;
                }
                lastPosition = pos;
            }
        }, 1000);
    }

    function stopWatchdog() {
        if (watchdogTimer) {
            clearInterval(watchdogTimer);
            watchdogTimer = null;
        }
        lastPosition = null;
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
        if (retryCount >= MAX_RETRIES) {
            console.warn('Video feed: max retries reached, stopping');
            setActive(false);
            return;
        }
        retryCount++;

        teardownPlayer();

        try {
            await startHls();
            retryCount = 0;
            setActive(true);
            startWatchdog();
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
