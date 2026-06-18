# Phase 1 enablement — canonical reader

The `CanonicalReader` resolves `fuel_level` and `water_fresh_level` from an ordered, staleness-bounded
source chain (SignalK preferred → MQTT fallback). It is OFF by default.

## Enable
1. Set `CANONICAL_READER_ENABLED=true`.
2. `php artisan config:clear` (and restart Octane workers).
3. Verify on the admin dashboard that fuel reads from MQTT `tanklevel` when SignalK is absent.

## Behaviour
- Prefers the first source that is fresh (`age <= staleness`) AND healthy (coverage ratio over the window
  ≥ `coverage_min`). Otherwise falls through; if nothing is fresh, shows the last-known value flagged stale.
- Volatile metrics (fuel/water) display a 10-minute median; `age`/`timestamp` come from the latest raw sample.
- This is the seed for the Phase 2 DB catalog; the config shape mirrors the planned schema.
