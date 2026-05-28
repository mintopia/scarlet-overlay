---
name: metrics-reference
description: "Reference for all Scarlet boat metrics — sources, PromQL queries, units, ingestion architecture, and query patterns. Activate when working with Prometheus/VictoriaMetrics queries, ship log commands, dashboard metrics, config/scarlet.php metric mappings, or any code that reads sensor data."
metadata:
  author: scarlet
---

# Scarlet Metrics Reference

## When to Apply

Activate this skill when:

- Working with `config/scarlet.php` metric mappings
- Modifying ship log generate/backfill commands
- Building or changing dashboard metric displays
- Writing PromQL queries for any Scarlet data
- Debugging missing or incorrect metric values
- Adding new sensors or data sources

## Ingestion Architecture

All metrics flow into VictoriaMetrics (Prometheus-compatible TSDB) at the URL defined in `config('scarlet.metrics.prometheus_url')`.

### Ingestion Paths

Every metric arrives via **two parallel paths**, creating duplicate time series with different `job` labels:

| Path | `job` label | How it works |
|------|-------------|--------------|
| **OTLP** | `otel-collector` | Tracker/SignalK → OpenTelemetry Collector → VictoriaMetrics |
| **Direct scrape** | `boat-tracker` | VictoriaMetrics scrapes the tracker's Prometheus endpoint |

This means every metric has **at least 2 series**. GPS metrics have up to **5 series** (2 paths x 2 `gps_source` values + 1 `device_mode=saver` variant).

### Critical: Range Query Aggregation

`PrometheusService::queryRange()` only reads `$result[0]` — the first series returned. Without aggregation, it silently drops data from other series.

**Rule: Always wrap range-queried metrics in `max()` to collapse duplicate series into one.** Instant queries (`query()`, `queryMultipleAt()`) are not affected — Prometheus resolves to a single current value.

The `log` and `history` config mappings already use `max()`. The `explore` mapping does not need it because its queries go through `queryRangeWithFallback()` which also reads only `$result[0]` but the explore charts are less sensitive to gaps.

### Position Source: GPS Only

**Use `scarlet_gps_*` exclusively for position.** Do not combine with `scarlet_signalk_navigation_position_*`.

The tracker's `gps_source` label already handles source selection — when SignalK is available, the GPS metric reports SignalK-sourced position (`gps_source="signalk"`). The GPS metric has ~2.75x more data points than the raw SignalK position metric and is a strict superset (SignalK never has timestamps that GPS doesn't).

Combining the two sources causes ~15m position jumps at every transition because they use different precision/timing for the same underlying GPS data.

### Zero Filtering

GPS position metrics can report `0` when the GPS has no fix. Position queries use `!= 0` to filter zeros at the query level. If the value is zero/missing, the query returns empty and PHP null-handling takes over. Additional PHP-side filtering as a safety net:

- MetricsService: `isNullIsland()` check (abs < 0.1)
- GPS service: `GpsService::getLocation()` Null Island check (abs < 0.1)
- Journey import: explicit `abs() < 0.1` filter

## Data Sources

### GPS Tracker (`scarlet_gps_*`)

The boat-tracker device sends GPS data every ~15 seconds. It has a `gps_source` label that is **exclusive per timestamp** — the tracker picks the best available source at each moment:

- `gps_source="signalk"` — GPS position forwarded from SignalK (higher data density)
- `gps_source="onboard"` — Tracker's internal GPS module (fallback when SignalK unavailable)

Do not filter by `gps_source` — trust the tracker's source selection. The label is informational.

