# Overlay + Dashboard Refactor

## Goal

Unify the stream overlay (`overlay.js` + Blade template) and public dashboard (`Dashboard.vue`) into two Vue/Inertia pages sharing a single composable for all common logic. The overlay currently uses vanilla JS with imperative DOM manipulation; the dashboard uses Vue. They do the same thing: subscribe to WebSocket metrics, render a Leaflet map with speed-coloured track, display weather/status/clock/coordinates. The overlay adds video feed (WHEP/HLS) and a state machine for stream states.

## Architecture

Extract shared reactive state and map management into `useScarletMetrics()`. Extract video feed logic into `useVideoFeed()`. Convert the overlay from Blade + vanilla JS to a Vue/Inertia page with its own bare layout. Both pages become thin templates consuming composables.

The existing `scarlet.js` utility module (pure functions: `speedToColor`, `makeBoatIcon`, `formatCoord`, `formatVal`, `getWeatherIcon`, `getWeatherLabel`) stays unchanged.

## File Structure

### New files

- `resources/js/composables/useScarletMetrics.js` -- shared composable
- `resources/js/composables/useVideoFeed.js` -- overlay-only video composable
- `resources/js/Pages/Public/Overlay.vue` -- overlay as Vue/Inertia page
- `resources/views/overlay-app.blade.php` -- bare Inertia layout for overlay (no admin chrome)

### Modified files

- `resources/js/Pages/Public/Dashboard.vue` -- refactored to use `useScarletMetrics()`
- `app/Http/Controllers/OverlayController.php` -- switch from `view()` to `Inertia::render()`, set root view
- `vite.config.js` -- remove overlay.js and overlay.css entry points

### Deleted files

- `resources/js/overlay.js` -- replaced by Overlay.vue + composables
- `resources/views/overlay.blade.php` -- replaced by Inertia page
- `resources/css/overlay.css` -- styles move into Overlay.vue `<style scoped>`

## Composable: `useScarletMetrics(options)`

### Options

```js
{
    initialMetrics: Object|null,  // server-provided metrics (dashboard passes these, overlay does not)
    portName: String,             // for "In Port" status detection
    utcOffset: Number|null,       // clock UTC offset in hours (null = use browser local time)
    timeLabel: String|null,       // label after clock date, e.g. "CEST (UTC +0200)" (null = omit)
}
```

### Returned reactive state

| Name | Type | Description |
|---|---|---|
| `boat` | `ref({})` | Latest boat metrics from WebSocket or initialMetrics |
| `gps` | `ref({})` | Latest GPS data |
| `weather` | `ref(null)` | Latest weather data |
| `lastUpdate` | `ref(Date\|null)` | Timestamp of last WebSocket message (or `new Date()` if initialMetrics provided) |
| `clock` | `ref('--:--')` | Formatted time string, updated every second |
| `clockDate` | `ref('')` | Formatted date string |
| `coordText` | `computed(string)` | Formatted lat/lon via `formatCoord()` |
| `statusText` | `computed(string)` | 'In Port' / 'Under Sail' / 'Under Power' |
| `statusClass` | `computed(string)` | CSS class: 'status-port' / 'status-sail' / 'status-power' |
| `isOffline` | `computed(bool)` | True when lastUpdate is more than 2 hours ago |
| `lastUpdateText` | `computed(string)` | "Updated HH:MM" or empty string |
| `wxTemp` | `computed(string)` | Formatted temperature with degree symbol or '--' |
| `wxCondition` | `computed(string)` | Weather condition label via `getWeatherLabel()` |
| `wxIcon` | `computed(string)` | Weather emoji via `getWeatherIcon()` |
| `wxSeaTemp` | `computed(string)` | Sea temperature or '--' |
| `wxWindSpeed` | `computed(string)` | Wind speed with unit or '--' |
| `wxWindDir` | `computed(string)` | Wind direction or '' |
| `wxWaveHeight` | `computed(string)` | Wave height with unit or '--' |
| `wxWavePeriod` | `computed(string)` | Wave period with unit or '' |

