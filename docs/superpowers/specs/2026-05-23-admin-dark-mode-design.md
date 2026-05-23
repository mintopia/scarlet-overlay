# Admin Dark Mode & Night Watch

## Overview

Add two alternative themes to the admin pages: a **Dark mode** for general low-light comfort and a **Night Watch mode** for preserving scotopic (rod-cell) night vision during helm duty. Automatic sunset/sunrise switching uses the boat's live GPS position.

Scope: admin pages only (16 pages + AdminLayout sidebar). Public dashboard, overlay, and auth pages are unchanged.

## Architecture: CSS Custom Property Swap

The existing `app.css` defines ~20 OKLch design tokens in `@theme`. Dark and Night Watch modes override these tokens via `data-theme` attributes on `<html>`:

```
html                         → light (default, no attribute)
html[data-theme="dark"]      → warm dark surfaces, light text
html[data-theme="night"]     → near-black surfaces, dim red/amber UI
```

No Vue component templates change. All components already reference `var(--color-*)` tokens via Tailwind classes (`bg-surface`, `text-text-primary`, etc.) or CSS custom properties.

## Token Overrides

### Dark Mode (warm undertone, hue 50)

| Token | Light | Dark |
|-------|-------|------|
| `--color-surface` | `oklch(0.995 0.003 70)` | `oklch(0.20 0.01 50)` |
| `--color-bg` | `oklch(0.97 0.003 70)` | `oklch(0.16 0.01 50)` |
| `--color-border` | `oklch(0.90 0.005 70)` | `oklch(0.28 0.01 50)` |
| `--color-border-light` | `oklch(0.93 0.003 70)` | `oklch(0.24 0.008 50)` |
| `--color-text-primary` | `oklch(0.18 0.005 40)` | `oklch(0.90 0.01 50)` |
| `--color-text-secondary` | `oklch(0.45 0.005 40)` | `oklch(0.65 0.008 50)` |
| `--color-text-dim` | `oklch(0.55 0.005 40)` | `oklch(0.50 0.008 50)` |
| `--color-scarlet` | `oklch(0.54 0.22 27)` | unchanged |
| `--color-scarlet-hover` | `oklch(0.48 0.22 27)` | unchanged |
| `--color-scarlet-light` | `oklch(0.54 0.22 27 / 0.06)` | `oklch(0.54 0.22 27 / 0.12)` |
| `--color-green` | `oklch(0.62 0.15 155)` | `oklch(0.65 0.15 155)` |
| `--color-green-bg` | `oklch(0.62 0.15 155 / 0.08)` | `oklch(0.62 0.15 155 / 0.15)` |
| `--color-amber` | `oklch(0.70 0.14 70)` | `oklch(0.72 0.14 70)` |
| `--color-amber-bg` | `oklch(0.70 0.14 70 / 0.08)` | `oklch(0.70 0.14 70 / 0.15)` |
| `--color-error` | `oklch(0.58 0.20 27)` | `oklch(0.62 0.20 27)` |
| `--color-error-bg` | `oklch(0.58 0.20 27 / 0.08)` | `oklch(0.58 0.20 27 / 0.15)` |

### Night Watch Mode (scotopic vision preservation)

| Token | Night Watch |
|-------|-------------|
| `--color-surface` | `oklch(0.08 0.01 27)` |
| `--color-bg` | `oklch(0.05 0.005 27)` |
| `--color-border` | `oklch(0.15 0.05 27)` |
| `--color-border-light` | `oklch(0.12 0.03 27)` |
| `--color-text-primary` | `oklch(0.45 0.12 27)` |
| `--color-text-secondary` | `oklch(0.35 0.10 27)` |
| `--color-text-dim` | `oklch(0.28 0.08 27)` |
| `--color-scarlet` | `oklch(0.40 0.18 27)` |
| `--color-scarlet-hover` | `oklch(0.35 0.16 27)` |
| `--color-scarlet-light` | `oklch(0.40 0.18 27 / 0.15)` |
| `--color-green` | `oklch(0.35 0.08 27)` |
| `--color-green-bg` | `oklch(0.35 0.08 27 / 0.15)` |
| `--color-amber` | `oklch(0.38 0.10 40)` |
| `--color-amber-bg` | `oklch(0.38 0.10 40 / 0.15)` |
| `--color-error` | `oklch(0.35 0.12 27)` |
| `--color-error-bg` | `oklch(0.35 0.12 27 / 0.15)` |

