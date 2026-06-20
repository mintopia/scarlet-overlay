# 7. Derived metrics are read-time computations over canonical inputs

Date: 2026-06-20

## Status

Accepted

## Context

Point of sail and true wind speed/direction depend on **true wind**, but SignalK
publishes only **apparent** wind (`scarlet_signalk_environment_wind_angleApparent`,
`...wind_speedApparent`). There is no true-wind series in VictoriaMetrics. Confirmed
on production VM (2026-06-20): the only "true direction" series are the deprecated
`scarlet_boat_wind_direction_deg` (a `boat_*` series we do not use) and
`scarlet_weather_wind_direction_deg` (forecast). Neither is the boat's measured true
wind.

The inputs needed to compute true wind — apparent wind speed/angle, speed-through-
water, and heading — all flow as canonical metrics. The computation already exists
(`App\Support\NavigationMath::calculateTrueWind`, used by the ship's log resolver and
exports), but the canonical **read path** never invokes it, so `wind_direction_true`
and `wind_speed_true` read as ABSENT. As a result three dashboards each hand-rolled
"point of sail" from a different, wrong source (weather wind / apparent wind /
true-wind-direction) with different angle bands, and disagreed.

ADR 0002 (amended 2026-06-19) establishes that the canonical layer resolves **at read
time** over raw VM series and **writes nothing back** — there is no canonical writer.
A derived quantity like true wind is genuinely *new* (not a normalized copy of a raw
series), so the open question was how to produce it without reintroducing a writer.

## Decision

**Derived metrics are first-class canonical metrics whose value is computed at read
time from other canonical metrics, and are never written back to VictoriaMetrics.**

A canonical metric may declare a **derived source kind**: a named pure function (e.g.
`true_wind_direction`, `true_wind_speed`) plus the canonical input metrics it consumes.
`CanonicalReader` resolves a derived metric by reading its inputs through the normal
Source Priority Chain (with their units, validity bounds, and staleness), then applying
the function. The derived value carries the same read contract envelope
(value/age/stale/trend); it is **stale if any input is stale** and **null if any
required input is null**. History is derived the same way, point-by-point over the
inputs' series.

The first derived metrics are `wind_direction_true` and `wind_speed_true`, computed by
`NavigationMath::calculateTrueWind(apparentWindSpeed, apparentWindAngle,
speedThroughWater, heading)`. The shared frontend point-of-sail module consumes
`wind_direction_true` − `heading_true`, falling back to apparent wind only when the
derived value is unavailable.

## Consequences

- **True wind becomes a real canonical metric** without a writer, staying inside ADR
  0002's read-time-only model. Point of sail, true wind speed, and the compass arrows
  become correct and identical across every dashboard.
- **No new VM series, no backfill, no prune.** Derived values exist only at read time;
  history is computed on demand from the inputs' raw series.
- **Staleness is honest and conservative.** A derived metric is only as fresh as its
  least-fresh input; if STW or apparent wind is offline, true wind reads stale/null
  rather than showing a misleading artifact.
- **Computation lives in one place.** `NavigationMath` is the single implementation; the
  reader wires inputs → function. New derived metrics (VMG, etc.) follow the same
  pattern: declare inputs + function, no writer.
- **Read cost.** A derived metric fans out to several input reads per resolution;
  acceptable at dashboard cadence, and those inputs are already being read.
- **Catalog gains a source kind.** The DB catalog / baseline expresses "derived: fn +
  inputs" alongside existing source descriptors — an additive, versioned extension of
  ADR 0002's catalog, not a new store.

## Supersedes

None. Extends ADR 0002 (derived metrics = read-time computation, consistent with its
read-time / no-write-back model).
