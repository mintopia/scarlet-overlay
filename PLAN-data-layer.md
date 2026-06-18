# Plan: Definitive Data Layer — Canonical Metric Catalog, Normalisation & Configurable Mapping
_Locked via grill-with-docs — by Claude + Jess. Terms per CONTEXT.md; architecture per ADR 0002._
_Revised after Codex Rounds 1–4 — APPROVED (see PLAN-REVIEW-LOG-data-layer.md)._

## Goal

Replace Scarlet's drift-prone, code-only metric registry with a **DB-backed, admin-editable Canonical
Catalog** that defines, for every meaningful boat metric, a single logical identity, its unit, its
ordered **Source Priority Chain** (definitive source + fallbacks), its freshness policy (last value +
age + per-metric/per-source staleness threshold), and — for volatile sensors — a median **Trend Value**.
Normalise existing history into clean canonical series via **fix-forward + additive backfill**
(non-destructive, restore-verified backups, legacy pruned only after verification), and cut the overlay,
public dashboard, ship log, and explore over to read from the catalog **incrementally behind an adapter**.
The four Audience Dashboards and their in-browser visual designer are an **explicitly deferred follow-on**.

## Scope (locked)

**In:** parts 1–4 — definitive sources, current-data inventory, label normalisation, configurable mapping
+ value/age/trend read contract, and migrating existing consumers.
**Out (this plan):** part 5 — the visual dashboard designer and the Main/Skipper/Ops/Tech Audience
Dashboards. They consume the catalog later; they do not shape it now beyond their known metric needs.

## Cross-cutting safety principles (apply to every phase)

- **No free-form PromQL is ever stored or executed from the catalog.** Sources are **structured
  descriptors** compiled to PromQL server-side (model below). Transforms are enumerated typed operations.
- **Canonical reads resolve to exactly one series**, declaring expected identity labels; the reader fails
  loudly on ≠1 series. The implicit `result[0]`/`max()` papering of the legacy registry is not carried in.
- **Catalog is versioned via an external key** (Redis/DB), not in-process state: the cached repository and
  every Octane worker resolve by that key; long-running jobs (ship-log generate/backfill, explore, backfill)
  **pin** a version at start and record it in output; the 15s push payload carries the version so clients
  ignore mixed-version frames until reload.
- **Backups are restore-verified** into a disposable VM with checksum/sample-count comparison before they
  count.
- **Rollback-to-previous-catalog-version** is a first-class admin + CLI action, distinct from
  reset-to-baseline.

## Source-descriptor & stitching model (normative)

- **Two descriptor roles:** (a) **reconstruction descriptors** — the raw Source Priority Chains, used by
  backfill, fix-forward, and diagnostics/provenance; (b) **runtime read** — after a range is verified,
  consumers read the **single canonical output series**, NOT the raw chains. Raw chains are retained for
  rebuilds/forensics but are not the post-cutover runtime path (this is what makes prune safe). Exactly
  **one canonical writer per canonical metric** (collector transform *or* reader/producer, never both),
  enforced in the inclusion matrix.
