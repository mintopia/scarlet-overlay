# Canonical mapping (inclusion matrix)

> **Model note (2026-06-19):** canonical metrics are **config-driven, not written.** There is no
> canonical *output* series and no canonical writer — the canonical source for a logical metric is
> configured in management (which raw series + transform) and resolved at read time by `CanonicalReader`.
> The "canonical output metric / output name" wording below is therefore a **source-selection reference**
> (what maps to what, with which transform), not a set of series to materialise in VM.

Maps every live raw `scarlet_*` series → disposition, per the Phase-0 inclusion-matrix model.
**This is a PROPOSED matrix for owner (Jess) approval** — only the rows marked **`baseline`** are
already defined in `App\Support\CanonicalBaseline`; the rest are candidates.

Dispositions: **canonicalize** → produce a canonical output metric; **keep-raw** → leave queryable,
not a canonical metric; **drop-from-catalog** → not surfaced (still queryable until Phase-8 prune).

Canonical output names follow `docs/data-layer/naming-convention.md`
(`scarlet_<domain>_<quantity>_<storage-unit>`) and must not collide with raw source names.

Key unit fact (from the 0.154.0 debug dump): **SignalK emits SI** — angles in **radians**,
temperature in **Kelvin**, speeds in **m·s⁻¹**, ratios 0–1 — so most signalk rows need a transform.
GPS is already unit-suffixed at source (`_deg`/`_kn`/`_meters`). MQTT tank `_percent` is already 0–100.

## Tanks (domain `tank`)

| Raw source (priority order) | labels | unit | Canonical key / output | transform | status |
|---|---|---|---|---|---|
| `scarlet_signalk_tanks_fuel_0_currentLevel` → `scarlet_mqtt_percent` | `topic=tanklevel` | ratio → pct | `fuel_level` → `scarlet_tank_fuel_level_pct` | ×100 (signalk only) | **baseline** |
| `scarlet_signalk_tanks_freshWater_0_currentLevel` → `scarlet_mqtt_percent` | `topic=watertank` | ratio → pct | `water_fresh_level` → `scarlet_tank_water_fresh_level_pct` | ×100 (signalk only) | **baseline** |
| `scarlet_mqtt_raw`, `scarlet_mqtt_average` | `topic=tanklevel\|watertank` | raw ADC / pre-avg | — | — | keep-raw (diagnostics; `_average` is pre-smoothed — never re-smooth) |
| `scarlet_signalk_tanks_{fuel,freshWater}_0_capacity` | — | litres | — | — | keep-raw (static capacity) |

## Power (domain `power`)

| Raw source | labels | unit | Canonical key / output | transform | status |
|---|---|---|---|---|---|
| `scarlet_mqtt_value` | `topic=ecoflow/<serial>_BMSHeartBeatReport/actSoc` | pct | `ecoflow_soc` → `scarlet_power_ecoflow_soc_pct` | none | **baseline** |
| `scarlet_mqtt_value` | `topic=.../inputWatts` | W | `ecoflow_input_watts` → `scarlet_power_ecoflow_input_w` | none | **baseline** |
| `scarlet_mqtt_value` | `topic=.../outputWatts` | W | `ecoflow_output_watts` → `scarlet_power_ecoflow_output_w` | none | **baseline** |
| `scarlet_signalk_electrical_batteries_0_capacity_stateOfCharge` | — | ratio → pct | `house_soc` → `scarlet_power_house_soc_pct` | ×100 | proposed |
| `scarlet_signalk_electrical_batteries_0_voltage` | — | V | `house_voltage` → `scarlet_power_house_voltage_v` | none | proposed |
| `scarlet_signalk_electrical_batteries_0_current` | — | A | `house_current` → `scarlet_power_house_current_a` | none | proposed |
| `scarlet_signalk_electrical_batteries_1_voltage` | — | V | `start_voltage` → `scarlet_power_start_voltage_v` | none | proposed |
| other curated EcoFlow fields (`vol`,`amp`,`temp`,`soh`,`cycles`,`remainTime`,`powGet*`…) | `topic=ecoflow/...` | mixed | — | — | keep-raw (curated keep-list; canonicalize later if needed) |
| `scarlet_signalk_electrical_switches_bank_0_*` (`order`/`state`) | — | enum | — | — | keep-raw → drop-from-catalog |

## Navigation (domain `navigation`)