### Returned methods

| Method | Signature | Description |
|---|---|---|
| `initMap` | `(el: HTMLElement, opts?: { interactive?: boolean }) => L.Map` | Creates a Leaflet map instance on the given element. `interactive: false` disables dragging, scroll zoom, touch zoom, double-click zoom, and keyboard (used for overlay PiP). All maps use the tile URL `/openseamap/{z}/{x}/{y}`, zoom 14, no zoom control, no attribution. |
| `addMapTarget` | `(map: L.Map, opts?: { autoCenter?: boolean\|Ref<boolean> }) => void` | Registers a map to receive boat marker and track updates. `autoCenter: true` (default) pans the map to follow the boat. Accepts a reactive ref so consumers can toggle auto-centering without re-registering. Multiple maps can be registered (overlay has PiP + full). |
| `removeMapTarget` | `(map: L.Map) => void` | Unregisters a map from updates. |
| `cleanup` | `() => void` | Clears clock interval, leaves Echo channel. Does not remove maps (the page handles that via `onUnmounted`). |

### Internal behavior

- On creation, subscribes to `Echo.channel('metrics').listen('.metrics.updated', ...)` and updates `boat`, `gps`, `weather`, `lastUpdate` refs on each message.
- On each WebSocket message, iterates registered map targets: updates or creates the boat marker (via `makeBoatIcon`), appends a speed-coloured track segment (via `speedToColor`), and optionally pans the map.
- Starts a 1-second clock interval using `utcOffset`/`timeLabel` if provided, browser local time otherwise.
- `isOffline` compares `lastUpdate` against a 2-hour threshold, recomputing reactively.
- Status logic: if `isOffline` is true, `statusText` returns 'Offline' and `statusClass` returns 'status-offline'. Otherwise: speed < 0.5 kn AND portName truthy = 'In Port', engine_rpm > 0 = 'Under Power', else 'Under Sail'.

## Composable: `useVideoFeed(videoEl)`

### Arguments

- `videoEl`: `Ref<HTMLVideoElement|null>` -- template ref to the `<video>` element

### Returned reactive state

| Name | Type | Description |
|---|---|---|
| `videoActive` | `ref(false)` | Whether video is currently playing and receiving frames |
| `videoChecked` | `ref(false)` | Whether the first connection attempt has completed (success or failure) |

### Returned methods

| Method | Description |
|---|---|
| `connect()` | Attempts WHEP connection, falls back to HLS. On success sets `videoActive = true`. On failure sets `videoActive = false` and schedules retry (5s). |
| `cleanup()` | Closes peer connection, stops HLS, clears watchdog and retry timers. |

### Internal behavior

- WHEP: creates RTCPeerConnection, POSTs offer to `/rtc/live/whep`, sets remote answer. 5-second connection timeout.
- HLS fallback: sets `<video>` src to `/hls/live/index.m3u8`, waits for `playing` event with 10-second timeout. Only attempted if WHEP fails with non-404 error.
- Watchdog: 1-second interval checking `framesDecoded` from WebRTC stats. 3 consecutive stalls (no new frames) triggers disconnect + retry.
- Connection state listener on the peer connection: `failed`/`disconnected`/`closed` triggers `videoActive = false` + retry.
- Retry: 5-second delay, single pending timer (no stacking).

## Overlay Vue page: `Overlay.vue`

### Props (from OverlayController via Inertia)

- `initialMetrics: Object|null`
- `boatName: String`
- `passageFrom: String`
- `passageTo: String`
- `portName: String`
- `utcOffset: Number`
- `timeLabel: String`

### State machine

Computed `overlayState` combining composable refs:

```
if isOffline          -> 'offline'
if statusText is 'In Port' -> 'port'
if videoActive        -> 'video-live'
if videoChecked       -> 'no-video'
else                  -> 'loading'
```

