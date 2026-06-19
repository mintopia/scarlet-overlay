# Handover — Scarlet telemetry: OTEL collector normalisation + data-layer program
_Written 2026-06-19 ~00:45 UTC for a fresh agent. Workspace: `/home/workspace/scarlet-overlay` (Laravel 12 + Inertia/Vue; telemetry in VictoriaMetrics). Branch: `develop`._

---

## 🔴 URGENT — RESUME HERE FIRST: live telemetry outage

**The boat → VictoriaMetrics data path is DOWN and unresolved.** Fresh boat metrics (`scarlet_mqtt_*`, `scarlet_signalk_*`, `scarlet_gps_*`) stopped at **2026-06-19 00:05:00 UTC** and have not resumed. This is a live production incident — fix it before anything else.

**What is NOT the cause (ruled out with evidence):**
- VM health — fine. It's serving reads and *ingesting via scrape*: control metric `scarlet_srt_up` (VM scrapes it directly from `app:80`, bypassing the boat path) is seconds-fresh.
- Clock/timezone — ruled out. My container and VM are both UTC unix-epoch, 0 skew. Scanned `now±3h`; boat data genuinely stops at 00:05, no offset hiding it. (The workspace shell shows UTC+2 wall-clock, but `time.time()`/VM `time()` are epoch, so it doesn't matter.)
- The Pi (boat-side collector) — healthy: processing ~25 datapoints/15s, its `otlphttp/remote` exporter shows **no export errors**, and it buffers to disk + retries forever (no data lost; it'll flush once the path is whole).
- The collector config — the live collector on rapunzel is back on the **known-good original** (normalisation removed); that's not the blocker.

**Root cause (strongly evidenced, not yet fixed): the central collector → VM remote-write hop is broken.** VM's own ingestion counters (from `http://44.30.69.5:8428/metrics`):
```
vm_rows_inserted_total{type="promscrape"}      314   ← SRT/weather scrape: WORKING
vm_rows_inserted_total{type="promremotewrite"}   0   ← central collector writes: ZERO
vm_http_requests_total{path="/api/v1/write"}      0   ← not one remote-write request has reached VM
```
So VM is receiving **zero** remote-write requests. The Pi delivers to the central collector fine, but the central cannot deliver to VM's `/api/v1/write`. The counters are 0 because a full `docker compose down && up` on rapunzel recreated VM and reset them — i.e. *since that restart, nothing has written*. Almost certainly a **docker-network problem**: after many `--force-recreate otel-collector` cycles + the down/up, the `otel-collector` container can't resolve/reach the `victoriametrics` service name.

**Last action (awaiting user):** I asked the user (on rapunzel, `/opt/scarlet`) to run and paste:
```bash
cd /opt/scarlet
docker compose logs --since 60s otel-collector 2>&1 | tail -30      # expect prometheusremotewrite "dial tcp ... no such host"/"connection refused"
docker compose ps                                                   # are otel-collector AND victoriametrics both Up, same project?
docker inspect -f '{{range $k,$v := .NetworkSettings.Networks}}{{$k}} {{end}}' scarlet-otel-collector-1 scarlet-victoriametrics-1
```
**Expected fix:** if the two containers aren't on a shared network, run `docker compose up -d` from the directory whose compose file defines **both** services so they rejoin one network. Then verify recovery (recipe below). If they ARE on the same network yet writes still don't land, check the central's `prometheusremotewrite` endpoint resolution and VM's `/api/v1/write` acceptance directly.

**Verify-recovery recipe (use this, NOT `/api/v1/series` which returns stale index entries):**
- `tlast_over_time(scarlet_mqtt_value[15m])` → should return a sample only seconds old.
- Cross-check control: `scarlet_srt_up` should always be fresh (proves VM up independent of boat path).
- `vm_rows_inserted_total{type="promremotewrite"}` at `…:8428/metrics` should be climbing.
- VM base URL: `http://44.30.69.5:8428` (reachable from this workspace). curl/wget are blocked by a hook here — fetch via `mcp__plugin_context-mode_context-mode__ctx_execute` Python (`urllib`).

---

## How we got here (one paragraph)

This incident came out of **Phase 4** of the data-layer program (OTEL collector normalisation). I (previous agent) shipped OTTL transform/filter processors that **silently no-op'd** (bare `attributes[...]` paths) and then, when "fixed" with explicit paths, **crash-looped the live collector** (panic on real data) — causing two outages. We reverted to the known-good config. A full `down/up` to recover then exposed/created the current central→VM network break. **Lesson, already in memory:** I was repeatedly wrong because I deployed collector changes I couldn't runtime-validate, and because I misread VM `/series` (stale index) as live data. Don't repeat that — see "verify recipe" above and the OTTL notes below.

---

## Current git / deploy state (accurate as of writing)

- **Live on rapunzel:** original known-good `docker/otel-collector/config.yaml` (no normalisation). Boat→VM still broken at the central→VM hop (above).
- **`origin/develop`:** collector config = original known-good (commit `d5a4452`, pushed). Safe.
- **Local `develop`:** 2 unpushed commits — `a0e3866`, `160ae8f` — these are the **audience-dashboards** workstream (ADRs 0004/0005 + a plan), unrelated to the incident, NOT pushed.
- **Working tree (uncommitted):** `docker/otel-collector/config.yaml` is MODIFIED — it holds the **corrected normalisation config** an expert subagent wrote (see next section). It is **NOT committed, NOT deployed, NOT runtime-validated.** Also one pre-existing untracked file `docs/superpowers/plans/2026-06-04-quality-review-fixes.md` (not mine; leave it).
- Do NOT deploy the working-tree config until the outage is fixed AND it's validated.

---

## The collector normalisation task (the actual Phase-4 goal)

**Goal:** as boat telemetry passes through the central OTEL collector (otelcol-contrib **0.154.0**) into VM, (1) **strip noise labels** that pollute every series and create duplicates, and (2) **drop the uncurated EcoFlow fields** (keep only a curated ~20 of ~125). Decided directly in `docker/otel-collector/config.yaml` — NOT a generator/app-coupled thing (an earlier over-engineered approach with an artisan generator/`NormalizationMap`/catalog-coupling was explicitly rejected by Jess and reverted).

**Ground-truth data structure** (from a real `debug verbosity:detailed` dump — this is the single most useful artifact; the previous agent kept guessing it wrong):
- Metric names are **dotted, NO `scarlet_` prefix** in-pipeline: `mqtt.value`, `mqtt.average`, `mqtt.percent`, `mqtt.raw`, `mqtt.last_seen`, `signalk.tanks.fuel.0.currentLevel`, etc. The `scarlet_` prefix + dot→underscore are added later by the `prometheusremotewrite` exporter (`namespace: scarlet`). So match `mqtt.value`, not `scarlet_mqtt_value`.
- **Resource attributes:** `service.name` (=boat-tracker), `device.id` (=scarlet), `device.mode` (=realtime). `device.id`/`device.mode` → labels `device_id`/`device_mode` via `resource_to_telemetry_conversion`.
- **InstrumentationScope:** name=`boat-tracker`, version=`1.0` → labels `otel_scope_name`/`otel_scope_version`.
- **Datapoint attributes:** `topic` (e.g. `tanklevel`, `watertank`, `zigbee2mqtt/Forepeak cabin`, `ecoflow/<serial>_<report>/<field>`), `value` (string rep on some metrics), sometimes `key`.

**Strip:** `key`, `value`, `instance` (datapoint), `service.name` + `service.instance.id` (resource → `service_name`/`instance`), `otel_scope_name`/`otel_scope_version` (scope). **Keep:** `device_id`, `device_mode`, `gps_source`, `topic`, `path`, LTE/wifi (`carrier`,`rat`,`ssid`), `job`.

**EcoFlow keep-list** (drop the rest of `mqtt.value{topic="ecoflow/.../<field>"}`): `soc, f32ShowSoc, actSoc, inputWatts, outputWatts, powInSumW, powOutSumW, powGetAc, powGetAcIn, powGet_12v, powGetTypec1, powGetTypec2, powGetQcusb1, powGetQcusb2, vol, amp, temp, remainTime, cycles, soh` (jess approved this list).

**HARD OTTL lessons (do not repeat — these caused the outages):**
1. **Bare `attributes["x"]` in transform/filter OTTL silently no-ops** in 0.154 (startup warns "paths were modified to include their context prefix"). Must use explicit `resource.attributes` / `datapoint.attributes` / `scope.name`.
2. The explicit-path **OTTL `transform` (flat `metric_statements` mixing contexts) crash-looped at runtime** (parsed fine, panicked on first data batch → restart loop → total outage). Avoid OTTL transform for this.

**The corrected config (currently in the working tree, uncommitted)** — written by a fresh expert subagent, and it sidesteps the crash by using **declarative, panic-proof processors** instead of OTTL where possible:
- `resource/strip` (resource processor) → delete `service.name`, `service.instance.id`.
- `attributes/strip` (attributes processor) → delete `key`, `value`, `instance`.
- `disable_scope_info: true` on the `prometheusremotewrite` exporter → drops `otel_scope_*` (supported, declarative).
- `filter/ecoflow` (the only remaining OTTL) → `error_mode: ignore` + `attributes["topic"] != nil` guard *before* `IsMatch` (short-circuit), scoped by `metric.name == "mqtt.value"`.
Read it at `docker/otel-collector/config.yaml` (working tree). **Status: NOT validated at runtime** — `otelcol validate` couldn't run here (no docker daemon in this workspace). The remaining unverified risk is the `filter/ecoflow` OTTL (the only OTTL left); the declarative parts can't panic.

**Safe deploy plan for normalisation (after the outage is fixed):**
1. Validate on a docker-capable host: `docker run --rm -v "$PWD/docker/otel-collector:/cfg:ro" otel/opentelemetry-collector-contrib:0.154.0 validate --config /cfg/config.yaml`.
2. Strongly consider a **staged rollout**: deploy ONLY the declarative pieces first (`resource/strip` + `attributes/strip` + `disable_scope_info`) — these cannot panic — confirm VM shows clean labels + still flowing; THEN add `filter/ecoflow` in a second deploy with `docker compose logs -f` open watching for a crash-loop, revert ready.
3. After it's proven on the live collector, commit + push the config and update `docs/data-layer/phase-4-normalization.md` to match.

---

## Other workstreams (context, not urgent)

- **Data-layer Phases 0–3: DONE, merged to `develop`, pushed.** Inventory/backups, `CanonicalReader` (flag-gated, OFF by default), DB canonical catalog (`canonical_metrics`/sources/versions, repo, baseline, reset/rollback CLIs), read-contract broadcast + admin recency UI. 243 tests passing before the Phase-4 detour. Full status in memory `project-data-layer`.
- **Audience dashboards (Part 5): parallel workstream Jess drove** — ADRs 0004/0005 + an implementation plan committed locally (the 2 unpushed commits). Memory `project-audience-dashboards` (note: scope was corrected to *hand-coded* bespoke role dashboards, NO user-facing builder). Earlier handoff at `/tmp/handoff-audience-dashboard-designer.md` is partly superseded by Jess's own design work.

---

## Key references
- **Memory** (`.claude/projects/-home-workspace-scarlet-overlay/memory/`): `project_data_layer.md` (has the OTTL + VM-`/series`-stale lessons), `project_audience_dashboards.md`, `reference_victoriametrics.md`, `reference_codex_sandbox_bwrap.md`, `feedback_no_browser_confirm.md`. Read `MEMORY.md` index first.
- **Plans:** `docs/superpowers/plans/2026-06-18-data-layer-phase-{0-1,2,3,4}.md`. ADRs `docs/adr/0002`/`0003` (note 0003's "Normalization Map derived from catalog" was the rejected over-engineered approach — superseded by the direct-config approach).
- **Config:** `docker/otel-collector/config.yaml` (working tree = corrected normalisation; HEAD = original). `docker-compose.yml` (collector + VM services). Pi config is on the boat (separate host) — exporter `otlphttp/remote` → `rapunzel.mintopia.net:4318`, bearer token (value REDACTED — it's in the Pi's `.env` as `OTLP_AUTH_TOKEN`; central must have matching `OTEL_AUTH_TOKEN`).
- **Hosts:** central collector + VM on `rapunzel` (`/opt/scarlet`, VM public `44.30.69.5:8428`). Pi/boat collector pushes OTLP to the central. Pi → central → VM is the ONLY path; VM also directly scrapes `app:80` for SRT/weather.

## Suggested skills
- **`metrics-reference`** — Scarlet metrics/PromQL/ingestion reference (activate for any VM/metrics work).
- **`superpowers:systematic-debugging`** — for the live outage; resist the urge to change configs before the cause is proven.
- **`verify`** / evidence-first habit — confirm against VM (recipe above) before claiming anything fixed; the previous agent repeatedly declared success prematurely.
- **`codex-review` / `grill-with-docs-codex`** — if reworking the normalisation design.
- Do NOT deploy collector configs you can't `otelcol validate` + smoke-test first.

## One-line status
Boat telemetry is DOWN (central→VM remote-write broken, likely docker network after a down/up); restore that first via the central logs + network check above, then (separately) finish Phase-4 normalisation by validating + staged-deploying the corrected config already sitting in the working tree.
