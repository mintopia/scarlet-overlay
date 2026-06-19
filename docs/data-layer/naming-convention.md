# Canonical metric naming convention

> **Model note (2026-06-19):** **no canonical output series are physically written.** Under the current
> read-time model there is no canonical writer — `CanonicalReader` resolves each logical metric from its
> configured raw VM source at read time. This convention therefore describes **logical catalog keys only**
> (the identity + storage-unit semantics behind each catalog entry); it does **not** describe series
> materialised in VictoriaMetrics.

Format: `scarlet_<domain>_<quantity>_<storage-unit>`

- `<domain>`: navigation | wind | power | tank | environment | cabin | tracker | ecoflow | stream
- `<quantity>`: the physical quantity (e.g. `fuel_level`, `house_battery_soc`, `speed_sog`)
- `<storage-unit>`: the STORED unit, encoded so it can never silently change behind a key:
  `pct` (0–100), `ratio` (0–1), `kn`, `deg`, `rad`, `m`, `nm`, `c`, `k`, `hpa`, `v`, `a`, `w`, `s`.

Rules:
- The storage unit (and thus meaning) behind an existing key is **immutable**. A new unit = a new key.
- Canonical output names MUST NOT collide with raw source names (`scarlet_signalk_*`, `scarlet_mqtt_*`, `scarlet_gps_*`).
- `display_unit` (UI) is separate from `storage-unit` and may differ via an approved conversion.

Examples: `scarlet_tank_fuel_level_pct`, `scarlet_tank_water_fresh_level_pct`, `scarlet_power_house_soc_pct`.
