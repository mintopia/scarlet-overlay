# Plan Review Log: LCARS Star Trek easter egg ("LCARS Panel")

Act 1 (grill-with-docs) complete — plan locked, CONTEXT.md updated, ADR 0001 created. MAX_ROUNDS=5.

Resolved decision tree (Act 1):
- Form factor: admin-only standalone page `/admin/lcars` (Inertia `Admin/Lcars.vue`).
- Trigger: Konami code on admin dashboard.
- Palette: authentic TNG; font: Antonio.
- UX: interactive sectioned console (prototype A), graph-led, not text tables.
- Stations (data-honest): MSD landing + CONN, NAVIGATION, ENGINEERING, OPS, SCIENCE. Tactical/Medical dropped.
- Graph data: server preload (6h via fetchRange) + live reverb tip.
- Ambience: sound (off by default), stardate+clock, data cascades. No Red Alert.
- Overdrive: "living instrument console" (springs, odometer digits, morphing graphs, View Transitions). No boot/CRT.
- House-style divergence sanctioned: ADR 0001.

## Round 1 — Codex
(Note: Codex's sandbox uses bwrap, which cannot create a namespace in this nested
container, so the first attempt could not read any files and returned a non-substantive
REVISE. The review below was produced by embedding PLAN.md + CONTEXT.md + ADR + verified
repository facts inline in the prompt so Codex reviewed without filesystem access.)

Verdict: REVISE. 22 findings across security, data correctness, architecture/perf,
frontend/a11y, operations. Summary:
1. Admin-only not enforced (only `auth`). 2. Public `metrics` channel bypasses admin-only.
3. No test plan. 4. Payload completeness assumed (weather/sun/true-wind). 5. Appending
every broadcast fabricates samples. 6. History↔live reconciliation unspecified. 7. History
queries lack allowlist/step. 8. Not every viz has 6h history. 9. Freshness not represented.
10. Units/ranges/null unspecified. 11. Controller preload = synchronous fan-out / self-DoS.
12. Loading all-station history contradicts one-at-a-time. 13. Partial-failure undefined.
14. Glossary conflict (Section=Group vs Station combines groups + MSD). 15. Konami
underspecified. 16. Konami keyboard-only, no accessible path. 17. WebAudio lifecycle.
18. Off-screen/background-tab pausing. 19. 60fps has no budget/fallback. 20. Google Fonts
external dep. 21. No observability. 22. Responsive acceptance undefined.

### Claude's response
Final arbiter decisions. Verified against code first:
- /dashboard is PUBLIC and broadcasts the same telemetry on the same public `metrics`
  channel → telemetry is already public by design. UserRole enum exists (owner/crew) but
  ALL admin pages use only `auth`; crew are trusted.

ACCEPTED & incorporated: #3 (tests §7), #4 (per-Station payload verification + classify,
risks), #5/#6 (no fabricated samples, dedupe, ordering, ring-buffer cap §2b6), #7 (allow-list
+ step §2b5), #8 (metric classification §2b7), #9 (freshness §2b8), #10 (presentation contract
§2b9 + CONTEXT.md), #11/#12 (lazy per-section series endpoint §2b5), #13 (isolated nullable
failures §2b5), #14 (CONTEXT.md glossary fixed: Section→Bridge Station over ≥1 groups; MSD a
special Section), #15 (Konami state machine §1.2), #17 (audio lifecycle §4.11), #18 (visibility
pausing §5.13), #20 (self-hosted Antonio §4.10), #21 (proportionate observability §8),
#22 (≥1024px floor + notice §6.14).
PARTIAL: #16 — direct /admin/lcars URL is the accessible fallback (unlinked, not gated);
no extra UI control added.
REJECTED (logged): #1 owner-only gating — inconsistent with every other admin page; `auth`
is the intended boundary. #2 private channel — same data already public via /dashboard;
would be security theatre. #19 frame-budget instrumentation/auto-degrade — overkill for a
hidden egg; reduced-motion + visibility-pausing + modest effect set are proportionate.

## Round 2 — Codex (resumed thread, revised plan embedded inline)
Verdict: REVISE. Most prior findings resolved; remaining are polish. Resolved:
#1,#2 (arbiter rejections, accepted), #7,#8,#11,#12,#15,#17,#18,#19,#20,#21,#22.
Still open / new:
- #3 partial: series endpoint needs its own access tests.
- #4: "instantaneous from history-or-null" contradicts "only historical hit series".
- #5: present-null discarded → preserves stale; must clear + signal-loss.
- #6: history-load vs live-tip race + cadence mismatch unspecified.
- #9: payload has ONE event timestamp, not per-metric → can't claim per-metric freshness.
- #10 partial: frontend contract vs backend allowlist drift risk.
- #13 mostly: `error?` could leak exception detail.
- #14: `Metric Group` glossary term still said group→distinct section (contradiction).
- #16: unlinked URL is not a discoverable accessibility path.
- #23 NEW: ring-buffer count-cap vs 6h window contradictory.
- #24 NEW: "Key decisions" still said graphs populated on open (contradicts lazy).
- #25 NEW: freshness/reconciliation lack frontend tests.

