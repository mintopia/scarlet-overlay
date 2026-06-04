# Planner Feature — Implementation Handoff

## What you're building

A GPX route planning workspace for the Scarlet sailing admin. Users upload GPX files into named groups (e.g. "Sarah's Routes", "Zoe's Routes"), compare routes on a Leaflet map with OpenSeaMap and Esri satellite tiles, toggle routes on/off, expand waypoint details, and share plans via public URLs.

## Key documents

- **Design spec:** `docs/superpowers/specs/2026-06-04-planner-design.md` — all product decisions, data model, states, interactions
- **Implementation plan:** `docs/superpowers/plans/2026-06-04-planner.md` — 12 tasks with exact code, file paths, commands, and test expectations
- **Visual mockup (approved):** Map on top, groups in a 2-column grid below. Each group is a panel card with routes, toggles, per-group dropzone. See `.superpowers/brainstorm/15610-1780573392/content/planner-layout-v4.html` for the approved HTML mockup.

## How to execute

Use `superpowers:subagent-driven-development` to implement the plan task-by-task. The plan file has checkbox syntax (`- [ ]`) for tracking progress.

Execute tasks 1–12 in order. Each task has exact file paths, complete code blocks, shell commands, and expected outputs. No placeholders — every step is self-contained.

## Critical patterns to follow

This is a Laravel 12 / Inertia v3 / Vue 3 / Tailwind 4 project. The plan's code already matches existing conventions, but watch for:

- **PHP:** Constructor property promotion, return types, `casts()` method (not `$casts` property), Pint formatting (`vendor/bin/pint --dirty --format agent`)
- **Controllers:** Return `Inertia::render()` for pages, `back()` for mutations, `redirect()->route()` for creates/deletes
- **Vue pages:** Wrap in `<AdminLayout>`, use `<Head>` for title, `useForm` for POST forms, `router.put/post/delete` for inline mutations with `preserveScroll: true`
- **Map:** Use Leaflet, init in `onMounted`, clean up in `onUnmounted`, watch theme changes for tile URL swap
- **Tests:** `RefreshDatabase`, `User::factory()->owner()->create()`, `Storage::fake()`, `UploadedFile::fake()->createWithContent()` for GPX uploads
- **No `confirm()`** — always use styled HTML modals for destructive actions
- **Colors:** OKLCH throughout, hue families defined in `resources/js/helpers/planColors.js`

## What's already done

- Design spec committed (`d96e8bb`→`b3d5ab0` range on `develop`)
- Implementation plan committed
- Visual mockups created and approved during brainstorming
- No code has been written yet — Task 1 (migrations) is the starting point

## After implementation

1. Run `vendor/bin/pint --dirty --format agent` to fix PHP formatting
2. Run `npm run build` to compile frontend
3. Run `php artisan test --compact` to verify no regressions
4. Test in browser: create a plan, add groups, upload GPX files, toggle routes, expand waypoints, try the share flow, switch between Sea Chart and Satellite tiles
