# Admin Dark Mode & Night Watch Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add Dark and Night Watch theme modes to admin pages, with automatic sunset/sunrise switching and a three-way toggle button.

**Architecture:** CSS custom property overrides via `data-theme` attribute on `<html>`. A `useTheme` composable manages state, localStorage persistence, and solar calculations. A toggle button in AdminLayout provides manual control.

**Tech Stack:** Vue 3, Tailwind CSS 4 (OKLch tokens), Inertia.js, Laravel Blade

---

## File Map

| File | Action | Responsibility |
|------|--------|----------------|
| `resources/css/app.css` | Modify | Dark + Night Watch token overrides, theme transition, hardcoded color fixes |
| `resources/js/composables/useTheme.js` | Create | Theme state, localStorage, sunrise/sunset calculation, auto-switching |
| `resources/js/Layouts/AdminLayout.vue` | Modify | Theme toggle button, hardcoded color fixes in scoped styles |
| `resources/js/Layouts/NavLink.vue` | Modify | Replace hardcoded oklch colors with CSS custom properties |
| `resources/views/app.blade.php` | Modify | Inline script to apply saved theme before paint (prevents flash) |
| `resources/js/Pages/Admin/Settings.vue` | Modify | Replace `#6b7280` hardcoded color |
| `resources/js/Pages/Admin/Weather.vue` | Modify | Replace `oklch(0.55 0.15 240)` inline styles |
| `resources/js/Pages/Admin/StreamMonitor.vue` | Modify | Replace hardcoded SVG colors with CSS custom properties |
| `resources/js/Pages/Admin/Explore.vue` | Modify | Replace hardcoded chart/uPlot colors with token-aware values |
| `resources/js/Pages/Admin/Team.vue` | Modify | Replace hardcoded badge/modal colors |
| `resources/js/Pages/Admin/JourneyEdit.vue` | Modify | Replace hardcoded modal backdrop and error panel colors |
| `resources/js/Pages/Admin/BoatMetrics.vue` | Modify | Replace hardcoded SVG chart colors |
| `resources/js/Pages/Admin/Tracker.vue` | Modify | Replace hardcoded chart/SVG colors |
| `resources/js/Pages/Admin/Dashboard.vue` | Modify | Replace hardcoded colors |
| `resources/js/Pages/Admin/Journeys.vue` | Modify | Replace hardcoded colors |

---

### Task 1: CSS Dark Mode Token Overrides

**Files:**
- Modify: `resources/css/app.css`

- [ ] **Step 1: Add dark mode token overrides after the `@theme` block**

Add this block immediately after the closing `}` of the `@theme` block (after line 27):

```css
/* ── Dark mode ──────────────────────────────────────────── */

html[data-theme="dark"] {
    --color-surface: oklch(0.20 0.01 50);
    --color-bg: oklch(0.16 0.01 50);
    --color-border: oklch(0.28 0.01 50);
    --color-border-light: oklch(0.24 0.008 50);
    --color-text-primary: oklch(0.90 0.01 50);
    --color-text-secondary: oklch(0.65 0.008 50);
    --color-text-dim: oklch(0.50 0.008 50);
    --color-scarlet-light: oklch(0.54 0.22 27 / 0.12);
    --color-green: oklch(0.65 0.15 155);
    --color-green-bg: oklch(0.62 0.15 155 / 0.15);
    --color-amber: oklch(0.72 0.14 70);
    --color-amber-bg: oklch(0.70 0.14 70 / 0.15);
    --color-error: oklch(0.62 0.20 27);
    --color-error-bg: oklch(0.58 0.20 27 / 0.15);
    color-scheme: dark;
}
```

- [ ] **Step 2: Add night watch token overrides after the dark block**

