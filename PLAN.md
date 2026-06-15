# Plan: LCARS Star Trek easter egg ("LCARS Panel")
_Locked via grill-with-docs — by Claude + Jess. Terms per CONTEXT.md._

## Goal
Add a hidden, admin-only **LCARS Panel** (CONTEXT.md) that re-presents Scarlet's
existing live boat telemetry as a Star-Trek-TNG/DS9/VOY-era console. It is a
read-only alternate presentation of the existing **Metric Catalog** — no new data,
no changed product behaviour — reached by a Konami-code unlock on the admin
dashboard. Telemetry is reorganised into data-honest **Bridge Stations** with an
**MSD** landing view, rendered graph-led with "living instrument" motion. It
deliberately diverges from Scarlet's house style as a sanctioned easter egg
(ADR 0001).

## Approach

### 1. Routing & access
1. **Public** route `GET /lcars` → `LcarsController@index` →
   `Inertia::render('Admin/Lcars', ...)` (component name kept for path stability).
   No auth — exposes only data the public dashboard already broadcasts (§2 4b). The
   series endpoint `GET /lcars/series` is likewise public. (Originally `/admin/lcars`
   behind auth; opened up by request once confirmed to carry no non-public data.)
2. **Konami unlock**: on **both** the public (`Public/Dashboard.vue`) and admin
   (`Admin/Dashboard.vue`) dashboards, a key-sequence **state machine** matches
   `↑↑↓↓←→←→ B A` and calls `router.visit('/lcars')`. No visible link. Spec:
   - ignore the sequence when the event target is an editable element
     (`input`, `textarea`, `select`, `[contenteditable]`, or inside one);
   - ignore auto-repeat (`event.repeat`) and events with modifier keys held;
   - reset progress on any wrong key **and** on a short inactivity timeout
     (e.g. 2s between keys);
   - listener registered on mount, removed on unmount.
   - **Discovery is intentionally obscure** (it's an easter egg): we do **not** claim
     the Konami code is an accessible discovery path, and we do not pretend the unlinked
     URL is an accessibility accommodation. Reaching the egg is meant to be a hidden,
     keyboard-driven discovery. Separately, the panel *content*, once reached, still aims
     for reasonable accessibility (semantic text, reduced-motion, readable contrast).
3. **Exit**: an LCARS "END PROGRAM" control returns to `route('admin.dashboard')`;
   browser back also works.

### 2. Access boundary (public)
4a. The route is **public** (no `auth`). This was opened up from the original
   admin-gated design by request, justified entirely by 4b.
4b. Telemetry exposure: the public voyage dashboard (`GET /dashboard`, no auth) already
   renders `getAllMetrics()` and subscribes to the **public** `metrics` Reverb channel.
   Boat telemetry is therefore **already public by design** — the LCARS Panel exposes
   nothing the public dashboard doesn't. Identity (boat name, MMSI) is public AIS-grade
   info. So a public `/lcars` leaks nothing new; the egg stays hidden via the Konami-only
   discovery, not via auth.

### 2b. Data (reuse existing layer, read-only)
4. **Initial paint**: `LcarsController` returns `MetricsService::getAllMetrics()`
   (boat, tracker, gps, weather, settings, sun, timestamp) as `initialMetrics`,
   plus reverb config (mirroring `DashboardController`). Plus history for the **MSD**
   landing view only.
5. **Graph history — lazy, per-section, allow-listed (preload only MSD)**: each
   Bridge Station's line-history is fetched **on first selection** via a small JSON
   endpoint `GET /lcars/series?station=conn` (mirroring the existing
   `admin.explore.series` pattern), NOT all up-front. This avoids a synchronous
   dozens-of-queries fan-out blocking an Octane worker on page load (a self-DoS risk).
   - A fixed **allow-list** maps each station to an explicit set of `historical`
     registry keys + step. Window **6 hours**; step sized to the window (e.g. `120s`).
   - Per-key failures are isolated: the endpoint returns independently nullable series
     `{ key, points|null, status }` where `status` is a **stable public code**
     (`ok`/`unavailable`/`empty`) — raw exception detail is logged server-side only,
     never returned. One failed key or weather lookup never fails the whole station.
   - **Single source of truth**: the per-metric **presentation contract** (units,
     ranges, classification, source key) is the one definition; the backend station
     allow-list is **derived from it** — specifically it equals each station's subset
     of contract metrics classified `historical` (registry-backed). A parity test
     asserts `backend station allow-list == that historical subset` so they can't drift.
6. **Live updates**: the page subscribes to the existing public `metrics` Echo
   channel / `.metrics.updated` event via the existing `useScarletMetrics`
   composable (or a thin wrapper). The payload carries one feed-level `timestamp`.
   On each tick, for every displayed metric, distinguish three cases:
   - **key present, non-null** → update the current value; for `historical` metrics
     append a point **only if the value changed**, stamped with the event `timestamp`;
   - **key present but null** → **clear** the reading and set its `signal-loss` state
     (do NOT keep the previous value — present-null means the sensor reported nothing);
   - **key absent** → leave the prior value untouched (no new info this tick).
   Never fabricate a sample from absent keys.
   - **Live-tip timestamp = arrival time**: history preload keeps true source
     timestamps, but each live-appended point is stamped at **client arrival time
     (now)**, not the event's embedded source timestamp. A live console places each
     incoming reading at the right edge as it arrives; this is monotonic (no
     out-of-order), robust to source-clock skew/buffering, and keeps replayed/demo
     feeds in-window. (Refined during the live data demo.)
   - **Shared-channel SPA-swap safety**: the `metrics` Echo channel is shared with the
     public dashboard composable, which calls `Echo.leave('metrics')` on unmount. During
     an SPA navigation dashboard→LCARS that teardown can race and silently kill the LCARS
     subscription ("AWAITING LINK"). Fix: LCARS subscribes in `onMounted` deferred a tick
     (after the swap settles) and on cleanup removes only its own listeners
     (`stopListening`), never `Echo.leave` the shared channel. (Found via the Konami path
     during the live demo.)
   - **History↔live race & reconciliation**: live events that arrive while a station's
     lazy history is still loading are **buffered**; when history resolves, merge by
     timestamp (history first, then buffered + ongoing live points), **dedupe by
     timestamp**, and **reject out-of-order/older** points. Live cadence (~15s push)
     is finer than the 120s history buckets — that mixed density is acceptable; the
     graph simply continues at native cadence past the last history bucket.
   - **Ring-buffer eviction**: evict by **timestamp window** (drop points older than
     6h), with a separate **max-point safety cap** (downsample/decimate if exceeded)
     so a long-open panel can neither show stale history nor grow unbounded. The graph
     **domain is fixed `[now-6h, now]`**. Separately, keep the **last-known value +
     its timestamp as metadata** (outside the sample buffer). If no sample falls inside
     the window (e.g. a metric unchanged for > 6h), the graph does not empty: it renders
     the last-known value as a clearly-distinct **"LAST KNOWN" dashed/dimmed flat line**
     across the domain plus the numeric readout — explicitly NOT drawn as measured
     samples. Changed-only sampling therefore never makes a still-valid reading vanish,
     and we never misrepresent an extrapolation as data.
7. **Metric classification** (drives rendering, part of the presentation contract):
   every displayed metric is tagged `historical` (line graph; the only class that hits
   the series endpoint), `instantaneous` (gauge/dial from the **live payload or null**,
   no history query), `categorical` (status/mode/connectivity badge from the live
   payload), or `identity` (name/registry/almanac text). No metric is both
   instantaneous and history-backed — the class is fixed per metric.
8. **Freshness is feed-level** (honest about the data): the payload has a single event
   `timestamp`, not per-metric source times, so we represent **feed freshness** — after
   a staleness threshold (e.g. > 3× push interval with no event) the whole panel shows a
   "SIGNAL LOST / LAST CONTACT hh:mm:ss" state and stops animating as if live. Plus
   per-reading `signal-loss` only where a present-null was received (step 6). We do not
   claim per-metric freshness we don't have. A living instrument must not confidently
   animate dead data.
9. **Presentation contract per metric** (CONTEXT.md): a single front-end map giving
   unit, valid range + wraparound (e.g. heading 0–360 wrap, current ±, RSSI dBm,
   tank/SOC 0–100%, pressure bounds), formatter (reuse `useFormatters`), and null
   render. Gauges read bounds from this map, never infer them.
10. No new backend metrics, no new broadcast events, no DB writes.

### 3. Bridge Stations (data-honest roster — CONTEXT.md)
8. Left **Elbow Rail** buttons select one **Console Section** at a time:
   - **MSD** (landing): sloop side-profile SVG with live call-outs (battery bank,
     fuel/water tanks, cabin temps, depth-under-keel, heel tilts the hull), boat
     name + registry (MMSI), key vitals strip.
   - **CONN** — SOG, STW, heading, COG, heel, pitch, VMG, apparent wind angle.
   - **NAVIGATION** — GPS pos, satellites, HDOP, depth, waypoint distance/TTG,
     trip log.
   - **ENGINEERING** — house battery bank (SOC/V/A/power/time-remaining), engine
     battery, tracker health (CPU/heap/uptime).
   - **OPS** — fuel/water tanks, LTE/WiFi connectivity (comms), cabin
     temp/humidity (crew quarters).
   - **SCIENCE** — weather (air temp, pressure, wind, waves, current), sea temp,
     sun/almanac.
9. Each Section is **graph-led**: bespoke LCARS-native SVG visualisations
   (compass/radial dials, horizontal/vertical bar meters, sweeping line-history),
   consistent with how the app already hand-rolls SVG viz (no chart library).

### 4. Aesthetic & ambience
10. Authentic **TNG palette** (black `#000`, orange `#FF9900`, peach, mauve, blue,
    amber), **Antonio** webfont **self-hosted** (woff2 in `public/`/Vite assets, OFL
    licensed) — no Google Fonts CDN request, avoiding the external dependency, privacy
    request, CSP, and offline-failure issues. Uppercase, the elbow frame + end-cap
    pills. All styling scoped to the LCARS Panel; zero leakage into admin theme
    (ADR 0001).
11. Ambience (locked): **sound effects** (WebAudio bleeps on interaction, default
    OFF, toggle) — the `AudioContext` is created only **after** the user's explicit
    opt-in gesture (respecting autoplay policy), the preference persists in
    `localStorage`, and the context is reused (not recreated per bleep) and closed on
    unmount. **Stardate + boat clock** (computed stardate + UTC/local). **Animated
    data cascades** (decorative scrolling number columns). **No Red Alert** state.

### 5. Overdrive — "living instrument console" (lead, kept focused)
12. Spring-physics needles on dials/gauges; odometer-roll digits on value change;
    LCARS line-graphs that ease/morph between states; **View Transitions API**
    morph when switching Console Sections.
12a. **UFP splash → MSD** (`LcarsSplash.vue`): on each `/lcars` mount a Federation-seal
    welcome screen (real `public/images/ufp.png`) animates in, then warps out to reveal
    the console. Skippable (click/Esc), reduced-motion aware, plays underneath live data.
12b. **Operable drill-in** (`LcarsDetail.vue`): clicking any single-metric tile
    (line/dial/compass/bar/stat/signal) does a shared-element View-Transition morph into
    a full-screen analysis — big current value, MIN/MAX/AVG/TREND computed from the ring
    buffer, and a history graph whose x-window auto-fits the data's actual span (vs the
    tiles' fixed 6h). Composite tiles (navfix/sunarc/climate/badge) are not drillable.
    (Rejected overdrive directions, prototyped not chosen: living-MSD schematic animation,
    cinematic-depth parallax.)
13. **Progressive enhancement / a11y / perf**: honour `prefers-reduced-motion` with a
    static-but-complete fallback (no springs/morph/cascade/sound); View Transitions
    behind a capability check. Animation is paused both by **section visibility** (a
    Section that isn't foregrounded does not animate) and the **Page Visibility API**
    (background tab pauses all motion); all timers/RAF/audio cleaned up on unmount.
    Keep the live effect set modest (springs + digit roll + one graph morph + cascade)
    so it stays smooth on a mid-range admin laptop. (We are *not* building frame-budget
    instrumentation with auto-degrade — overkill for a hidden egg; reduced-motion +
    visibility-pausing + a modest effect set is the proportionate measure.)

### 6. Responsive
14. **Minimum supported viewport ≥ 1024px wide** (admin laptop). At/above it the elbow
    layout is fully usable; below it, instead of broken overflow, show a tasteful LCARS
    "ACCESS FROM A WIDER DISPLAY" notice. Konami is keyboard-based and LCARS is
    inherently widescreen, so phones are explicitly out of target.

### 7. Testing
15. Feature tests:
    - `GET /lcars`: public → 200 + correct `Inertia` component (`Admin/Lcars`) and
      expected props (initialMetrics shape, reverb config, MSD history present); identity
      degrades when `boat_name`/MMSI empty.
    - `GET /lcars/series`: public → 200; returns independently nullable series with stable
      `status` codes; a forced per-key failure is isolated (other keys still `ok`) and
      leaks no exception detail in the response.
    - Presentation-contract/allow-list **parity test** (each station's backend allow-list
      == its contract metrics classified `historical`/registry-backed).
16. Front-end unit tests:
    - Konami state machine (correct sequence fires; editable-target / `repeat` /
      modifier / wrong-key / timeout-reset all suppress).
    - Live reconciliation: present-non-null updates+appends-on-change; **present-null
      clears + signal-loss**; absent key leaves value; **out-of-order/duplicate
      timestamps rejected**; history↔live merge while loading; ring-buffer evicts by
      6h window + max-point cap; **a metric whose last measured sample is older than 6h
      renders as the "LAST KNOWN" dashed line + readout (not emptied, not drawn as
      measured samples)**; feed-level **staleness transition**; timer/RAF/audio
      **cleanup on unmount**.

### 8. Observability (proportionate)
17. Server-side: log `series`/history query failures and weather-lookup failures
    (without logging sensitive telemetry values). Front-end: surface websocket
    connection state, **feed freshness**, and the **per-reading present-null
    signal-loss** state in the UI (ties to data step 8); no new metrics pipeline.

## Key decisions & tradeoffs
- **Admin-only standalone page**, not a public reskin — isolates the egg so it
  can't break real screens.
- **Konami unlock** over a visible link or bare secret URL — maximal easter-egg
  spirit, zero UI clutter.
- **Data-honest Bridge Stations** over a full Trek roster — Tactical/Medical were
  dropped (no AIS/contacts, no dedicated medical data); every readout is live
  telemetry, no theatre.
- **Lazy per-section history + live tip** — only the MSD landing view's history is
  preloaded; each Bridge Station's history loads on first selection via the `series`
  endpoint (existing `fetchRange`), then the live feed appends the tip. Rejected:
  up-front all-station preload (Octane fan-out / self-DoS), live-only (blank on open),
  and a full range selector (too much for an egg).
- **Living-instrument overdrive** over CRT/boot spectacle — appropriate for a
  data-heavy console; protects readability and reduced-motion users.
- **Deliberate house-style divergence** — see ADR 0001
  (`docs/adr/0001-lcars-easter-egg-house-style-divergence.md`).

## Risks / open questions
- **Octane singleton safety**: `LcarsController` must resolve request/config per
  request (no container/request captured in singletons) — follow existing
  controller patterns.
- **Reverb payload coverage (per-Station)**: must finalize, before building each
  Station, the source + classification of every metric it shows. Known gaps to pin
  down: computed **true wind** is produced in `getAllMetricsAt()` but may be absent
  from the live event payload; **weather/sun** depend on the dispatcher. Each metric
  gets a fixed class: `historical` (series endpoint), `instantaneous`/`categorical`
  (live payload or null), or `identity`. A metric absent from the live payload is
  **not** silently re-sourced from history — it is classified `historical` (and only
  ever drawn as a graph) or shown as null; no metric mixes sources. (Addresses #4.)
- **View Transitions in an Inertia SPA**: station switching is an in-page DOM
  swap, so use the same-document View Transitions API directly; verify it
  composes with Inertia v3 instant visits without fighting them.
- **MMSI as "registry"**: MMSI may be blank in settings; MSD must degrade
  gracefully when identity fields are empty (covered by test, step 15).

## Resolved by Act 2 review (round 1)
- Access boundary clarified (§2 4a) — same as all admin pages; not a new hole.
- Telemetry already public by design (§2 4b) — documented, accepted.
- History fan-out → **lazy per-section, allow-listed, isolated failures** (§2b 5).
- No fabricated samples / dedupe / ordering / ring-buffer cap (§2b 6).
- Metric classification + freshness + presentation contract (§2b 7–9).
- Konami state-machine hardening (§1 2); self-hosted font (§4 10); audio lifecycle
  (§4 11); visibility-pausing + reduced-motion (§5 13); responsive floor (§6 14);
  tests (§7); observability (§8).

## Appendix A — Finalized metric classification matrix
Class: **H** historical (line graph, series endpoint, registry-backed) · **I**
instantaneous (gauge/dial, from live payload or null) · **C** categorical (badge,
live) · **ID** identity/almanac (text). Class is fixed per metric. Each row gives the
**exact source**: a live-payload path `live:<group>.<key>` (from `getAllMetrics()`),
a **registry key** for H-history (`config('scarlet.metrics.registry')`), or a weather
series `scarlet_weather_*`. Build step: confirm each `live:` path is actually emitted by
the `MetricsUpdated` dispatcher; any that isn't is rendered null per §2b6 (class is
unaffected). Any metric without a verified source is **dropped, not faked**.

- **MSD (landing)** — boat_name `ID` (`BoatSetting boat_name`), registry/MMSI `ID`
  (`BoatSetting mmsi`); SOG `H` (key `speed_sog`), house_battery_soc `H`
  (key `house_battery_soc`) — the two preloaded histories; heel `I` (`live:boat.heel`),
  depth `I` (`live:boat.depth`), fuel_level `I` (`live:boat.fuel_level`), water_level `I`
  (`live:boat.water_level`), cabin temps `I` (`live:boat.cabin_temp_*`), position lat/lon
  **`I`** (`live:gps.latitude`/`.longitude`), heading `I` (`live:boat.heading`).
- **CONN** — speed_sog `H` (`speed_sog`), speed_stw `H` (`speed_stw`), vmg `H` (`vmg`),
  heel `H` (`heel`); heading `I` (`live:boat.heading`, compass 0–360 wrap), cog `I`
  (`live:boat.cog`), pitch `I` (`live:boat.pitch`), wind_angle_apparent `I`
  (`live:boat.wind_angle_apparent`, dial).
- **NAVIGATION** — depth `H` (`depth`), trip_log `H` (`trip_log`, monotonic); lat/lon
  **`I`** (`live:gps.latitude`/`.longitude`, text), satellites `I` (`live:gps.satellites`),
  hdop `I` (`live:gps.hdop`), nav_wp_distance `I` (`live:boat.nav_wp_distance`),
  nav_wp_ttg `I` (`live:boat.nav_wp_ttg`), magnetic_variation `I` (`live:boat.magnetic_variation`).
- **ENGINEERING** — house_battery_soc `H` (`house_battery_soc`), house_battery_voltage `H`
  (`house_battery_voltage`), house_battery_current `H` (`house_battery_current`, ±),
  battery_power `H` (`battery_power`, ±), engine_battery_voltage `H`
  (`engine_battery_voltage`), tracker_cpu `H` (`tracker_cpu`); house_battery_time_remaining
  `I` (`live:boat.house_battery_time_remaining`), tracker_heap `I` (`live:tracker.tracker_heap`),
  tracker_uptime `I` (`live:tracker.tracker_uptime`), tracker_mode `C` (`live:tracker.tracker_mode`).
- **OPS** — fuel_level `H` (`fuel_level`), water_level `H` (`water_level`), lte_rssi `H`
  (`tracker_lte_rssi`), wifi_rssi `H` (`tracker_wifi_rssi`), cabin_temp_{forepeak,main,
  quarterberth} `H` + cabin_humidity_{…} `H` (registry `cabin_temp_*`/`cabin_humidity_*`);
  lte_connected `C` (`live:tracker.tracker_lte_connected`), wifi_connected `C`
  (`live:tracker.tracker_wifi_connected`), lte_rat `C` (`live:tracker.tracker_lte_rat`).
  (Stream/broadcast status dropped — not in the live payload; comms covered by LTE/WiFi.)
- **SCIENCE** — water_temp/sea `H` (`water_temp`), air temp `H` (`scarlet_weather_temperature_celsius`),
  pressure `H` (`scarlet_weather_pressure_hpa`); wind speed/dir `I`
  (`live:weather.wind_speed`/`.wind_direction`), wave height/period `I`
  (`live:weather.wave_height`/`.wave_period`), current speed/dir `I`
  (`live:weather.current_speed`/`.current_direction`); sunrise/sunset/twilight `ID`
  (`live:sun.*`, almanac). Weather/sun depend on the dispatcher populating those payload
  groups — confirm at build; absent → null-render per §2b6.

## Out of scope (incl. explicit review rejections)
- Any new telemetry, metric, broadcast event, or DB change.
- Public / non-admin access; mobile-first optimisation.
- Red Alert / threshold alarms; AIS/Tactical; standalone Medical.
- Touch-based unlock (Konami/keyboard only; discovery is intentionally obscure — the
  unlinked URL is NOT claimed as an accessibility accommodation, see §1.2).
- Restyling or sharing tokens with any production surface.
- **Owner-only gating** (review #1): rejected — every admin page is crew-accessible;
  adding a tier only here would be inconsistent. `auth` is the correct boundary.
- **Private telemetry channel** (review #2): rejected — the same data is already
  public via `/dashboard`; a private channel would be security theatre.
- **Frame-budget instrumentation + auto-degrade** (review #19): rejected as overkill
  for a hidden egg; reduced-motion + visibility-pausing + a modest effect set suffice.
