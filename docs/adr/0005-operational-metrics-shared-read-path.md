# 5. Operational/infra metrics read through the shared metrics service + source-mapping

Date: 2026-06-18

## Status

Accepted

## Context

The Tech Audience Dashboard ([[project-audience-dashboards]]) surfaces operational/infrastructure
telemetry that is not a physical boat quantity:

- **Streaming** — `scarlet_srt_*` (publisher: bitrate/RTT/latency/dropped/network; consumer:
  bitrate/RTT/latency/dropped; `srt_up`), which VictoriaMetrics scrapes directly from the overlay
  (per ADR 0003, SRT stream stats are the one path VM scrapes directly rather than via the OTEL collector).
- **ESP32 tracker** — `scarlet_system_*` (battery V, CPU, free heap, internal temp/humidity, multi-carrier
  LTE RSSI/quality/RAT, WiFi RSSI, realtime/saver mode, uptime, usb_powered), arriving via the OTEL
  `boat-tracker` scope.

ADR 0002 scoped the canonical work to "non-EcoFlow **physical** metrics," which left ambiguous how these
operational metrics are read by a dashboard. The existing `StreamMonitorController` already reads
`scarlet_srt_*` through `PrometheusService`. The risk to avoid: a second, ad-hoc read idiom (raw PromQL
sprinkled through dashboard controllers) growing up alongside the canonical read path.

Clarification of the data-layer model that frames this decision: **all** metrics come from VictoriaMetrics
**via the shared service**. The admin "catalog" is a **source-mapping UI** — it maps each logical
data-source the app uses to a metric series in VM, with fallbacks/priority, units, and staleness. It is
not a separate store of metric definitions divorced from VM.

## Decision

Every metric the Audience Dashboards consume — physical boat data **and** operational/infra telemetry
(`scarlet_srt_*`, `scarlet_system_*`) — is read from VictoriaMetrics through the **shared metrics
service**, resolved via the **admin source-mapping** (logical data-source → VM series, with
fallbacks/priority/units/staleness). The value/age/stale/trend envelope of the read contract applies
**uniformly** to operational metrics, so Tech shows staleness the same way the other dashboards do.

Operational metrics are ordinary mapped data-sources — typically single-source, identity units, and no
priority chain — but they go through the same mapping and the same service. **Dashboard controllers do
not issue raw PromQL.**

This **refines the scope** of ADR 0002 (confirming operational metrics use the one read path) and is
consistent with ADR 0003 (whose "VM scrapes SRT directly" note concerns ingestion; this ADR concerns
read). It does **not** supersede either.

## Consequences

- **One read path, one freshness model** — operational and physical metrics are read and staleness-guarded
  identically; no parallel raw-PromQL idiom in dashboard controllers.
- **Tech's infra metrics get age/stale handling for free** — a dropped tracker or dead SRT scrape reads as
  stale through the same envelope, which Tech surfaces (idle/old states).
- **Mapping covers operational sources too** — they appear in the admin source-mapping UI like any other
  data-source; their mappings are trivial (single source, identity transform, no priority) but uniform.
- **Ingestion is unchanged** — SRT remains directly scraped by VM and the ESP32 remains an OTEL
  `boat-tracker` scope; this ADR governs how dashboards *read*, not how data lands.
- **Deliberate scope refinement of ADR 0002** — "physical metrics" was about what the normalization/
  priority machinery is for; operational metrics still ride the same service + mapping for reads.

## Supersedes

None. Refines ADR 0002 and complements ADR 0003 (neither superseded).
