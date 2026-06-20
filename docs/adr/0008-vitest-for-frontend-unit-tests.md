# 8. Vitest for frontend unit tests

Date: 2026-06-20

## Status

Accepted

## Context

The app has substantial frontend logic — map track building, trend-path geometry,
metric formatting, heading resolution — but no JavaScript test runner. PHP is
covered by PHPUnit; JS was covered only by ad-hoc standalone scripts run by hand
(e.g. `resources/js/lib/trendPath.test.mjs`, a `node:assert` file referenced
nowhere and never run in CI).

This gap let a real bug ship: the boat track bridged telemetry gaps with a long
straight line (a "blue line joining two points") because the initial draw had no
gap threshold. The fix was verified only by replaying logic against live data in a
throwaway node one-liner — there was nothing to lock the behaviour in.

The build tool is already Vite, so Vitest is the zero-config, idiomatic runner: it
reuses the Vite transform pipeline and needs no separate Babel/Jest setup.

## Decision

**Adopt Vitest as the frontend unit-test runner.** Run via `npm test`
(`vitest run`) and `npm run test:watch`.

Test **pure logic**, not Leaflet/Vue rendering. Logic that needs coverage is
extracted into dependency-free modules (no `import L from 'leaflet'`, no Vue) so it
imports cleanly under Vitest without jsdom or CSS handling. The first such module is
`resources/js/track.js` (`isTrackGap`, `trackSegments`), extracted out of the
`useScarletMetrics` composable. Tests live next to their module as
`*.test.js` / `*.test.mjs`.

## Consequences

- A new dev dependency (`vitest`) and two npm scripts. No production-bundle impact.
- Frontend behaviour can now be locked with tests; the track-gap fix has one.
- Encourages separating pure logic from framework-coupled code (Leaflet/Vue) so it
  is testable — the same isolation principle the PHP side already follows.
- The pre-existing `trendPath.test.mjs` node script is converted to a Vitest suite,
  so all JS logic tests run under one command instead of by hand.
- CI/quality gates should run `npm test` alongside `php artisan test`.

## Supersedes

None
