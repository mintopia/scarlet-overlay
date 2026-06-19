# Phase 5 — Backup, raw-history relabel & canonical backfill

Executed 2026-06-19 against production VictoriaMetrics (`44.30.69.5:8428`) via its HTTP API.
Dataset is small (~900 active / 1,593 historical series, ~7.8M samples), so this was done as a
single pass rather than the day-chunked ledgered tooling the plan envisages for scale.

## 1. Backup
- VM server-side snapshot: **`20260619080047-18BA54CD9CA64F5E`** (`/snapshot/create`).
- Portable export of all `scarlet_*` full history → `storage/app/metrics/backup-<ts>/export.jsonl`
  (+ `manifest.json`). Restore: `POST /api/v1/import --data-binary @export.jsonl` into a disposable VM.

## 2. Raw-history label normalisation (additive then prune)
- Stripped `{otel_scope_name, otel_scope_version, value, key, instance}` from every exported series
  and re-imported (VM merged collapsed identities + dedups timestamps). 1,593 historical series →
  880 normalised identities.
- **Safety assertion before deletion:** for sampled metrics, confirmed the clean (no-scope) identity
  held ≥ the legacy sample count over full history, then deleted the legacy series:
  `POST /api/v1/admin/tsdb/delete_series?match[]={__name__=~"scarlet_.*",otel_scope_name=~".+"}`.
- Result: each `scarlet_*` metric is now a single normalised series spanning full history + live;
  the 2026-06-19 Stage-1 deploy seam is gone. `scarlet_signalk_navigation_datetime` (was 98 series
  from the `value` explosion) collapsed to one.

## 3. Canonical backfill (the 5 baseline metrics)
Built canonical OUTPUT series by **left-looking, staleness-bounded fall-through** stitching of the
baseline source chains (`App\Support\CanonicalBaseline`): at each candidate timestamp use the most
recent sample at-or-before from the highest-priority source within its staleness, else fall through.
Output series carry only `__name__` (resolve to exactly one series).

| Canonical series | samples | resolved-by | range |
|---|---|---|---|
| `scarlet_tank_fuel_level_pct` | 19,090 | mqtt `tanklevel` 13,399 / signalk 5,691 | 19–100 |
| `scarlet_tank_water_fresh_level_pct` | 64,849 | mqtt `watertank` 59,366 / signalk 5,483 | 0–100 |
| `scarlet_power_ecoflow_soc_pct` | 890 | mqtt actSoc | 97.5–100 |
| `scarlet_power_ecoflow_input_w` | 21 | mqtt inputWatts | 0–1135 |
| `scarlet_power_ecoflow_output_w` | 2,315 | mqtt outputWatts | 0–5 |

The fuel mix (mostly MQTT, SignalK only when fresh) confirms the documented fuel-source reality.

## Important follow-ups / current limitations
- **No live canonical writer yet.** This backfill fills history up to ~now; the canonical output
  series will NOT receive new samples until a live writer exists (a collector transform or a
  scheduled incremental `metrics:canonicalise` advancing a high-water mark — plan Phase 5 item 15 /
  Phase 6). Until then they go stale. The app's live reads are unaffected: `CanonicalReader` reads the
  raw chains at query time.
- **No verification ledger / resumable chunking** (plan items 15–16) — acceptable here given size +
  existing backups, but required before relying on the canonical series as the gated read path (Phase 6).
- **EcoFlow canonical series are historical-only** — the unit is offline (only `pdBmsCommErr`/
  `pdIotCommErr` flow); curated fields resume when it returns.
- **Beyond the 5 baseline metrics:** the broader canonical set (navigation/wind/environment/cabin,
  with SI→display transforms) is proposed in `canonical-mapping.md` and needs owner approval before
  backfilling.