Night Watch maps all status colors toward the red/amber end of the spectrum and drastically reduces lightness. The goal is zero blue/green light emission.

## Theme Switching Composable: `useTheme()`

File: `resources/js/composables/useTheme.js`

### State

- `theme` — reactive ref: `'light' | 'dark' | 'night'`
- `autoMode` — boolean: whether automatic sunset/sunrise switching is active (default: `true`)
- `effectiveTheme` — computed: the theme currently applied to the DOM

### Behaviour

- **On mount:** Read `scarlet-theme` and `scarlet-theme-auto` from localStorage. If auto mode is on and no manual override exists, calculate whether current time is between sunset and sunrise.
- **Manual toggle:** Click cycles Light → Dark → Night Watch → Light. Any manual selection disables auto mode.
- **Re-enable auto:** Long-press the toggle button to re-enable auto mode.
- **Auto mode logic:** Recalculates every 60 seconds. Between sunset and sunrise → applies dark mode. Auto mode never switches to Night Watch — that is always a deliberate user choice.
- **DOM application:** Sets `data-theme` attribute on `document.documentElement`. Light mode removes the attribute entirely.

### Sunrise/Sunset Calculation

Uses the boat's live GPS coordinates to compute sunrise and sunset via standard solar declination equations (~30 lines, no external library):

1. On mount (and every 10 minutes), fetch position from `/api/v1/gps` — a public endpoint already available
2. Calculate day-of-year and solar declination angle
3. Compute hour angle for the sun at the horizon
4. Convert to local sunrise/sunset times

Falls back to 06:00–20:00 local time if no GPS position is available or the fetch fails. Caches the last known position in localStorage (`scarlet-gps-cache`) so the first page load doesn't need to wait for the API.

### Persistence (localStorage)

| Key | Values | Purpose |
|-----|--------|---------|
| `scarlet-theme` | `'light'`, `'dark'`, `'night'` | Manual theme override |
| `scarlet-theme-auto` | `'true'`, `'false'` | Whether auto switching is enabled |

## Toggle UI

### Placement

- **Desktop:** Fixed-position button, top-right of main content area (`position: fixed; top: 20px; right: 20px; z-index: 20`)
- **Mobile:** In the mobile header bar, next to the hamburger menu button

### Appearance

36px round button matching the existing admin UI conventions (14px border-radius, surface background, border).

Three states with distinct icons:
- **Light:** Sun icon — subtle border, blends with light theme
- **Dark:** Moon icon — warm surface background
- **Night Watch:** Eye icon — dim red glow

### Interaction

- **Single click:** Cycles through Light → Dark → Night Watch → Light. Disables auto mode.
- **Long-press (~800ms):** Re-enables auto mode. A brief tooltip confirms "Auto mode on."
- **Hover tooltip:** Shows current mode name (e.g., "Dark mode" or "Auto: Dark").

### Auto Mode Indicator

When auto mode is active, a small dot appears on the button (like a notification badge) to signal automatic switching is engaged. The dot disappears when the user manually overrides.

## Hardcoded Color Cleanup

The following hardcoded oklch values in `AdminLayout.vue` scoped styles must be converted to token references:

| Selector | Current | Replacement |
|----------|---------|-------------|
| `.nav-label` color | `oklch(0.60 0.005 40)` | `var(--color-text-dim)` |
| `.nav-link` color | `oklch(0.45 0.005 40)` | `var(--color-text-secondary)` |
| `.nav-link:hover` background | `oklch(0.98 0.003 70)` | `var(--color-bg)` |
| `.nav-link:hover` color | `oklch(0.18 0.005 40)` | `var(--color-text-primary)` |

Additionally, audit all 16 admin page components for any inline oklch values or hardcoded color literals. Replace with token references where found.

## Transition

Theme changes apply a brief CSS transition on background and color properties (`transition: background-color 0.2s ease, color 0.2s ease, border-color 0.2s ease`) on the root element, so the swap isn't jarring. Respects `prefers-reduced-motion`.

## Files Changed

| File | Change |
|------|--------|
| `resources/css/app.css` | Add `html[data-theme="dark"]` and `html[data-theme="night"]` token override blocks |
| `resources/js/composables/useTheme.js` | New composable: theme state, auto-switching, sunrise/sunset calc, localStorage |
| `resources/js/Layouts/AdminLayout.vue` | Import `useTheme`, add toggle button to template, fix hardcoded colors in scoped styles |
| `resources/js/Pages/Admin/*.vue` | Audit and fix any hardcoded color values (expected: minimal changes) |