```css
/* ── Night watch (scotopic vision preservation) ─────────── */

html[data-theme="night"] {
    --color-surface: oklch(0.08 0.01 27);
    --color-bg: oklch(0.05 0.005 27);
    --color-border: oklch(0.15 0.05 27);
    --color-border-light: oklch(0.12 0.03 27);
    --color-text-primary: oklch(0.45 0.12 27);
    --color-text-secondary: oklch(0.35 0.10 27);
    --color-text-dim: oklch(0.28 0.08 27);
    --color-scarlet: oklch(0.40 0.18 27);
    --color-scarlet-hover: oklch(0.35 0.16 27);
    --color-scarlet-light: oklch(0.40 0.18 27 / 0.15);
    --color-green: oklch(0.35 0.08 27);
    --color-green-bg: oklch(0.35 0.08 27 / 0.15);
    --color-amber: oklch(0.38 0.10 40);
    --color-amber-bg: oklch(0.38 0.10 40 / 0.15);
    --color-error: oklch(0.35 0.12 27);
    --color-error-bg: oklch(0.35 0.12 27 / 0.15);
    color-scheme: dark;
}
```

- [ ] **Step 3: Add theme transition rule and fix hardcoded colors**

Add after the night watch block:

```css
/* ── Theme transition ───────────────────────────────────── */

html[data-theme] *,
html[data-theme] *::before,
html[data-theme] *::after {
    transition: background-color 0.2s ease, color 0.2s ease, border-color 0.2s ease, fill 0.2s ease, stroke 0.2s ease;
}

@media (prefers-reduced-motion: reduce) {
    html[data-theme] *,
    html[data-theme] *::before,
    html[data-theme] *::after {
        transition: none;
    }
}
```

Also fix the two hardcoded colors already in `app.css`:

Replace line 60 (`.field-input:focus` box-shadow):
```css
    box-shadow: 0 0 0 2px var(--color-scarlet-light);
```

Replace lines 121-126 (`.btn--danger` and `.btn--danger:hover`):
```css
.btn--danger {
    background: var(--color-error);
    color: white;
}

.btn--danger:hover {
    background: var(--color-scarlet-hover);
}
```

- [ ] **Step 4: Verify the CSS parses correctly**

Run: `npx vite build 2>&1 | head -20`
Expected: Build succeeds with no CSS parse errors.

- [ ] **Step 5: Commit**

```bash
git add resources/css/app.css
git commit -m "feat: add dark mode and night watch CSS token overrides"
```

---

### Task 2: Flash-Prevention Script in Blade Template

**Files:**
- Modify: `resources/views/app.blade.php`

The theme must be applied before the first paint to avoid a flash of the light theme. Add a tiny inline script in `<head>` that reads localStorage and sets `data-theme` synchronously.

- [ ] **Step 1: Add theme init script to `app.blade.php`**

Add this immediately after the opening `<head>` tag (before `<meta charset>`), on line 3:

```html
    <script>
        (function() {
            var t = localStorage.getItem('scarlet-theme');
            if (t === 'dark' || t === 'night') {
                document.documentElement.setAttribute('data-theme', t);
            }
        })();
    </script>
```

This runs synchronously before CSS loads, so the correct token overrides are active from the first paint.

- [ ] **Step 2: Commit**

```bash
git add resources/views/app.blade.php
git commit -m "feat: add theme flash-prevention script to Blade template"
```

---

### Task 3: `useTheme` Composable

**Files:**
- Create: `resources/js/composables/useTheme.js`

- [ ] **Step 1: Create the composable file**

Create `resources/js/composables/useTheme.js` with this content:

```js
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

function applyTheme(t) {
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

export function useTheme() {
    let autoInterval = null;
    let gpsInterval = null;
    let gpsPosition = null;

    const effectiveTheme = computed(() => theme.value);

    function cycleTheme() {
        const idx = THEMES.indexOf(theme.value);
        const next = THEMES[(idx + 1) % THEMES.length];
        autoMode.value = false;
        localStorage.setItem(LS_AUTO, 'false');
        applyTheme(next);
    }

    function enableAuto() {
        autoMode.value = true;
        localStorage.setItem(LS_AUTO, 'true');
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
        applyTheme(dark ? 'dark' : 'light');
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
        cycleTheme,
        enableAuto,
    };
}
```

