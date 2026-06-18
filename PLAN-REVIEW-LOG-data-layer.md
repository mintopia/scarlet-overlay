# Plan Review Log: Definitive Data Layer
Act 1 (grill-with-docs) complete — plan locked (PLAN-data-layer.md), CONTEXT.md updated with Data Layer
terms, ADR 0002 created. MAX_ROUNDS=5.

Grill resolved: scope (parts 1–4, designer deferred) · root causes (dup series + drift + rename + stale,
all confirmed live) · normalisation = fix-forward + additive backfill + backup + later prune · catalog
scope (all non-EcoFlow + curated EcoFlow) · DB-backed editable+validated catalog · median 10-min trend
(empirically chosen from 2026-06-17 at-sea data) · fuel/water Source Priority Chains validated on real
data · data-catalog→DB / presentation→code boundary.

## Round 1 — Codex
Codex sandbox could not read files (bwrap blocked in this workspace); re-ran in same thread with files embedded inline. Returned 35 findings, VERDICT: REVISE. Highlights:
- Admin-editable **free-form PromQL** is a security/availability/cardinality hole (#8,#9,#27) → store STRUCTURED, allowlisted source descriptors compiled to PromQL; enumerated transform types.
- Naive `keep_last_value` stitching carries STALE preferred-source across outages, suppressing valid fallback (#5) — breaks the exact fuel-empty-2026-06-17 case → staleness-bounded fall-through per timestamp.
- "Idempotent import" not credible (#13,#14) → per-range delete+reimport / immutable chunk ledger + backfill high-water-mark vs live writes.
- Octane caching unspecified + push/edit races (#11,#12) → versioned, version-keyed/scoped repo, cross-worker bust, catalog-version in push payload.
- Verification "match all history" falsely fails on correct fallback (#7) → compare canonical vs RESOLVED source per timestamp + report source coverage.
- Canonical naming convention is a hard dependency, not a deferred open question (#17) → decide in Phase 0.
- Reduce blast radius (#32,#33) → registry-key adapter + feature-flagged incremental cutover; ship corrected fuel/water mappings + freshness as an early quick-win.
- Backups must be restore-verified into a disposable VM w/ checksums (#24); prune is **destructive deletion** recoverable only from backup, needs retention/quarantine + consumer inventory (#15,#35).
- Also accepted: collector contrib-image pin + config validation + device_id drop audit (#1,#23); MQTT per-topic exact match (#2,#3); reader rejects multi-series, drop implicit result[0]/max in canonical paths (#20,#21); trend value uses median but timestamp/age from latest real raw sample, expose resolved_source/mixed_source (#18,#19); DB constraints + unit storage-vs-display split (#25,#26); cardinality budget + resource_to_telemetry_conversion audit (#22); ship_logs versioning policy (#28); precise drift-state algorithm (#29); data-layer self-observability (#30); explicit keep/canonicalize/drop inclusion matrix (#34); versioned baselines w/ diff before reset (#31); GPS historical-vs-current split + active-series proof before prune (#4); stitching grid/boundary/overlap spec + tests (#6).

### Claude's response
Accepted essentially all findings (they are correct and improve safety). Revised PLAN-data-layer.md: structured source descriptors replace free-form PromQL throughout; staleness-bounded fall-through stitching; transactional/ledgered backfill + high-water mark; versioned Octane-safe catalog + push payload version; restore-verified backups; honest destructive-prune framing + retention; naming convention + inclusion matrix promoted to Phase 0; added early quick-win adapter phase + feature-flagged incremental cutover; per-timestamp resolved-source verification; data-layer observability; ship_log catalog-versioning; precise drift algorithm. No findings rejected.

## Round 2 — Codex
"Resolves most Round 1 issues" — 22 finer findings, VERDICT: REVISE. All accepted (spec-level, no fundamental breakage). Key ones folded in:
- Separate allowlists: source metric names (live) vs planned canonical output names (not yet live) (#1).
- Matcher operators equals/absent, validated vs live label sets; descriptors declare expected identity labels + scheduled cardinality-drift checks (#2,#4).
- Separate SOURCE-selection function from PRESENTATION trend function; reject smoothing in source descriptors — avoids double-smoothing the Pi's already-averaged MQTT `_average` (#3).
- Preferred-source validated by recent COVERAGE RATIO over operating window, not latest non-empty (the fuel-empty case) (#5).
- Stitching = LEFT-LOOKING ONLY (most recent sample at/before grid ts within threshold; no look-forward) (#6); per-source staleness threshold defaulting to metric (#7); source kind + storage-unit transform + source-equivalence check for `_raw`/`_percent`/`_average` (#8).
- Backfill ledger with explicit resumable states (pending_delete/deleted/imported/verified); ONE required strategy not an "or"; duplicate-timestamp count per chunk before verified; high-water mark = exclusive ts with safety lag > max staleness + batch delay (#9,#10,#11,#12).
- Catalog version in EXTERNAL key (Redis/DB); long-running jobs pin version at start (#13,#14).
- ≥1-enabled-source enforced in app validation + portable DB constraints, not a relational check (#15).
- drop_from_catalog vs delete_from_storage; dropped raw stays queryable until prune (#16).
- Canonical keys encode storage semantics (`_ratio` vs `_percent`); never change storage meaning behind a key (#17).
- Source class live|historical|both; freshness validation only for live-capable (lets historical-only fallbacks save) (#18).
- EcoFlow serial modeled as typed param w/ controlled replacement (#19).
- PROVE all relevant MQTT transits the central collector before relying on collector transforms; else canonicalize at reader/producer for bypassing paths (#20).
- Post-cutover label-hygiene checks: canonical series carry only approved labels (#21).
- Rollback-to-previous-version as first-class admin+CLI action, distinct from reset-to-baseline (#22).

### Claude's response
All 22 accepted; added a normative "Source-descriptor & stitching model" section + tightened backfill ledger, versioning, schema, prune, cutover hygiene, rollback, and the collector-path assumption. Note: findings are now implementation-spec granularity, not architectural breakage — plan is converging.

## Round 3 — Codex
"Round 2 is mostly resolved... structured descriptor model, per-source staleness, left-looking stitching, version pinning, ledgered backfill materially stronger." 9 findings, VERDICT: REVISE. All accepted:
- **Read-path/prune tension** (#1,#2,#6): after verification, RUNTIME consumers read the CANONICAL OUTPUT series; raw Source Priority Chains are demoted to RECONSTRUCTION/PROVENANCE descriptors (backfill/fix-forward/diagnostics), not the runtime read path. Canonical reads gated by VERIFIED LEDGER COVERAGE for the requested range. Resolves the "reader stitches raw chains yet we prune raw" contradiction.
- Trend Value is a READ-LAYER (data-layer) function, not presentation (#3) — aligns with CONTEXT.
- Source allowlist from FULL inventory incl. historical active ranges, not only currently-live (#4).
- Spell out exact baseline selectors, no ellipsis (#5).
- Duplicate detection from import-source/ledger BEFORE write + VM-side sanity AFTER write (#7).
- Display-unit compatibility validated vs storage unit; incompatible needs approved conversion (#8).
- Exactly ONE canonical writer per canonical metric, enforced in the inclusion matrix (#9).

### Claude's response
All accepted. Edited plan: added runtime-read vs reconstruction descriptor split + verified-ledger-coverage read gate + one-writer rule; relabeled Trend as read-layer; widened source allowlist to historical ranges; spelled out exact freshWater selector; two-sided duplicate detection; display-unit compatibility validation. Findings are now clarifications, not breakage — strong convergence.

## Round 4 — Codex
"Round 3 blockers are resolved at the plan level." No new material blocker; only editorial nits (subtitle round count; a `21'` numbering quirk). VERDICT: APPROVED.

### Claude's response
Fixed both editorial nits. Plan converged and APPROVED after 4 Codex rounds.

## Resolution: CONVERGED (APPROVED) — 4 rounds
Act 1 (grill) + Act 2 (Codex) complete. No code written. Awaiting human sign-off before implementation.
