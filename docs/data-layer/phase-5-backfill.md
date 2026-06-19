# Phase 5 — Backup & historical label normalisation

Executed 2026-06-19 against production VictoriaMetrics (`44.30.69.5:8428`) via its HTTP API.
Small dataset (~900 active series, ~7.8M samples), so done as direct passes rather than the
day-chunked ledgered tooling the plan envisages for scale. Existing backups are the safety net.

## Backup
- VM server-side snapshot: **`20260619080047-18BA54CD9CA64F5E`** (`/snapshot/create`).
- Portable export of all `scarlet_*` full history → `storage/app/metrics/backup-*/export.jsonl`.
  Restore: `POST /api/v1/import --data-binary @export.jsonl`.

## Final normalised label model
Every `scarlet_*` series now carries only: `device_id`, `job=boat-tracker`, `service_name=boat-tracker`,
plus `topic` (mqtt) and `gps_source` (gps lat/lon). **Removed** across all history *and* at the collector
going forward: `otel_scope_name`/`otel_scope_version`, the changing `value`/`key`/`instance` datapoint
attrs, `exported_job`, and `device_mode`.

Done as additive re-import (drop/normalise labels → import; VM merges collapsed identities) followed by a
safety-asserted delete of the superseded series:
- Stripped `otel_scope_*` + `value`/`key`/`instance`; deleted legacy scope series.
- Normalised historical ingestion drift: dropped `exported_job`, forced `job`/`service_name` to
  `boat-tracker` (older data had `job=otel-collector`+`exported_job` from a scrape era, or missing labels).
- Removed `device_mode` (tracked as its own metric). **Collector pairing:** a `resource/strip` processor
  now deletes the `device.mode` resource attribute on new data (on `develop`), so the VM delete sticks
  instead of being re-polluted by live writes. Verified: post-deploy the labelled series froze while
  `device_mode`-free series stayed live.

## GPS lat/lon collation
`scarlet_gps_latitude_deg` / `_longitude_deg` historically split into `gps_source=signalk` vs `onboard`
(plus the onboard offline/saver track). Per owner decision, **both source series are made the same
historical track**: SignalK is priority, onboard fills the gaps (left-looking; a gap = no SignalK sample
within ~60s). The unified track (~68k samples) is written under **both** `gps_source` labels.
**Going forward they are separate** (expected): SignalK feeds the main position/track; onboard is for its
own map (position even when boat systems are offline) and appends to the onboard series whose history is
the shared unified track. Other GPS metrics (speed/heading/hdop/satellites/altitude) have no `gps_source`
and were just `device_mode`-stripped.

## Canonical metrics — model decision
**No canonical *output* series / no canonical writer.** The 5 canonical output series briefly backfilled
(`scarlet_tank_fuel_level_pct`, …) were **removed**. Instead, the canonical source for a logical metric is
**configured in management** (which raw series is the source) and resolved at read time by `CanonicalReader`
over the structured source chain. This supersedes the "canonical output series" idea in earlier drafts;
`canonical-mapping.md` now serves as the source-selection reference, not a set of series to write.

## Notes / limitations
- No verification ledger / resumable chunking (plan items 15–16) — acceptable at this size given backups.
- EcoFlow remains offline (only `pdBmsCommErr`/`pdIotCommErr` flow); curated fields resume when it returns.
