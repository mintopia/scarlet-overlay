import { ref, computed, onMounted, onUnmounted } from 'vue';

const THEMES = ['light', 'dark', 'night'];
const LS_THEME = 'scarlet-theme';
const LS_AUTO = 'scarlet-theme-auto';
const LS_GPS = 'scarlet-gps-cache';
const DEFAULT_SUNRISE_HOUR = 6;
const DEFAULT_SUNSET_HOUR = 20;
const GPS_REFRESH_MS = 10 * 60 * 1000;
const AUTO_CHECK_MS = 60 * 1000;

const theme = ref('light');
const autoMode = ref(true);

let transitionTimer = null;

/**
 * Enable the cross-fade transition only for the ~250ms of an actual theme
 * switch, so it never applies to unrelated hover/focus/state changes.
 */
function flagThemeTransition() {
    const el = document.documentElement;
    el.classList.add('theme-transition');
    if (transitionTimer) { clearTimeout(transitionTimer); }
    transitionTimer = setTimeout(() => el.classList.remove('theme-transition'), 250);
}

function applyTheme(t, animate = false) {
    if (animate && theme.value !== t) { flagThemeTransition(); }
    theme.value = t;
    if (t === 'light') {
        document.documentElement.removeAttribute('data-theme');
    } else {
        document.documentElement.setAttribute('data-theme', t);
    }
    localStorage.setItem(LS_THEME, t);
}

function calculateSunTimes(lat, lng, date) {
    const rad = Math.PI / 180;
    const dayOfYear = Math.floor(
        (date - new Date(date.getFullYear(), 0, 0)) / 86400000
    );
    const declination = -23.45 * Math.cos(rad * (360 / 365) * (dayOfYear + 10));
    const latRad = lat * rad;
    const decRad = declination * rad;
    const cosHourAngle = -Math.tan(latRad) * Math.tan(decRad);

    if (cosHourAngle > 1) return { sunrise: DEFAULT_SUNRISE_HOUR, sunset: DEFAULT_SUNSET_HOUR };
    if (cosHourAngle < -1) return { sunrise: 0, sunset: 24 };

    const hourAngle = Math.acos(cosHourAngle) / rad;
    const solarNoonLST = 12 - lng / 15;
    const sunrise = solarNoonLST - hourAngle / 15;
    const sunset = solarNoonLST + hourAngle / 15;

    return { sunrise, sunset };
}

function isDark(lat, lng) {
    const now = new Date();
    const utcHour = now.getUTCHours() + now.getUTCMinutes() / 60;
    const { sunrise, sunset } = calculateSunTimes(lat, lng, now);
    return utcHour < sunrise || utcHour > sunset;
}

function isDarkFallback() {
    const hour = new Date().getHours();
    return hour < DEFAULT_SUNRISE_HOUR || hour >= DEFAULT_SUNSET_HOUR;
}

export { theme };

export function useTheme() {
    let autoInterval = null;
    let gpsInterval = null;
    let gpsPosition = null;

    const effectiveTheme = computed(() => theme.value);

    function setTheme(t) {
        if (!THEMES.includes(t)) return;
        autoMode.value = false;
        localStorage.setItem(LS_AUTO, 'false');
        applyTheme(t, true);
    }

    function enableAuto() {
        autoMode.value = true;
        localStorage.setItem(LS_AUTO, 'true');
        localStorage.removeItem(LS_THEME);
        checkAuto();
    }

    function checkAuto() {
        if (!autoMode.value) return;
        if (theme.value === 'night') return;

        let dark;
        if (gpsPosition) {
            dark = isDark(gpsPosition.latitude, gpsPosition.longitude);
        } else {
            dark = isDarkFallback();
        }
        applyTheme(dark ? 'dark' : 'light', true);
    }

    async function fetchGps() {
        try {
            const res = await fetch('/api/v1/gps');
            if (!res.ok) return;
            const json = await res.json();
            const data = json.data || json;
            if (data.latitude && data.longitude && data.valid) {
                gpsPosition = { latitude: data.latitude, longitude: data.longitude };
                localStorage.setItem(LS_GPS, JSON.stringify(gpsPosition));
            }
        } catch {
            // GPS unavailable — fall back to cached or default
        }
    }

    function init() {
        const saved = localStorage.getItem(LS_THEME);
        const savedAuto = localStorage.getItem(LS_AUTO);

        if (savedAuto === 'false') {
            autoMode.value = false;
        }

        const cached = localStorage.getItem(LS_GPS);
        if (cached) {
            try { gpsPosition = JSON.parse(cached); } catch { /* ignore */ }
        }

        if (saved && THEMES.includes(saved)) {
            applyTheme(saved);
        }

        if (autoMode.value && !saved) {
            checkAuto();
        }

        fetchGps();
        gpsInterval = setInterval(fetchGps, GPS_REFRESH_MS);
        autoInterval = setInterval(checkAuto, AUTO_CHECK_MS);
    }

    function cleanup() {
        if (autoInterval) clearInterval(autoInterval);
        if (gpsInterval) clearInterval(gpsInterval);
    }

    onMounted(init);
    onUnmounted(cleanup);

    return {
        theme,
        autoMode,
        effectiveTheme,
        setTheme,
        enableAuto,
    };
}