| Metric | Unit | Description | Data since |
|--------|------|-------------|------------|
| `scarlet_gps_latitude_deg` | degrees | Latitude (can be 0 when no fix) | 2026-05-20 |
| `scarlet_gps_longitude_deg` | degrees | Longitude (can be 0 when no fix) | 2026-05-20 |
| `scarlet_gps_altitude_meters` | m | Altitude above sea level | 2026-05-20 |
| `scarlet_gps_heading_deg` | degrees | Compass heading (not GPS-derived, always available) | 2026-05-20 |
| `scarlet_gps_speed_kn` | knots | Speed over ground | 2026-05-20 |
| `scarlet_gps_satellites` | count | Visible GPS satellites | 2026-05-20 |
| `scarlet_gps_hdop` | - | Horizontal dilution of precision | 2026-05-20 |

**Notes:**
- GPS is the earliest data source — 2 days before SignalK/MQTT came online.
- `gps_heading` is compass-based, not GPS course — it works even when stationary.
- When GPS has no fix, lat/lon report 0 but heading/speed/satellites continue.

### Signal K Instruments (`scarlet_signalk_*`)

Signal K is the boat's instrument bus, providing navigation, wind, power, and tank data. Published via the tracker which connects to SignalK over the boat's local network.

#### Navigation

| Metric | Unit | Description | Data since |
|--------|------|-------------|------------|
| `scarlet_signalk_navigation_position_latitude` | degrees | SignalK GPS position (used as fallback for ship log) | 2026-05-22 |
| `scarlet_signalk_navigation_position_longitude` | degrees | SignalK GPS position | 2026-05-22 |
| `scarlet_signalk_navigation_headingTrue` | radians | True heading from compass | 2026-05-22 |
| `scarlet_signalk_navigation_courseOverGroundTrue` | radians | COG from GPS | 2026-05-22 |
| `scarlet_signalk_navigation_speedOverGround` | m/s | SOG (multiply by 1.94384 for knots) | 2026-05-22 |
| `scarlet_signalk_navigation_speedThroughWater` | m/s | STW from paddlewheel/transducer | 2026-05-22 |
| `scarlet_signalk_navigation_trip_log` | metres | Cumulative trip log (divide by 1852 for nm) | 2026-05-22 |
| `scarlet_signalk_navigation_attitude_roll` | radians | Heel angle | 2026-05-22 |

#### Waypoint (only present when a waypoint is active in the chart plotter)

| Metric | Unit | Description | Data since |
|--------|------|-------------|------------|
| `scarlet_signalk_navigation_courseGreatCircle_nextPoint_distance` | metres | Distance to next waypoint (divide by 1852 for nm) | 2026-05-23 |
| `scarlet_signalk_navigation_courseGreatCircle_nextPoint_timeToGo` | seconds | ETA to next waypoint | 2026-05-23 |

**Note:** These metrics are **null when no waypoint is active** — this is expected, not a data gap.

#### Wind

| Metric | Unit | Description | Data since |
|--------|------|-------------|------------|
| `scarlet_signalk_environment_wind_speedApparent` | m/s | Apparent wind speed (multiply by 1.94384 for knots) | 2026-05-22 |
| `scarlet_signalk_environment_wind_angleApparent` | radians | Apparent wind angle | 2026-05-22 |

**Note:** True wind is computed in PHP from apparent wind, STW, and heading — there is no direct true wind metric. See `ResolvesShipLogData::calculateTrueWind()`.

#### Power

| Metric | Unit | Description | Data since |
|--------|------|-------------|------------|
| `scarlet_signalk_electrical_batteries_0_voltage` | V | House battery voltage | 2026-05-22 |
| `scarlet_signalk_electrical_batteries_0_current` | A | House battery current (signed: positive=charging) | 2026-05-22 |
| `scarlet_signalk_electrical_batteries_0_capacity_stateOfCharge` | ratio 0-1 | House battery SOC (multiply by 100 for %) | 2026-05-22 |
| `scarlet_signalk_electrical_batteries_1_voltage` | V | Engine/starter battery voltage | 2026-05-22 |

#### Tanks

| Metric | Unit | Description | Data since |
|--------|------|-------------|------------|
| `scarlet_signalk_tanks_fuel_currentLevel` | ratio 0-1 | Diesel tank level (multiply by 100 for %) | 2026-05-23 |