| Raw source | unit | Canonical key / output | transform | status |
|---|---|---|---|---|
| `scarlet_signalk_navigation_position_latitude` | deg | `position_lat` → `scarlet_navigation_position_lat_deg` | none | proposed (preferred over `scarlet_gps_latitude_deg`) |
| `scarlet_signalk_navigation_position_longitude` | deg | `position_lon` → `scarlet_navigation_position_lon_deg` | none | proposed |
| `scarlet_signalk_navigation_speedOverGround` | m/s | `speed_sog` → `scarlet_navigation_speed_sog_kn` | ×1.943844 | proposed |
| `scarlet_signalk_navigation_speedThroughWater` | m/s | `speed_stw` → `scarlet_navigation_speed_stw_kn` | ×1.943844 | proposed |
| `scarlet_signalk_navigation_headingTrue` | rad | `heading_true` → `scarlet_navigation_heading_true_deg` | ×180/π | proposed |
| `scarlet_signalk_navigation_headingMagnetic` | rad | `heading_mag` → `scarlet_navigation_heading_mag_deg` | ×180/π | proposed |
| `scarlet_signalk_navigation_courseOverGroundTrue` | rad | `cog_true` → `scarlet_navigation_cog_true_deg` | ×180/π | proposed |
| `scarlet_signalk_navigation_attitude_{pitch,roll,yaw}` | rad | `attitude_*` → `scarlet_navigation_attitude_*_deg` | ×180/π | proposed |
| `scarlet_signalk_navigation_rateOfTurn` | rad/s | `rate_of_turn` → `scarlet_navigation_rate_of_turn_degs` | ×180/π | proposed |
| `scarlet_signalk_navigation_magneticVariation` | rad | `mag_variation` → `scarlet_navigation_mag_variation_deg` | ×180/π | proposed |
| `scarlet_gps_*` (`latitude_deg`,`longitude_deg`,`speed_kn`,`heading_deg`,`hdop`,`satellites`,`altitude_meters`) | deg/kn/m | — | — | keep-raw (already unit-correct; GPS fallback source for position/speed) |
| `scarlet_signalk_navigation_gnss_*` (dilutions, satellites, antennaAltitude, geoidalSeparation) | mixed | — | — | keep-raw → drop-from-catalog (GNSS diagnostics) |
| `scarlet_signalk_navigation_course_calcValues_velocityMadeGood` | m/s | `vmg` (reads this series) | ×1.943844 → kn | **baseline** (was mapped to non-existent `courseGreatCircle_*`; repointed 2026-06-19) |
| `scarlet_signalk_navigation_course_calcValues_crossTrackError` | m | `xte` (reads this series) | none (m) | **baseline**; bounds ±185200 m (±100 nm) — repointed 2026-06-19 |
| `scarlet_signalk_navigation_course_calcValues_bearingTrue` | rad | `bearing_to_wp_true` (reads this series) | ×180/π → deg | **baseline**; repointed 2026-06-19 |
| `scarlet_signalk_navigation_course_calcValues_bearingTrackTrue` | rad | `track_bearing_true` (reads this series) | ×180/π → deg | **baseline**; repointed 2026-06-19 |
| `scarlet_signalk_navigation_course_calcValues_distance` | m | `wp_distance` (reads this series) | m → nm | **baseline**; bounds 0–1000 nm — repointed 2026-06-19 |
| `scarlet_signalk_navigation_course_calcValues_timeToGo` | s | `wp_ttg` (reads this series) | s | **baseline**; bounds 0–14 d — repointed 2026-06-19 |
| `scarlet_signalk_navigation_{datetime,log,trip_log,course*startTime,*Point*type}` | str/m/ts | — | — | drop-from-catalog (string/enum/odometer) |
| `scarlet_signalk_performance_velocityMadeGoodToWaypoint` | m/s | `vmg_waypoint` (reads this series) | ×1.943844 → kn | proposed |

