# Phase 2 — DB canonical catalog

The canonical catalog now lives in the database (`canonical_metrics`, `canonical_metric_sources`),
seeded from the code baseline in `App\Support\CanonicalBaseline`. `CanonicalReader` reads it via the
`CanonicalCatalog` repository (structured descriptors compiled to PromQL selectors + ordered transforms).

## Operate
- Seed/refresh from baseline: `php artisan db:seed --class=Database\\Seeders\\CanonicalCatalogSeeder` (idempotent).
- Reset to baseline (drops live edits): `php artisan metrics:catalog:reset` (`--force` to skip prompt).
- Roll back: `php artisan metrics:catalog:rollback {version}`.
- Every mutation writes a `canonical_catalog_versions` row (monotonic version + action + actor + full snapshot)
  and bumps the shared cache version key, so all Octane workers converge on the new catalog.

## Notes
- Live editing via Settings UI + authorization is Phase 7. CLIs are operator-run for now.
- The reader still only takes effect when `CANONICAL_READER_ENABLED=true` (default off).
- EcoFlow device serial is `CANONICAL_ECOFLOW_SERIAL` (typed param substituted into EcoFlow source topics).