#### Environment

| Metric | Unit | Description | Data since |
|--------|------|-------------|------------|
| `scarlet_signalk_environment_water_temperature` | Kelvin | Sea water temperature (subtract 273.15 for °C) | 2026-05-22 |

### MQTT Zigbee Sensors (`scarlet_mqtt_*`)

Zigbee sensors in the boat's cabins, bridged via Zigbee2MQTT. Each sensor publishes to a specific MQTT topic. The tracker subscribes and re-publishes as Prometheus metrics.

| Metric | Topic filter | Unit | Description | Data since |
|--------|-------------|------|-------------|------------|
| `scarlet_mqtt_temperature` | `{topic="zigbee2mqtt/Forepeak cabin"}` | °C | Forepeak cabin temperature | 2026-05-22 |
| `scarlet_mqtt_humidity` | `{topic="zigbee2mqtt/Forepeak cabin"}` | % | Forepeak cabin humidity | 2026-05-22 |
| `scarlet_mqtt_pressure` | `{topic="zigbee2mqtt/Forepeak cabin"}` | hPa | Barometric pressure (forepeak sensor) | **2026-05-24** |
| `scarlet_mqtt_temperature` | `{topic="zigbee2mqtt/Quarterberth"}` | °C | Quarterberth temperature | 2026-05-22 |
| `scarlet_mqtt_humidity` | `{topic="zigbee2mqtt/Quarterberth"}` | % | Quarterberth humidity | 2026-05-22 |
| `scarlet_mqtt_temperature` | `{topic="zigbee2mqtt/Main Cabin"}` | °C | Main cabin temperature | 2026-05-22 |
| `scarlet_mqtt_humidity` | `{topic="zigbee2mqtt/Main Cabin"}` | % | Main cabin humidity | 2026-05-22 |
| `scarlet_mqtt_percent` | `{topic="watertank"}` | % | Fresh water tank level | 2026-05-22 |

**Note:** Pressure sensor came online 2 days after the other Zigbee sensors. The water tank sensor uses `scarlet_mqtt_percent` not `scarlet_mqtt_water`.

### Tracker System (`scarlet_system_*`)

Internal telemetry from the boat-tracker hardware itself.

| Metric | Unit | Description |
|--------|------|-------------|
| `scarlet_system_battery_voltage_volts` | V | Tracker's own battery |
| `scarlet_system_usb_powered` | 0/1 | Whether tracker has USB power |
| `scarlet_system_lte_connected` | 0/1 | LTE modem connected |
| `scarlet_system_lte_rssi_dBm` | dBm | LTE signal strength |
| `scarlet_system_lte_signal_quality` | 0-100 | LTE signal quality |
| `scarlet_system_wifi_connected` | 0/1 | WiFi connected |
| `scarlet_system_wifi_rssi_dBm` | dBm | WiFi signal strength |
| `scarlet_system_uptime_seconds` | s | Tracker uptime |
| `scarlet_system_cpu_usage_percent` | % | Tracker CPU usage |
| `scarlet_system_free_heap_bytes` | bytes | Free heap memory |
| `scarlet_system_mode` | enum | Operating mode (realtime/saver) |

### Deprecated Metrics

**Never use `scarlet_boat_*` metrics.** These were removed and must not be referenced. Use the specific `scarlet_signalk_*` or `scarlet_gps_*` equivalents.

## Ship Log Field Mapping

The ship log (`ship_logs` table) records hourly snapshots. Config is at `config('scarlet.metrics.mappings.log')`.

