# Backup & restore-verify runbook (data-layer normalisation)

A backup does not count until it has been restored into a disposable VM and verified.

## Create
1. `php artisan metrics:backup` → snapshot name + `storage/app/metrics/backup-<ts>/{export.jsonl,manifest.json}`.

## Restore-verify (mandatory before any prune)
1. Start a throwaway VictoriaMetrics container (same version) on a scratch volume.
2. Import: `curl -X POST 'http://<disposable>:8428/api/v1/import' --data-binary @export.jsonl`.
3. For 5+ representative series (incl. `scarlet_mqtt_percent{topic="tanklevel"}`, a GPS metric, an EcoFlow field):
   compare `count_over_time(<series>[<full-window>])` and a checksum of values between production and disposable.
4. Record pass/fail against `manifest.json`. Prune (Phase 8) is BLOCKED until this passes.

## Notes
- `/snapshot/create` is instant + cheap (hard links); keep snapshots until canonical data is verified.
- This runbook is the gate referenced by ADR 0002 and PLAN-data-layer.md Phase 0/8.