- **Descriptor fields:** `source_metric_name` (from the **source allowlist** = full inventory including
  historically-active ranges, not only currently-live series);
  `label_matchers` (typed operators — at minimum `equals` and `absent` — validated against live label sets);
  `source_class` ∈ {`live`,`historical`,`both`}; `source_kind` (semantic tag, e.g. raw-ADC / percent /
  ratio / pre-averaged); `select_fn` ∈ {`last`,`min`,`max`} (**selection only — smoothing functions are
  rejected here** to avoid double-smoothing an already-averaged source like MQTT `_average`); typed
  `unit_transform`; optional **per-source `staleness_threshold_s`** (defaults to the metric's); `priority`.
- **Canonical output names** are a **separate allowlist** generated from the inclusion matrix (they are not
  live yet). Keys encode storage semantics (`_ratio` vs `_percent`, etc.) and the storage meaning behind a
  key is immutable.
- **Source equivalence:** a chain may only mix sources proven equivalent after their `unit_transform`
  (so `_raw` ADC and `_percent` can coexist only via a verified transform).
- **Trend** (volatility/median) is a **read-layer (data-layer)** function applied by the reader — part of
  the data read contract used by overlays/ship-log/dashboards, distinct from presentation config (colours/
  layout) and kept strictly separate from `select_fn`.
- **Stitching (backfill + read):** **left-looking only** — at each grid timestamp use the most recent
  sample *at or before* it from the highest-priority source **within that source's staleness threshold**;
  otherwise fall to the next priority. Never look forward; never hold a stale preferred source over a live
  fallback (the fuel-empty-2026-06-17 case).
- **Preferred-source health** is judged by **recent coverage ratio over the relevant operating window**,
  not a single latest-non-empty sample.

## Approach

### Phase 0 — Inventory, naming, decisions & safety net (read-only first)
1. **Full live inventory** of every `scarlet_*` series: name, exact label set, cadence, last value/ts,
   active date ranges → `metrics-inventory.json` + a drift report (config vs reality).
2. **Inclusion matrix (owner-approved):** per live metric, `keep-raw | canonicalize | drop-from-catalog`
   with its canonical key. (`drop-from-catalog` ≠ `delete-from-storage`; dropped raw stays queryable until
   prune gates pass.)
3. **Canonical naming convention decided now:** `scarlet_<domain>_<quantity>_<storage-unit>`, non-colliding,
   storage-semantics-encoded; feeds the canonical-output allowlist.
4. **Restore-verified backups:** VM snapshot + export selected by **inventory matchers** (not a hardcoded
   prefix) + test restore into a disposable VM with checksum/sample-count comparison. Hard gate before any
   write/prune.

### Phase 1 — Quick-win adapter (de-risk, immediate value)
5. **Corrected mappings + freshness behind an adapter**, shipped before DB/VM work: verified chains
   (fuel: SignalK `scarlet_signalk_tanks_fuel_0_currentLevel` → MQTT `scarlet_mqtt_percent{topic="tanklevel"}`;
   water: SignalK `scarlet_signalk_tanks_freshWater_0_currentLevel` → MQTT
   `scarlet_mqtt_percent{topic="watertank"}` — exact names + labels, no shorthand), preferred-source health
   by coverage ratio.
   Fixes today's blank fuel and proves the read contract.

### Phase 2 — Canonical Catalog (DB + baseline)
6. **Schema + constraints:** `canonical_metrics` (key UNIQUE, label, group, storage_unit + display_unit,
   volatile, trend_window_s, staleness_threshold_s, enabled, baseline_version) and
   `canonical_metric_sources` (structured descriptor columns + typed transform + source_class + per-source
   staleness). Portable DB constraints (unique key, unique `(metric_id,priority)`) **plus transactional
   app-level validation** for "≥1 enabled source per enabled metric", positive thresholds, bounded windows.
   Full audit trail (actor, before/after diff, validation result, rollback target).
7. **Curated EcoFlow set** with **device serial modeled as a typed source parameter** (controlled
   replacement workflow), exact `topic` matchers per field; per-cell BMS stays raw.
8. **Octane-safe externally-versioned repository** + versioned baselines (reset shows diff/preview,
   validates, rolls back to previous version).
9. **Dedicated catalog-mutation authorization** distinct from generic admin; every mutation audited.

### Phase 3 — Read contract (value + age + trend)
10. **`CanonicalMetricReader`** → `value` (Trend Value if volatile else last), `raw`, `display_unit`,
    `timestamp`/`age_seconds` from the **most recent real raw sample** (not the median window), `stale`,
    `resolved_source`, `mixed_source` when a trend window spans a switchover. Volatile = `median_over_time`
    over `trend_window_s` (default 600s). **Post-cutover the reader reads the canonical output series and
    is gated by verified-ledger coverage for the requested range** — unverified canonical chunks are never
    served (no partially-repaired range leaks into dashboards or ship-log generation); pre-verification it
    falls back to the reconstruction chains.
11. **Broadcast + UI:** push carries value+age+stale+resolved_source+catalog_version.

### Phase 4 — Fix-forward ingestion
12. **Prove the path first:** confirm all relevant MQTT/SignalK telemetry actually transits the **central
    in-repo collector** (not a direct remote-write/bypass). Where a source bypasses it, canonicalize at the
    reader/producer instead. Then **pin otelcol-contrib**, add CI config-validation, and **audit the
    existing `filter/dedup` `device_id==nil` drop** so canonical-eligible sources aren't discarded.
13. **Canonicalising transforms** (per exact `topic`) with **label allow/drop rules**, a **cardinality
    budget** check, and an audit of `resource_to_telemetry_conversion` blow-up before enabling.

### Phase 5 — Additive backfill & verification
14. **`metrics:canonicalise --from --to`:** day-chunked with explicit sampling grid, inclusive/exclusive
    boundaries, lookback, midnight-edge tests; **left-looking staleness-bounded fall-through** stitching.
15. **Ledgered, resumable, transactional-by-convention:** each chunk advances a ledger
    (`pending_delete → deleted → imported → verified`) with automatic resume/repair after a crash (a crash
    between delete and import is a recoverable hole, not silent corruption); reruns refuse without
    `--replace`. **High-water mark = exclusive timestamp with safety lag > max source staleness + collector
    batch delay**, so backfill never overlaps live writes.
16. **Verification gate:** compare canonical against the **resolved source per timestamp** (not "match all
    history"); report **source coverage** separately; detect duplicate timestamps **both** from the import
    source/ledger **before write** and via a VM-side sanity query **after write**, before marking `verified`.

### Phase 6 — Consumer cutover
17. **Adapter-first, feature-flagged, one consumer at a time** (MetricRegistry/MetricsService, overlay,
    public + admin dashboards, explore, ship-log) — small blast radius.
18. **Ship-log policy:** rows annotated with `catalog_version` + `resolved_source`; immutable vs
    regenerated vs back-annotated decided explicitly.
19. **Post-cutover label hygiene:** canonical series carry only approved labels — no raw `topic`,
    `device_id`, `instance`, or resource labels unless explicitly allowed.

### Phase 7 — Settings UI
19. Mapping **visible** (metric, units, structured source chain, last value, age, freshness, **drift
    state**) and **configurable** via structured descriptors (no PromQL box). Save validation: returns
    exactly one series with the declared identity labels; **freshness checked only for `live`-capable
    sources** (so historical-only fallbacks save); **`display_unit` validated for compatibility with
    `storage_unit`** (incompatible changes require an explicit approved conversion, so percent data can't be
    shown as litres/volts); bounded window/cardinality. Reset-to-baseline with diff;
    **rollback-to-previous-version**; force-client-reload.
20. **Drift state defined:** declared selector vs live inventory mismatch **plus** freshness + coverage
    deltas, computed by a scheduled report; **scheduled cardinality-drift checks** catch a selector that
    returns one series today but multiple tomorrow.

### Phase 8 — Prune legacy (destructive; heavily gated)
22. **`metrics:prune-legacy` is destructive deletion** (recoverable only from backup). Requires:
    restore-verified backups, per-series **consumer inventory**, quarantine/retention period, GPS
    **historical-vs-current split with active-series proof**, reviewed `match[]` selectors, maintenance
    window. `delete-from-storage` is the only step that removes data; `drop-from-catalog` never does.

### Cross-cutting — Data-layer observability
23. First-class internal metrics/events: catalog version served, reader resolution + fallback rates, query
    failures, stale counts, backfill chunk progress, **duplicate-sample detection**, verification failures,
    catalog edits, prune deletes.

## Key decisions & tradeoffs (grill + Codex Rounds 1–2)

- **DB catalog, not config** — kills silent drift; mitigated by structured (non-PromQL) descriptors,
  validation, audit, external versioning, dedicated authz, rollback. (ADR 0002)
- **Structured source descriptors with live/historical class + per-source staleness + left-looking
  stitching** — secure, and correct across the real fuel/water SignalK↔MQTT switchovers.
- **Selection vs presentation functions strictly separated** — no double-smoothing of pre-averaged sources.
- **Ledgered, high-water-marked additive backfill; restore-verified backups; destructive prune last.**
- **Median (10-min, per-metric) volatile value; age/timestamp from the latest real raw sample.**
- **Adapter + incremental feature-flagged cutover**; quick-win mappings ship first.
- **Data catalog → DB now; presentation config stays in code.**
- **EcoFlow already arriving** — curation of input/output + status, not acquisition.

## Risks / open questions

- **VM import throughput / cardinality** across ~95 metrics + `resource_to_telemetry_conversion` label
  blow-up — chunk by day, enforce cardinality budget.
- **Stitching correctness across switchovers** — tested against fuel-on-`tanklevel`-2026-06-17 + midnight edges.
- **Structured-descriptor expressiveness** must cover every real source with no free-form escape hatch; a
  gap is a model fix, not a reason to add raw PromQL.
- **Some telemetry may bypass the central collector** — must be proven in Phase 4 before relying on
  collector transforms; bypassing paths canonicalize at reader/producer.
- **otelcol-contrib image pin** changes the deploy image — validate in CI first.
- **SignalK tank export broken upstream** (out-of-repo Pi/SignalK) — fallback chain insulates us; tracked separately.

## Out of scope

- Audience Dashboards + the in-browser visual designer (part 5).
- Per-cell EcoFlow BMS internals, switch-bank states, long-tail raw series (kept raw).
- On-boat Pi/SignalK export firmware fix (out-of-repo).
- Per-user dashboard customisation / layout persistence.