| DB Field | PromQL | Source | Fallback | Notes |
|----------|--------|--------|----------|-------|
| `latitude` | `max(scarlet_gps_latitude_deg != 0)` | GPS | none | Zeros filtered in query + PHP |
| `longitude` | `max(scarlet_gps_longitude_deg != 0)` | GPS | none | Zeros filtered in query + PHP |
| `course` | `max(scarlet_gps_heading_deg)` | GPS compass | SignalK COG → SignalK heading | Fallback chain in PHP; COG/heading converted from radians |
| `trip_log` | `max(scarlet_signalk_navigation_trip_log) / 1852` | SignalK | none | Cumulative, in nautical miles |
| `wind_speed` | computed | SignalK | none | True wind calculated from AWS, AWA, STW, heading |
| `wind_direction` | computed | SignalK | none | True wind direction in degrees |
| `pressure` | `max(scarlet_mqtt_pressure{topic="zigbee2mqtt/Forepeak cabin"})` | MQTT Zigbee | none | Barometric, hPa |
| `wp_distance` | `max(scarlet_signalk_...nextPoint_distance) / 1852` | SignalK | none | Null when no active waypoint |
| `wp_ttg` | `max(scarlet_signalk_...nextPoint_timeToGo)` | SignalK | none | Null when no active waypoint |
| `battery_soc` | `max(scarlet_signalk_...stateOfCharge) * 100` | SignalK | none | House battery % |
| `water_level` | `max(scarlet_mqtt_percent{topic="watertank"})` | MQTT Zigbee | none | Fresh water % |
| `fuel_level` | `max(scarlet_signalk_tanks_fuel_currentLevel) * 100` | SignalK | none | Diesel % |

### Generate vs Backfill

Both commands use the same config and the shared `ResolvesShipLogData` trait, but differ in how they query:

| | `ship-log:generate` | `ship-log:backfill` |
|---|---|---|
| **Query method** | `queryMultipleAt()` — pooled instant queries | `queryRange()` — range query per metric |
| **When** | Hourly via scheduler | Manual, with `--from`/`--to` |
| **Duplicates** | Skips if entry exists | Updates existing entries (upsert) |
| **`max()` needed?** | No (instant queries resolve naturally) | Yes (range queries return multiple series) |

## PromQL Patterns

### For instant queries (dashboards, hourly generate)

Use the raw metric name. Instant queries resolve to a single current value:

```
scarlet_gps_latitude_deg
scarlet_signalk_navigation_trip_log / 1852
scarlet_mqtt_pressure{topic="zigbee2mqtt/Forepeak cabin"}
```

### For range queries (backfill, history charts)

Wrap in `max()` to merge duplicate series from the two ingestion paths:

```
max(scarlet_gps_latitude_deg)
max(scarlet_signalk_navigation_trip_log) / 1852
max(scarlet_mqtt_pressure{topic="zigbee2mqtt/Forepeak cabin"})
```

### Unit conversions in PromQL

| Conversion | PromQL pattern |
|------------|---------------|
| SignalK m/s → knots | `metric * 1.94384` |
| SignalK radians → degrees | `metric * 180 / 3.14159265359` |
| SignalK metres → nautical miles | `metric / 1852` |
| SignalK ratio → percentage | `metric * 100` |
| SignalK Kelvin → Celsius | `metric - 273.15` |

### Arithmetic with aggregated metrics

When two metrics are multiplied, aggregate each independently:

```
max(scarlet_signalk_electrical_batteries_0_current) * max(scarlet_signalk_electrical_batteries_0_voltage)
```

Do **not** write `max(a * b)` — this multiplies within each series before aggregating, which may produce incorrect results if the series don't align.

## Data Coverage Notes (as of 2026-05-26)

- **GPS tracker**: Earliest source, data from May 20. Always-on, ~15s reporting interval.
- **SignalK instruments**: Online from May 22. ~69% hourly coverage due to otel-collector outages (3-6h gaps).
- **MQTT Zigbee**: Water tank from May 22, pressure sensor from May 24. ~52-72% coverage.
- **Gaps are from otel-collector outages**, not sensor failures. The boat-tracker direct scrape path fills some gaps but runs at a different interval.
- **Waypoint metrics** (wp_distance, wp_ttg) are only present when a waypoint is active — nulls are normal.
