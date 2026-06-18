# 2. Canonical Metric Catalog (DB-backed) and fix-forward additive backfill

Date: 2026-06-18

## Status

Accepted

## Context

Scarlet's telemetry has suffered persistent data-quality failures (see [[canonical-metric]],
[[definitive-source]] in CONTEXT.md): duplicate series across ingestion paths, label-value drift,
metric renames/retypes across firmware versions, and stale/out-of-window query results. The mapping
from sensor data to display lives entirely in `config/scarlet.php` — a large hand-edited PHP registry
that has **silently drifted out of sync with reality**. Confirmed live examples on production
VictoriaMetrics (2026-06-18):

- Config queries `scarlet_signalk_tanks_fuel_currentLevel`; the live name is
  `scarlet_signalk_tanks_fuel_0_currentLevel` (a `_0_` rename) — so the fuel mapping resolves to nothing.
- Fuel data on a day at sea (2026-06-17) actually arrived via MQTT `tanklevel`, not SignalK at all.
- The same physical reading is published under `scarlet_mqtt_{raw,percent,average}` distinguished only
  by `topic` labels; `watertank` (fresh water) and `tanklevel` (fuel) are two different tanks.
- EcoFlow Delta data is already flowing as hundreds of `ecoflow/<serial>_<report>/<field>` MQTT topics.

The recurring failure mode is that only a developer can see or fix the mapping, and they cannot tell it
has drifted until a dashboard goes blank.

## Decision

Two coupled decisions.

**1. The Canonical Catalog is the DB-backed, editable source of truth for the data layer.**
A Canonical Metric (logical identity + unit + Source Priority Chain + transform + volatility/trend +
Staleness Threshold + display label) is stored in the database, seeded from a code baseline, and edited
through the admin Settings UI with "test query returns data" validation on save and reset-to-baseline.
It replaces the `config/scarlet.php` registry for source resolution. Presentation config (colours, chart
types, grouping, layout) stays in code — that is the deferred Audience Dashboard designer's concern (see
[[data-catalog-vs-presentation-config]]).

**2. Normalisation is Fix-Forward + Additive Backfill, never destructive in place.**
Correct ingestion (central in-repo otel-collector transforms) so new data lands canonical; additively
rebuild clean canonical history alongside the originals; verify; back up; only then prune legacy series.

## Consequences

- **Drift becomes visible and fixable without a deploy.** The admin can see every metric's source, unit,
  last value, and age, and repoint a drifted source chain in seconds. This is the primary win.
- **Source-of-truth moves out of version control.** Catalog edits lose code review and PR history; we
  mitigate with validation, an audit trail (updated_by/at + change log), and the code baseline as a
  recoverable reset. This is the deliberate trade-off accepted for live editability.
- **Octane caching** means the catalog must be loaded via an invalidatable cached repository, busted on
  save, with a client reload — never read per-request from config at boot.
- **Additive backfill is reversible** until the prune step; the prune is gated behind a VM snapshot plus a
  durable per-series export, and runs only after canonical data is verified against source within tolerance.
- **Bounded scope.** All non-EcoFlow physical metrics are canonicalised; EcoFlow is limited to the curated
  input/output + status set (see [[ecoflow-curated-set]]). The in-browser dashboard designer and the four
  Audience Dashboards are an explicitly separate follow-on program.
- **A Source Priority Chain, not a single query, defines "definitive."** Backfill must stitch history
  across whichever source was live at each timestamp (SignalK where present, MQTT fallback otherwise).