- [ ] **Step 2: Verify the composable imports work**

Run: `npx vite build 2>&1 | head -20`
Expected: Build succeeds (composable isn't used yet, but no import errors).

- [ ] **Step 3: Commit**

```bash
git add resources/js/composables/useTheme.js
git commit -m "feat: add useTheme composable with solar auto-switching"
```

---

### Task 4: Theme Toggle Button in AdminLayout

**Files:**
- Modify: `resources/js/Layouts/AdminLayout.vue`

- [ ] **Step 1: Add useTheme import and toggle button to template**

In the `<script setup>` section, add the import (after the existing NavLink import on line 86):

```js
import { useTheme } from '../composables/useTheme.js';
```

Add the destructured composable (after the `sidebarOpen` ref on line 93):

```js
const { theme, autoMode, cycleTheme, enableAuto } = useTheme();
let longPressTimer = null;

function onPointerDown() {
    longPressTimer = setTimeout(() => {
        enableAuto();
        longPressTimer = null;
    }, 800);
}

function onPointerUp() {
    if (longPressTimer) {
        clearTimeout(longPressTimer);
        longPressTimer = null;
        cycleTheme();
    }
}
```

In the template, add the toggle button in two places:

**Desktop** — inside the `<main>` tag, before the mobile header `<div>` (after `<main class="flex-1 overflow-y-auto px-4 py-5 md:px-10 md:py-8">`, line 68):

```html
            <!-- Theme toggle -->
            <button
                class="theme-toggle hidden md:flex"
                :title="autoMode ? `Auto: ${theme}` : theme.charAt(0).toUpperCase() + theme.slice(1) + ' mode'"
                @pointerdown.prevent="onPointerDown"
                @pointerup="onPointerUp"
                @pointerleave="onPointerUp"
            >
                <!-- Sun icon (light) -->
                <svg v-if="theme === 'light'" viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                <!-- Moon icon (dark) -->
                <svg v-else-if="theme === 'dark'" viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                <!-- Eye icon (night watch) -->
                <svg v-else viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                <!-- Auto mode indicator dot -->
                <span v-if="autoMode" class="theme-toggle__dot"></span>
            </button>
```

**Mobile** — inside the mobile header `<div>` (the `<div class="flex items-center gap-3 mb-4 md:hidden">` block), add the toggle button after the Scarlet `<span>` (line 74), before the closing `</div>`:

```html
                <button
                    class="theme-toggle ml-auto md:hidden"
                    :title="autoMode ? `Auto: ${theme}` : theme.charAt(0).toUpperCase() + theme.slice(1) + ' mode'"
                    @pointerdown.prevent="onPointerDown"
                    @pointerup="onPointerUp"
                    @pointerleave="onPointerUp"
                >
                    <svg v-if="theme === 'light'" viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                    <svg v-else-if="theme === 'dark'" viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                    <svg v-else viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    <span v-if="autoMode" class="theme-toggle__dot"></span>
                </button>
```

- [ ] **Step 2: Add theme toggle styles to scoped CSS**

Add these styles at the end of the `<style scoped>` section (before the closing `</style>`):

```css
.theme-toggle {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 20;
    width: 36px;
    height: 36px;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    color: var(--color-text-secondary);
    cursor: pointer;
    transition: border-color 0.12s ease-out, color 0.12s ease-out;
    -webkit-user-select: none;
    user-select: none;
}

.theme-toggle:hover {
    border-color: var(--color-text-dim);
    color: var(--color-text-primary);
}

.theme-toggle:focus-visible {
    outline: 2px solid var(--color-scarlet);
    outline-offset: 2px;
}

.theme-toggle__dot {
    position: absolute;
    top: 2px;
    right: 2px;
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: var(--color-green);
}

@media (max-width: 767px) {
    .theme-toggle {
        position: relative;
        top: auto;
        right: auto;
    }
}
```

- [ ] **Step 3: Fix hardcoded colors in AdminLayout scoped styles**

Replace line 140 in the scoped CSS:
```css
    color: oklch(0.60 0.005 40);
```
with:
```css
    color: var(--color-text-dim);
```

Replace line 154 in `.nav-link`:
```css
    color: oklch(0.45 0.005 40);
```
with:
```css
    color: var(--color-text-secondary);
```

Replace line 160 in `.nav-link:hover`:
```css
.nav-link:hover { background: oklch(0.98 0.003 70); color: oklch(0.18 0.005 40); }
```
with:
```css
.nav-link:hover { background: var(--color-bg); color: var(--color-text-primary); }
```

- [ ] **Step 4: Verify the build succeeds**

Run: `npx vite build 2>&1 | head -20`
Expected: Build succeeds with no errors.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Layouts/AdminLayout.vue
git commit -m "feat: add theme toggle button to admin layout"
```

---

### Task 5: Fix NavLink Hardcoded Colors

**Files:**
- Modify: `resources/js/Layouts/NavLink.vue`

- [ ] **Step 1: Replace hardcoded oklch colors in NavLink scoped styles**

In `resources/js/Layouts/NavLink.vue`, replace line 39:
```css
    color: oklch(0.45 0.005 40);
```
with:
```css
    color: var(--color-text-secondary);
```

Replace line 45:
```css
.nav-link:hover { background: oklch(0.98 0.003 70); color: oklch(0.18 0.005 40); }
```
with:
```css
.nav-link:hover { background: var(--color-bg); color: var(--color-text-primary); }
```

Replace lines 49-51:
```css
.nav-link--active {
    background: oklch(0.54 0.22 27 / 0.06);
    color: oklch(0.54 0.22 27);
    font-weight: 600;
}
```
with:
```css
.nav-link--active {
    background: var(--color-scarlet-light);
    color: var(--color-scarlet);
    font-weight: 600;
}
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/Layouts/NavLink.vue
git commit -m "fix: replace hardcoded NavLink colors with theme tokens"
```

---

### Task 6: Fix Hardcoded Colors in Admin Pages (Batch 1 — Small Files)

**Files:**
- Modify: `resources/js/Pages/Admin/Settings.vue`
- Modify: `resources/js/Pages/Admin/Weather.vue`
- Modify: `resources/js/Pages/Admin/JourneyEdit.vue`

These files have 1-3 hardcoded colors each.

- [ ] **Step 1: Fix Settings.vue**

In `resources/js/Pages/Admin/Settings.vue`, find line 81 — the modal confirmation text with inline style:
```html
<p style="font-size: 14px; color: #6b7280; margin: 0 0 24px; line-height: 1.5;">
```
Replace with:
```html
<p class="text-sm text-text-secondary leading-normal mb-6">
```

- [ ] **Step 2: Fix Weather.vue**

In `resources/js/Pages/Admin/Weather.vue`, the `oklch(0.55 0.15 240)` color is a blue accent for water temperature values. This needs a new CSS custom property since it doesn't map to any existing token. Add to `app.css` `@theme` block:

```css
    --color-blue: oklch(0.55 0.15 240);
```

And add to the dark mode override:
```css
    --color-blue: oklch(0.60 0.15 240);
```

And add to the night watch override (map to dim red — no blue light allowed):
```css
    --color-blue: oklch(0.35 0.10 27);
```

Then in `Weather.vue`, replace line 56:
```html
<span style="color: oklch(0.55 0.15 240)">
```
with:
```html
<span class="text-blue">
```

And line 57 similarly:
```html
<span style="color: oklch(0.55 0.15 240)">
```
with:
```html
<span class="text-blue">
```

- [ ] **Step 3: Fix JourneyEdit.vue**

In `resources/js/Pages/Admin/JourneyEdit.vue`, line 87 has a hardcoded error background:
```html
<div class="panel p-6 border-error bg-[oklch(0.58_0.20_27_/_0.08)]">
```
Replace with:
```html
<div class="panel p-6 border-error bg-error-bg">
```

Lines 185 and 194 in the scoped styles contain modal backdrop and shadow colors. Replace:
```css
    background: oklch(0.05 0.008 40 / 0.45);
```
with:
```css
    background: oklch(from var(--color-text-primary) l c h / 0.45);
```

If relative color syntax causes issues, use a simpler approach:
```css
    background: color-mix(in oklch, var(--color-text-primary) 45%, transparent);
```

Or simplest fallback:
```css
    background: rgba(0, 0, 0, 0.45);
```

For the box-shadow on line 194:
```css
    box-shadow: 0 8px 40px oklch(0.05 0.008 40 / 0.14);
```
Replace with:
```css
    box-shadow: 0 8px 40px rgba(0, 0, 0, 0.14);
```

These are backdrop/shadow colors that work fine in all themes with neutral black-based values.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/Admin/Settings.vue resources/js/Pages/Admin/Weather.vue resources/js/Pages/Admin/JourneyEdit.vue resources/css/app.css
git commit -m "fix: replace hardcoded colors in Settings, Weather, JourneyEdit"
```

---

### Task 7: Fix Hardcoded Colors in Admin Pages (Batch 2 — Team.vue)

**Files:**
- Modify: `resources/js/Pages/Admin/Team.vue`

Team.vue has 10 hardcoded colors in scoped styles for role badges, invite status badges, and modal backdrop.

- [ ] **Step 1: Replace badge colors**

In `resources/js/Pages/Admin/Team.vue` scoped styles, replace the role badge styles (around lines 241-246):

```css
    background: oklch(0.54 0.22 27 / 0.12);
    color: oklch(0.54 0.22 27);
```
Replace with:
```css
    background: var(--color-scarlet-light);
    color: var(--color-scarlet);
```

The "member" role variant:
```css
    background: oklch(0.88 0.005 70);
    color: oklch(0.45 0.005 40);
```
Replace with:
```css
    background: var(--color-bg);
    color: var(--color-text-secondary);
```

Invite status badge active state (around lines 256-257):
```css
    background: oklch(0.54 0.22 27 / 0.10);
    color: oklch(0.54 0.22 27);
```
Replace with:
```css
    background: var(--color-scarlet-light);
    color: var(--color-scarlet);
```

Invite status badge pending state (around lines 260-261):
```css
    background: oklch(0.92 0.003 70);
    color: oklch(0.45 0.005 40);
```
Replace with:
```css
    background: var(--color-bg);
    color: var(--color-text-secondary);
```

- [ ] **Step 2: Replace modal backdrop and shadow**

Modal backdrop (around line 266):
```css
    background: oklch(0.05 0.008 40 / 0.45);
```
Replace with:
```css
    background: rgba(0, 0, 0, 0.45);
```

Modal shadow (around line 275):
```css
    box-shadow: 0 8px 40px oklch(0.05 0.008 40 / 0.14);
```
Replace with:
```css
    box-shadow: 0 8px 40px rgba(0, 0, 0, 0.14);
```

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/Admin/Team.vue
git commit -m "fix: replace hardcoded colors in Team page with theme tokens"
```

---

### Task 8: Fix Hardcoded Colors in Admin Pages (Batch 3 — StreamMonitor.vue)

**Files:**
- Modify: `resources/js/Pages/Admin/StreamMonitor.vue`

StreamMonitor has hardcoded SVG chart colors — blue for metrics values and scarlet/amber for chart lines/fills.

- [ ] **Step 1: Replace inline style blue values**

In `resources/js/Pages/Admin/StreamMonitor.vue`:

Line 49 — metric value display:
```html
style="color: oklch(0.55 0.15 240)"
```
Replace with:
```html
class="text-blue"
```
(uses the `--color-blue` token added in Task 6)

Line 102 — RTT value:
```html
style="color: oklch(0.55 0.15 240)"
```
Replace with:
```html
class="text-blue"
```

- [ ] **Step 2: Replace SVG chart colors**

Lines 80-81 (bitrate gradient):
```html
<stop offset="0%" stop-color="oklch(0.54 0.22 27)" stop-opacity="0.20"/>
<stop offset="100%" stop-color="oklch(0.54 0.22 27)" stop-opacity="0.02"/>
```
Replace with:
```html
<stop offset="0%" stop-color="var(--color-scarlet)" stop-opacity="0.20"/>
<stop offset="100%" stop-color="var(--color-scarlet)" stop-opacity="0.02"/>
```

Line 85 (bitrate line):
```html
stroke="oklch(0.54 0.22 27)"
```
Replace with:
```html
stroke="var(--color-scarlet)"
```

Lines 109-110 (RTT gradient):
```html
<stop offset="0%" stop-color="oklch(0.55 0.15 240)" stop-opacity="0.20"/>
<stop offset="100%" stop-color="oklch(0.55 0.15 240)" stop-opacity="0.02"/>
```
Replace with:
```html
<stop offset="0%" stop-color="var(--color-blue)" stop-opacity="0.20"/>
<stop offset="100%" stop-color="var(--color-blue)" stop-opacity="0.02"/>
```

Line 114 (RTT line):
```html
stroke="oklch(0.55 0.15 240)"
```
Replace with:
```html
stroke="var(--color-blue)"
```

Lines 136-137 (dropped packets gradient):
```html
<stop offset="0%" stop-color="oklch(0.75 0.15 65)" stop-opacity="0.20"/>
<stop offset="100%" stop-color="oklch(0.75 0.15 65)" stop-opacity="0.02"/>
```
Replace with:
```html
<stop offset="0%" stop-color="var(--color-amber)" stop-opacity="0.20"/>
<stop offset="100%" stop-color="var(--color-amber)" stop-opacity="0.02"/>
```

Line 141 (dropped packets line):
```html
stroke="oklch(0.75 0.15 65)"
```
Replace with:
```html
stroke="var(--color-amber)"
```

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/Admin/StreamMonitor.vue
git commit -m "fix: replace hardcoded SVG colors in StreamMonitor with theme tokens"
```

---

### Task 9: Fix Hardcoded Colors in Admin Pages (Batch 4 — BoatMetrics.vue)

**Files:**
- Modify: `resources/js/Pages/Admin/BoatMetrics.vue`

BoatMetrics has the most hardcoded colors (62). Most are SVG chart colors (green for positive, scarlet for primary, blue for secondary, amber for warnings). The approach: replace all SVG `stop-color`, `stroke`, and `fill` attributes with `var(--color-*)` references.

- [ ] **Step 1: Read the full file to understand the chart patterns**

Run: Read `resources/js/Pages/Admin/BoatMetrics.vue` to see all 62 occurrences.

- [ ] **Step 2: Replace all SVG hardcoded colors systematically**

Apply these replacements throughout the file:

| Hardcoded value | Replace with |
|----------------|-------------|
| `oklch(0.62 0.15 155)` | `var(--color-green)` |
| `oklch(0.62 0.15 155 / 0.18)` or similar opacity variants | `var(--color-green)` with appropriate `stop-opacity` |
| `oklch(0.62 0.15 155 / 0.08)` | `var(--color-green-bg)` |
| `oklch(0.54 0.22 27)` | `var(--color-scarlet)` |
| `oklch(0.54 0.22 27 / 0.18)` or similar | `var(--color-scarlet)` with `stop-opacity` |
| `oklch(0.55 0.15 240)` | `var(--color-blue)` |
| `oklch(0.65 0.18 40)` | `var(--color-amber)` |
| `oklch(0.65 0.18 40 / 0.08)` | `var(--color-amber-bg)` |
| `oklch(0.70 0.005 40)` | `var(--color-text-dim)` |
| `oklch(0.70 0.14 70)` | `var(--color-amber)` |

For inline `style="color: oklch(...)"` on legend dots and labels, replace with the appropriate Tailwind class (`text-green`, `text-amber`, `text-scarlet`, `text-blue`, `text-text-dim`).

For SVG gradient `<stop>` elements, use `stop-color="var(--color-green)"` with separate `stop-opacity` attribute.

For SVG `<polyline>` and `<line>` elements, use `stroke="var(--color-green)"`.

For SVG `<text>` elements, use `fill="var(--color-text-dim)"`.

- [ ] **Step 3: Verify the build**

Run: `npx vite build 2>&1 | head -20`
Expected: Build succeeds.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/Admin/BoatMetrics.vue
git commit -m "fix: replace hardcoded SVG chart colors in BoatMetrics with theme tokens"
```

---

### Task 10: Fix Hardcoded Colors in Admin Pages (Batch 5 — Tracker.vue)

**Files:**
- Modify: `resources/js/Pages/Admin/Tracker.vue`

Tracker has 27 hardcoded colors, mostly in SVG charts similar to BoatMetrics.

- [ ] **Step 1: Read the full file**

Run: Read `resources/js/Pages/Admin/Tracker.vue` to see all occurrences.

- [ ] **Step 2: Replace all hardcoded colors**

Apply the same replacement table as Task 9. Additionally, any Tracker-specific colors (signal strength indicators, etc.) should map to existing tokens:

| Pattern | Token |
|---------|-------|
| Green signal strength | `var(--color-green)` |
| Amber/warning signal | `var(--color-amber)` |
| Red/error signal | `var(--color-error)` |
| Blue accent | `var(--color-blue)` |
| Grey/dim text | `var(--color-text-dim)` |
| Grid/axis lines | `var(--color-border)` |

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/Admin/Tracker.vue
git commit -m "fix: replace hardcoded colors in Tracker with theme tokens"
```

---

### Task 11: Fix Hardcoded Colors in Admin Pages (Batch 6 — Explore.vue)

**Files:**
- Modify: `resources/js/Pages/Admin/Explore.vue`

Explore has 22 hardcoded colors: uPlot chart config, scoped CSS for presets/tooltips, and canvas drawing code.

- [ ] **Step 1: Read the full file**

Run: Read `resources/js/Pages/Admin/Explore.vue` to see all occurrences.

- [ ] **Step 2: Handle uPlot chart config colors**

uPlot config objects (around lines 410-443) use hardcoded colors for axes, grid, and series. These are JS object properties, not CSS — they can't use `var()` directly. Instead, read the CSS custom property values from the DOM:

Add a helper at the top of the `<script setup>`:
```js
function cssVar(name) {
    return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
}
```

Then replace the uPlot config color values:
```js
stroke: cssVar('--color-text-dim'),
grid: { stroke: cssVar('--color-border-light'), width: 1 },
ticks: { stroke: cssVar('--color-border'), width: 1 },
```

For series colors:
```js
stroke: cssVar('--color-green'),
fill: cssVar('--color-green-bg'),
```

For canvas drawing code (line 360):
```js
ctx.strokeStyle = cssVar('--color-text-dim');
```

- [ ] **Step 3: Fix scoped CSS hardcoded colors**

Replace scoped CSS hardcoded colors in the preset button and tooltip styles:

Around line 780:
```css
    background: oklch(0.70 0.14 70 / 0.1);
    border: 1px solid oklch(0.70 0.14 70 / 0.3);
```
Replace with:
```css
    background: var(--color-amber-bg);
    border: 1px solid var(--color-amber);
```

Around line 784:
```css
    color: oklch(0.50 0.14 70);
```
Replace with:
```css
    color: var(--color-amber);
```

Around line 857 (tooltip background):
```css
    background: oklch(0.18 0.005 40 / 0.92);
```
Replace with:
```css
    background: color-mix(in oklch, var(--color-text-primary) 92%, transparent);
```

Or simpler fallback:
```css
    background: rgba(0, 0, 0, 0.92);
```

Around line 869 and 884 (tooltip text colors):
```css
    color: oklch(0.70 0.005 40);
```
Replace with:
```css
    color: var(--color-text-dim);
```

```css
    color: oklch(0.50 0.005 40);
```
Replace with:
```css
    color: var(--color-text-secondary);
```

uPlot selection and cursor overrides (lines 959-960):
```css
:deep(.u-select) { background: oklch(0.54 0.22 27 / 0.1) !important; }
:deep(.u-cursor-x) { border-right: 1px dashed oklch(0.54 0.22 27 / 0.4) !important; }
```
Replace with:
```css
:deep(.u-select) { background: var(--color-scarlet-light) !important; }
:deep(.u-cursor-x) { border-right: 1px dashed var(--color-scarlet) !important; }
```

- [ ] **Step 4: Handle the computed stat color (line 185)**

```js
return stats.value.current >= 0 ? 'oklch(0.62 0.15 155)' : 'oklch(0.65 0.18 40)';
```
Replace with:
```js
return stats.value.current >= 0 ? cssVar('--color-green') : cssVar('--color-amber');
```

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/Admin/Explore.vue
git commit -m "fix: replace hardcoded colors in Explore with theme-aware tokens"
```

---

### Task 12: Fix Remaining Admin Pages (Dashboard.vue, Journeys.vue)

**Files:**
- Modify: `resources/js/Pages/Admin/Dashboard.vue`
- Modify: `resources/js/Pages/Admin/Journeys.vue`

- [ ] **Step 1: Read and fix Dashboard.vue**

Run: Read `resources/js/Pages/Admin/Dashboard.vue`, find the 5 hardcoded color instances, and replace them using the same token mapping.

- [ ] **Step 2: Read and fix Journeys.vue**

Run: Read `resources/js/Pages/Admin/Journeys.vue`, find the 5 hardcoded color instances, and replace them using the same token mapping.

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/Admin/Dashboard.vue resources/js/Pages/Admin/Journeys.vue
git commit -m "fix: replace hardcoded colors in Dashboard and Journeys pages"
```

---

### Task 13: Final Audit and Verification

**Files:**
- All modified files

- [ ] **Step 1: Verify no hardcoded oklch values remain in admin pages**

Run: `grep -rn "oklch" resources/js/Pages/Admin/ resources/js/Layouts/ --include="*.vue" | grep -v "node_modules" | grep -v "var(--color" | grep -v "cssVar"`

Expected: Zero results (all oklch values should now reference tokens), or only values that are intentionally unique (e.g., SVG gradient opacity variants that correctly use `var()` with `stop-opacity`).

- [ ] **Step 2: Verify no hardcoded hex colors remain**

Run: `grep -rn "#[0-9a-fA-F]\{3,8\}" resources/js/Pages/Admin/ resources/js/Layouts/ --include="*.vue"`

Expected: Zero results.

- [ ] **Step 3: Full build test**

Run: `npx vite build 2>&1 | tail -10`

Expected: Build succeeds with no errors.

- [ ] **Step 4: Manual test**

Start the dev server and test all three themes:
1. Open admin in browser
2. Click theme toggle — verify Light → Dark → Night Watch → Light cycle
3. Verify sidebar, panels, inputs, buttons, charts all change correctly
4. Verify auto mode dot indicator appears/disappears
5. Long-press toggle — verify auto mode re-enables
6. Set to dark, reload page — verify no flash of light theme
7. Check mobile layout — verify toggle appears in header bar

- [ ] **Step 5: Commit any remaining fixes**

```bash
git add -A
git commit -m "chore: final dark mode audit and cleanup"
```
