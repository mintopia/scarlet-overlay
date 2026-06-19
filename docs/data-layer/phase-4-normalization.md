# Phase 4 — Fix-forward ingestion (collector normalisation)

Normalisation applied at the **central OTEL collector** (`docker/otel-collector/config.yaml`,
otelcol-contrib **0.154.0**) as boat telemetry transits Pi → central → VictoriaMetrics. Decided
directly in collector config (the earlier catalog-coupled generator approach was rejected).

## Status

- **Stabilisation + Stage 1: DONE, deployed, verified** (on `develop`, live on rapunzel).
- **Stage 2 (EcoFlow drop): DEFERRED** until the EcoFlow unit is back online (it is currently
  offline — only its `pdBmsCommErr` / `pdIotCommErr` comm-error flags flow, no curated fields).
  The reviewed `filter/ecoflow` config is preserved in git commit `4654005`.

## Stabilisation (the 2026-06-19 outage fix)

- **`filter/dedup` corrected to dotted `device.id`.** The in-pipeline resource key is dotted
  `device.id`; the underscore `device_id` is only created later as a label by the exporter's
  `resource_to_telemetry_conversion`. The old `resource.attributes["device_id"] == nil` condition
  was nil for every datapoint, so the filter silently dropped 100% of telemetry after the unpinned
  image rolled to 0.154.0. See `project_otel_collector_incident` memory.
- **Collector image pinned** `otel/opentelemetry-collector-contrib:0.154.0` (no `:latest`). ADR 0006.

## Stage 1 — declarative label strips (panic-proof, no OTTL)

| Mechanism | Effect |
|---|---|
| `attributes/strip` delete `value`, `key`, `instance` (datapoint) | Removes the **changing `value`** attribute that spawned a new series per value on `mqtt.last_seen` and the signalk string metrics — the main cardinality win. |
| `prometheusremotewrite.disable_scope_info: true` | Drops `otel_scope_name` / `otel_scope_version`. |

**Deliberately NOT stripped:** `service.name`. The exporter derives BOTH the redundant
`service_name` label AND the wanted `job` label from it, with no declarative way to split them
(a datapoint attribute named `job` is not promoted to a label). `service_name` is a constant
(zero cardinality cost), so it is kept to preserve `job`.

### Label shape (verified in VM, post-deploy)

- **Kept:** `device_id`, `device_mode`, `gps_source` (gps), `topic` (mqtt), `job`, `service_name`.
- **Gone:** `otel_scope_name`, `otel_scope_version`, the changing `value` datapoint attribute.

Example: `scarlet_gps_latitude_deg{device_id="scarlet", device_mode="realtime", gps_source="signalk", job="boat-tracker", service_name="boat-tracker"}`.

## Pipeline order (Stage 1)

`[memory_limiter, filter/dedup, attributes/strip, batch]` → `prometheusremotewrite`
(namespace `scarlet`, `resource_to_telemetry_conversion: enabled`, `disable_scope_info: true`).
Stage 2 inserts `filter/ecoflow` before `batch`.

## Notes

- Stage 1 normalises only **new** data flowing through the collector. Historical series in VM
  retain the old labels (the changing `value`, `otel_scope_*`) — see Phase 5 (additive backfill)
  to reconcile history. Until then, history and live differ in label shape.
- Validate any collector change before deploying:
  `docker run --rm -v "$PWD/docker/otel-collector:/cfg:ro" otel/opentelemetry-collector-contrib:0.154.0 validate --config /cfg/config.yaml`,
  then deploy staged with `docker compose logs -f otel-collector` open. OTTL pieces (filter/ecoflow)
  are the only panic risk; declarative strips cannot panic.