### Claude's response
ACCEPTED ALL of round 2 (no rejections — all cheap, correct refinements):
- #3 → series access + parity tests (§7.15). #4 → removed contradiction; fixed source
  classes, no mixed-source (§2b7, risks). #5 → absent vs present-null vs present-non-null
  three-case handling; present-null clears + signal-loss (§2b6). #6 → buffer live during
  history load, merge by timestamp, dedupe, reject out-of-order (§2b6). #9 → freshness is
  now **feed-level** (one event ts) + per-reading signal-loss only on present-null (§2b8).
  #10 → presentation contract is single source of truth; backend allowlist derived + parity
  test (§2b5, §7.15). #13 → stable public `status` codes, exceptions logged only (§2b5).
  #14 → `Metric Group` glossary rewritten many-to-many (CONTEXT.md). #16 → dropped the
  accessibility-fallback claim; discovery is intentionally obscure, content still aims for
  a11y (§1.2). #23 → evict by 6h timestamp window + separate max-point cap (§2b6). #24 →
  Key-decisions bullet rewritten to MSD-preload + first-selection (§ Key decisions). #25 →
  added frontend reconciliation/freshness/lifecycle unit tests (§7.16).

## Round 3 — Codex (resumed, revised plan embedded)
Verdict: REVISE. Convergence reached on substance; only consistency nits + 1 new edge case.
Resolved: #3,#5,#6,#9,#13,#14,#23,#24,#25. Remaining:
- #4: wants the finalized per-metric source/classification matrix IN the plan.
- #10 partial: parity test should compare allowlist to contract's HISTORICAL subset, not all keys.
- #16: Out-of-scope line still called the URL "the accessible fallback" (contradicts §1.2).
- NEW1: changed-only sampling + 6h eviction can erase a constant metric's history entirely.
- NEW2: §17 still said "per-reading freshness" (contradicts feed-level §8).

### Claude's response
ACCEPTED ALL (all correct, cheap):
- #4 → added Appendix A: finalized per-metric classification matrix (H/I/C/ID) for all 6
  sections, with †flags for build-time live-payload verification (class fixed regardless).
- #10 → parity test reworded: backend allow-list == station's `historical`/registry-backed
  contract subset (§2b5, §7.15).
- #16 → removed the "accessible fallback" phrasing from Out of scope (§ Out of scope).
- NEW1 → ring-buffer always retains the most recent point regardless of age; constant
  metric flatlines at current value instead of emptying (§2b6).
- NEW2 → §17 reworded to "feed freshness + per-reading present-null signal-loss".

## Round 4 — Codex (resumed, revised plan embedded)
Verdict: REVISE. Converging; only exact-source detail + the last-known rendering + its test.
Resolved: #10, #16, NEW2. Remaining:
- #4 partial: Appendix A gave classes but not exact source keys; weather still †;
  stream/broadcast status unsourced; lat/lon had two classes (ID/I).
- NEW1 still open: "retain one old point" doesn't flatline and may fall outside the 6h domain.
- NEW(test): no test for the retained/last-known constant-metric behavior.

### Claude's response
ACCEPTED ALL:
- #4 → Appendix A now gives an exact source per metric (live:<group>.<key> / registry key /
  scarlet_weather_*); lat/lon fixed to single class **I**; weather/sun given concrete keys
  with a build-time dispatcher-confirmation note (null-render if absent); **stream/broadcast
  status dropped** (no live source; comms covered by LTE/WiFi) — also removed from §3.8 + CONTEXT.
- NEW1 → reworked §2b6: fixed graph domain [now-6h, now]; last-known value kept as metadata
  and drawn as a distinct "LAST KNOWN" dashed/dimmed flat line + readout, explicitly not
  measured samples — never empties, never misrepresents extrapolation.
- NEW(test) → added a unit test for a metric whose last sample is older than 6h (§7.16).

## Round 5 — Codex (final, MAX_ROUNDS=5 reached)
Verdict: REVISE. All round-4 findings confirmed resolved. ONE new finding:
- "Tracker live-payload paths are wrong — verified keys are heap/uptime/mode/lte_connected/
  wifi_connected/lte_rat, not tracker_heap/etc.; these would null-render."

### Claude's response — REJECTED (Codex factual error, verified against code)
Codex had NO filesystem access this entire review (bwrap sandbox could not create a
namespace), so its "verified" tracker key names are a hallucination. The actual code:
- MetricRegistry::fetchInstant() builds `$queries[$key]=...` and returns
  prometheus->queryMultipleAt() keyed by those SAME registry keys → keys retain the
  `tracker_` prefix.
- getTrackerMetrics() returns that map UNMODIFIED (only adds battery_percent, last_seen).
  Contrast getGpsMetrics(), which MANUALLY remaps gps_latitude→latitude — proving prefixes
  are only stripped where the code explicitly does so; tracker does not.
- Existing resources/js/Pages/Admin/Tracker.vue consumes `tracker_uptime`, `tracker_heap`,
  `tracker_wifi_connected`, `tracker_mode` — the prefixed names. Live-consumer proof.
Therefore Appendix A's `live:tracker.tracker_*` paths are CORRECT. Finding rejected.

## Resolution — round cap reached (5/5)
Substantive convergence achieved over rounds 1–4: 22 original findings + 5 follow-on, all
either incorporated or rejected-with-rationale. Round 5 surfaced no real defect — its lone
REVISE reason is a verified Codex hallucination (no file access). Three standing arbiter
rejections (#1 owner-only, #2 private channel, #19 frame-budget instrumentation) hold.
Plan is considered LOCKED pending user sign-off. No APPROVED token was emitted by Codex,
but the final REVISE is unfounded; this is not a genuine substantive deadlock.