The root `<div>` gets `:data-state="overlayState"`. All CSS visibility rules from the current `overlay.css` that use `[data-state="..."]` selectors continue to work unchanged.

### Template structure

Same structure as current `overlay.blade.php`, converted to Vue template syntax:

- `<video>` element with template ref for `useVideoFeed`
- PiP map (`<div ref="mapPipEl">`) and full map (`<div ref="mapFullEl">`)
- LIVE badge with reactive text based on `overlayState`
- Weather pills using `wxTemp`, `wxIcon`, etc. from composable
- Bottom badges (coord, speed legend)
- Offline card (conditional on `overlayState === 'offline'`)
- Lower third with metrics, status pill, passage/port info, clock

### Viewport scaling

The overlay scales to fit 1920x1080 into the actual viewport using `transform: scale()`. This stays in the component's `onMounted` with a resize listener, same as today.

### Styles

All CSS from `resources/css/overlay.css` moves into `<style scoped>` in `Overlay.vue`. The `[data-state]` selector pattern is preserved since the root div has the reactive `data-state` attribute.

### Map management

On mount, creates two maps via `initMap()`:
- PiP map: `interactive: false`
- Full map: `interactive: true` (default)

Registers both via `addMapTarget()`. The full map uses `autoCenter: true` only when `overlayState !== 'video-live'` (same as today: don't pan the full map while video is showing). The PiP map always auto-centers.

## Dashboard Vue page: `Dashboard.vue`

### Changes from current

- Remove all inline state management (boat/gps/weather refs, WebSocket listener, clock interval, status computation, weather computed properties, `speedToColor`, `makeBoatIcon`, coordinate formatting).
- Import `useScarletMetrics` and destructure all needed refs/computeds.
- Keep dashboard-specific template and styles (interactive map controls, sailing instrument pills, speed legend, lower third with extra metrics).
- Map: single interactive map, registered via `addMapTarget()`. The `autoCenter` option is a reactive `Ref<boolean>` so the dashboard can set it to `false` on the map's `dragstart` event and back to `true` via the recentre button, without re-registering.

### Props remain the same

- `initialMetrics`, `boatName`, `passageFrom`, `passageTo`, `portName`, `tileUrl`, `reverb`, `reverbKey`

## Bare Inertia layout: `overlay-app.blade.php`

Minimal HTML shell for the overlay page. No admin sidebar, no Tailwind app.css, no `@routes` (ziggy). Includes:

- `<meta charset>` and viewport
- Outfit font (Google Fonts preconnect + link)
- Leaflet CSS/JS from CDN (same as current overlay.blade.php)
- `window.scarletConfig.reverb` for Echo bootstrap
- `@vite(['resources/js/app.js'])` for Inertia + Echo
- `@inertiaHead` and `@inertia`

The OverlayController sets this layout via `Inertia::setRootView('overlay-app')` before rendering.

## Controller changes

### OverlayController

Switch from `view('overlay', ...)` to `Inertia::render('Public/Overlay', ...)`. Add `Inertia::setRootView('overlay-app')`. Add `MetricsService` injection to provide `initialMetrics` (same as DashboardController). Pass `utcOffset` and `timeLabel` as props.

### DashboardController

No changes needed. Already passes all required props.

## Vite config changes

Remove `resources/css/overlay.css` and `resources/js/overlay.js` from the `input` array. The overlay now uses the same `app.js` entry point as all other Inertia pages. Overlay styles are scoped within the component.

## What stays unchanged

- `resources/js/scarlet.js` -- pure utility functions, no changes
- `resources/js/bootstrap.js` -- Echo setup, no changes
- `app/Events/MetricsUpdated.php` -- event shape, no changes
- `app/Console/Commands/MetricsPushCommand.php` -- push logic, no changes
- Route: `GET /overlay` stays the same, just serves an Inertia page now
- Route: `GET /` (dashboard) stays the same
