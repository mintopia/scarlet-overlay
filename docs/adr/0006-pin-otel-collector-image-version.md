# 6. Pin the OTEL Collector image to an explicit version (no `:latest`)

Date: 2026-06-19

## Status

Accepted

## Context

On 2026-06-19 boat telemetry stopped reaching VictoriaMetrics at 00:05 UTC and stayed
down for ~7h. The central OTEL collector ran `otel/opentelemetry-collector-contrib:latest`.
A routine `docker compose down && up` on rapunzel silently pulled a newer image — **0.154.0** —
whose OTTL attribute-path handling differs from the previously running version.

The live `filter/dedup` processor used `resource.attributes["device_id"] == nil`. The real
in-pipeline resource key is the **dotted** `device.id` (the underscore `device_id` is only
created later, as a Prometheus label, by the exporter's `resource_to_telemetry_conversion`) —
confirmed via a 0.154.0 `debug verbosity:detailed` dump. Under 0.154.0 that condition was `nil`
for every datapoint, so the filter **dropped 100% of telemetry**. The failure was silent: the
exporter had nothing to send, so VictoriaMetrics logged zero `/api/v1/write` requests and the
collector logged no errors. Diagnosis was slow precisely because the symptom (zero remote-writes,
healthy VM, healthy Pi) looked like a network break, not a config/version interaction.

`:latest` means the deployed version is non-deterministic: it changes on any pull, with no
review, no changelog reading, and no config re-validation. A latent config bug can lie dormant
across many restarts and then activate on an unrelated `down/up`. This is the same class of
data-quality / silent-drift failure that motivated [[canonical-metric]] and ADR 0002.

## Decision

Pin the OTEL collector image to an explicit version in `docker-compose.yml`:
`otel/opentelemetry-collector-contrib:0.154.0` (the known-good version, with the config
corrected to the dotted `device.id` key).

Version bumps are **deliberate**: change the tag explicitly, read the collector changelog for
processor/OTTL behaviour changes, `otelcol validate` the config against the new image, and
deploy staged with logs watched and a revert ready. Never rely on an implicit `:latest` pull.

The same `:latest` risk exists for other services in the compose file — notably
`victoriametrics/victoria-metrics:latest`. Pinning those is recommended follow-up (out of scope
for this ADR, which addresses the service that caused the incident).

## Consequences

- **Easier:** deployments are reproducible; a `down/up` can no longer silently change the
  collector version or its data-handling behaviour.
- **Harder:** security/feature updates require a deliberate, reviewed bump instead of arriving
  automatically — an acceptable trade for a component on the critical telemetry path.
- **Risk accepted:** the pinned version can age; mitigated by treating bumps as a normal,
  validated maintenance task.
- **Follow-up:** pin the remaining `:latest` images (VictoriaMetrics first); re-validate the
  Phase-4 normalisation config (ADR 0002) against the pinned collector before its staged rollout.

## Supersedes

None.