> **Waypoint/course keys repointed (2026-06-19).** The 6 navigation waypoint keys — `vmg`, `xte`,
> `bearing_to_wp_true`, `track_bearing_true`, `wp_distance`, `wp_ttg` — were baselined against
> non-existent `scarlet_signalk_navigation_courseGreatCircle_*` series. The live audit found the real
> resolved-course cluster is `scarlet_signalk_navigation_course_calcValues_*`, so they were repointed there
> (rows above). **Validity bounds** were added so these keys read null/empty instead of serving SignalK's
> "no active route" sentinels (xte ≈ −3.79 M m, wp_distance ≈ 2642 nm): `xte` ±100 nm (±185200 m),
> `wp_distance` 0–1000 nm, `wp_ttg` 0–14 d. Bounds are applied by `CanonicalReader` in both the fresh and
> stale passes (read contract, Phase 3 item 10a).
>
> **Null Island filter.** A per-metric `reject_null_island` option (configurable in the management UI)
> discards lat/long readings of ≈ 0,0 (`abs < 0.1`, the app-wide no-GPS-fix convention) so a position
> metric reads null instead of (0,0). Off by default; no baseline key enables it yet — intended for any
> lat/long series mapped via the UI. Caveat: at single-series granularity this also rejects legitimate
> positions within ~0.1° of the prime meridian / equator, so enable per coordinate with that in mind.

## Wind & Environment (domains `wind`, `environment`)

| Raw source | unit | Canonical key / output | transform | status |
|---|---|---|---|---|
| `scarlet_signalk_environment_wind_angleApparent` | rad | `awa` → `scarlet_wind_apparent_angle_deg` | ×180/π | proposed |
| `scarlet_signalk_environment_wind_speedApparent` | m/s | `aws` → `scarlet_wind_apparent_speed_kn` | ×1.943844 | proposed |
| `scarlet_signalk_environment_water_temperature` | **K** | `water_temp` → `scarlet_environment_water_temp_c` | −273.15 | proposed |
| `scarlet_signalk_environment_depth_belowSurface` | m | `depth_surface` → `scarlet_environment_depth_surface_m` | none | proposed |
| `scarlet_signalk_environment_depth_belowTransducer` | m | `depth_transducer` → `scarlet_environment_depth_transducer_m` | none | proposed |
| `scarlet_signalk_environment_current_drift` | m/s | `current_drift` → `scarlet_environment_current_drift_kn` | ×1.943844 | proposed |
| `scarlet_signalk_environment_current_setTrue` | rad | `current_set` → `scarlet_environment_current_set_deg` | ×180/π | proposed |

## Cabin sensors (domain `cabin`) — zigbee2mqtt, 3 rooms

Rooms (via `topic` label): `zigbee2mqtt/Forepeak cabin`, `zigbee2mqtt/Main Cabin`, `zigbee2mqtt/Quarterberth`.
Canonical reads resolve to exactly one series, so each canonical cabin metric needs a **`cabin`
identity label** (proposed: derive `cabin=forepeak|main|quarterberth` from `topic`), or keep per-room raw.

| Raw source | unit | Canonical (proposed) | transform | status |
|---|---|---|---|---|
| `scarlet_mqtt_temperature` | °C | `scarlet_cabin_temp_c{cabin}` | none | proposed |
| `scarlet_mqtt_humidity` | %RH | `scarlet_cabin_humidity_pct{cabin}` | none | proposed |
| `scarlet_mqtt_pressure` (Forepeak only) | hPa | `scarlet_cabin_pressure_hpa{cabin}` | none | proposed |
| `scarlet_mqtt_battery` | pct | — | — | keep-raw (sensor health) |
| `scarlet_mqtt_voltage` | V | — | — | keep-raw (sensor health) |
| `scarlet_mqtt_linkquality`, `scarlet_mqtt_last_seen`, `scarlet_mqtt_power_outage_count` | — | — | — | keep-raw → drop-from-catalog (device telemetry) |

## Steering / Notifications

| Raw source | disposition |
|---|---|
| `scarlet_signalk_steering_autopilot_{state,hullType}` | keep-raw (string enums; surface as status, not a numeric canonical) |
| `scarlet_signalk_notifications_*` (AISConnectionLost, NoFix, server.newVersion, status flags) | drop-from-catalog (alert/notification state, not telemetry) |

## Open decisions for owner

1. Approve which `proposed` rows become canonical (esp. the SI→display transforms).
2. Position/speed: prefer **SignalK** canonical with **GPS** (`scarlet_gps_*`) as fallback source? (matches the tank/water pattern.)
3. Cabin: model a `cabin` identity label (derive from `topic`) vs per-room canonical keys.
4. Confirm transform constants: knots `×1.943844`, rad→deg `×57.29578`, K→°C `−273.15`.
