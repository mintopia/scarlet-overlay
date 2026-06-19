# Admin Navigation & Data-View Overhaul Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Consolidate the admin area — remove Tracker/Broadcast/Environment/Explore, add a camera-only Stream page, section the sidebar, fix Ops layout bugs, rename Data Mapping → Data with live value/age + a uPlot Grafana-style metric explorer, and make the hand-drawn SVG charts draw crisp constant-width lines.

**Architecture:** Laravel 12 + Inertia v3 + Vue 3 SPA. Server routes under `routes/web.php` (`admin` prefix, `auth` middleware) render Inertia pages in `resources/js/Pages`. Metric reads go through `App\Services\CanonicalReader` (the sole read path). A new `DataController` serves the explorer page + JSON series/current endpoints; the existing `CanonicalCatalogController` keeps the catalog CRUD under renamed `admin.data.*` routes. Charts use the already-installed `uplot` library via a thin Vue wrapper.

**Tech Stack:** PHP 8.4, Laravel 12, Inertia v3, Vue 3 (`<script setup>`), Tailwind v4, uPlot `^1.6.32`, hls.js `^1.6.16`, PHPUnit 11.

## Global Constraints

- PHP 8.4; explicit return types + param type hints; constructor property promotion; curly braces on all control structures.
- After editing any PHP file, run `vendor/bin/pint --dirty --format agent` before committing.
- Tests are **PHPUnit** (never Pest). Base class `Tests\TestCase`, `RefreshDatabase` trait. Auth via `$this->actingAs(User::factory()->create())`. Inertia assertions via `Inertia\Testing\AssertableInertia as Assert`.
- No new runtime dependencies. Charts use `uplot` only (already in `package.json`).
- Generate links with `route()` names, never hardcoded paths, in PHP and Vue.
- Confirmations use styled HTML modals, never `confirm()`.
- Follow sibling-file conventions (class structure, naming, Tailwind utility style).
- Metric reads go through `CanonicalReader`; never reintroduce the removed legacy registry.
- Frontend-only template/CSS changes have no JS test runner in this repo — verify them with a controller/route render test plus manual visual check (`npm run dev`). Backend (controllers, routes, JSON endpoints) gets real PHPUnit feature tests.

## File Structure

**New**
- `app/Http/Controllers/Admin/StreamController.php` — renders the camera page.
- `app/Http/Controllers/Admin/DataController.php` — explorer page (`show`) + JSON `series` + `current`.
- `resources/js/Pages/Admin/Stream.vue` — full-bleed live camera.
- `resources/js/Pages/Admin/MetricExplorer.vue` — Grafana-style explorer.
- `resources/js/components/Admin/UplotChart.vue` — uPlot wrapper (multi-series/axis, theme-aware).
- `resources/js/components/Admin/MetricSelect.vue` — typeahead metric picker.
- `tests/Feature/Admin/AdminNavOverhaulTest.php` — removed-route 404s + nav.
- `tests/Feature/Admin/StreamPageTest.php`
- `tests/Feature/Admin/DataExplorerTest.php`

**Modified**
- `routes/web.php`
- `app/Http/Controllers/Admin/CanonicalCatalogController.php` (render `Admin/Data`)
- `app/Http/Controllers/Admin/StreamMonitorController.php` (trim to `updatePull`)
- `resources/js/Layouts/AdminLayout.vue` (sections, links, breadcrumbs)
- `resources/js/Layouts/NavLink.vue` (add `video` icon)
- `resources/js/Pages/Admin/Dash/Ops.vue` (weather de-dup + battery alignment)
- `resources/js/Pages/Admin/Dash/Tech.vue` (Stream link card)
- `resources/js/components/Admin/Sparkline.vue`, `TrendChart.vue` (crisp strokes)
- `tests/Feature/Admin/CanonicalCatalogControllerTest.php` (route/component renames)

**Renamed**
- `resources/js/Pages/Admin/Catalog.vue` → `resources/js/Pages/Admin/Data.vue`

**Deleted**
- Pages: `Admin/Tracker.vue`, `Admin/Broadcast.vue`, `Admin/Environment.vue`, `Admin/Explore.vue`, `Admin/ExploreDashboard.vue`
- Controllers: `TrackerController`, `AdminEnvironmentController`, `ExploreController`

---

## Task 1: Crisp SVG lines (Sparkline + TrendChart)

**Files:**
- Modify: `resources/js/components/Admin/Sparkline.vue:97,103,106`
- Modify: `resources/js/components/Admin/TrendChart.vue:187,201,215,226,238,254`

**Interfaces:**
- Consumes: nothing.
- Produces: no API change — visual only. `vector-effect="non-scaling-stroke"` keeps stroke width constant under the stretched (`preserveAspectRatio="none"`) viewBox.

Note: `resources/js/components/Lcars/LcarsLineGraph.vue` already has `vector-effect="non-scaling-stroke"` — do not touch it.

- [ ] **Step 1: Sparkline — add non-scaling-stroke to the three stroked elements**

In `resources/js/components/Admin/Sparkline.vue`, edit these three elements (add `vector-effect="non-scaling-stroke"`):

Zero line (currently line 97):
```html
<line v-if="zeroLine && pathData.zeroY" x1="0" :y1="pathData.zeroY" :x2="width" :y2="pathData.zeroY" :stroke="color" stroke-width="0.5" stroke-dasharray="2 2" opacity="0.3" vector-effect="non-scaling-stroke"/>
```

Solid line segments (currently line 103):
```html
<path v-for="(seg, i) in pathData.lineSegments" :key="'l'+i" :d="seg" fill="none" :stroke="color" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" opacity="0.7" vector-effect="non-scaling-stroke"/>
```

Dotted gap connectors (currently line 106):
```html
<path v-for="(seg, i) in pathData.gapPaths" :key="'g'+i" :d="seg" fill="none" :stroke="color" stroke-width="1" stroke-dasharray="3 3" opacity="0.3" vector-effect="non-scaling-stroke"/>
```

- [ ] **Step 2: TrendChart — add non-scaling-stroke to every stroked `<path>`/`<line>`**

In `resources/js/components/Admin/TrendChart.vue`, add `vector-effect="non-scaling-stroke"` to each of these stroked elements (leave the fill `<path>`s and the end-dot `<circle>` untouched):
- Line variant path (line 187 `:d="linePath.line"`)
- Area variant line path (line 201 `:d="linePath.line"` — the second path in that block, not the fill)
- Bipolar zero baseline `<line>` (line 215)
- Bipolar above-zero paths (line 226 `v-for ... bipolarData.above`)
- Bipolar below-zero paths (line 238 `v-for ... bipolarData.below`)
- Water variant line path (line 254 `:d="waterData.linePath"`)

Example (bipolar above-zero), final form:
```html
<path
    v-for="(seg, i) in bipolarData.above"
    :key="'a' + i"
    :d="seg"
    fill="none"
    :stroke="colorPositive"
    stroke-width="2"
    stroke-linecap="round"
    stroke-linejoin="round"
    opacity="0.85"
    vector-effect="non-scaling-stroke"
/>
```

- [ ] **Step 3: Build to confirm no syntax errors**

Run: `npm run build`
Expected: build completes without errors.

- [ ] **Step 4: Manual visual verification**

Run `npm run dev`, open `/admin/dash/tech` and `/admin/dash/ops`. Confirm the battery/bitrate trend lines and sparklines render at uniform width (no thick/thin variation along diagonals).

- [ ] **Step 5: Commit**

```bash
git add resources/js/components/Admin/Sparkline.vue resources/js/components/Admin/TrendChart.vue
git commit -m "fix(charts): constant-width SVG strokes via non-scaling-stroke"
```

---

## Task 2: Remove the Tracker page

**Files:**
- Modify: `routes/web.php:95` (remove route), `routes/web.php:22` (remove import)
- Delete: `resources/js/Pages/Admin/Tracker.vue`, `app/Http/Controllers/Admin/TrackerController.php`
- Modify: `resources/js/Layouts/AdminLayout.vue:34` (nav link), `:163` (breadcrumb)
- Test: `tests/Feature/Admin/AdminNavOverhaulTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `route('admin.tracker')` no longer exists; `/admin/tracker` returns 404. (The `TrackerPanel` Vue component used by Tech is unrelated and stays.)

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Admin/AdminNavOverhaulTest.php`:
```php
<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminNavOverhaulTest extends TestCase
{
    use RefreshDatabase;

    public function test_tracker_route_is_removed(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin/tracker')
            ->assertNotFound();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=test_tracker_route_is_removed`
Expected: FAIL — currently returns 200, not 404.

- [ ] **Step 3: Remove the route, controller, page, and nav entries**

- `routes/web.php`: delete line `Route::get('/tracker', [TrackerController::class, 'index'])->name('admin.tracker');` and the `use App\Http\Controllers\Admin\TrackerController;` import.
- Delete files `resources/js/Pages/Admin/Tracker.vue` and `app/Http/Controllers/Admin/TrackerController.php`.
- `resources/js/Layouts/AdminLayout.vue`: delete the Tracker `<NavLink>` (line 34) and the `'Admin/Tracker': [home, { label: 'Tracker' }],` entry in `breadcrumbMap`.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=test_tracker_route_is_removed`
Expected: PASS.

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "refactor(admin): remove Tracker page"
```

---

## Task 3: Remove the Broadcast page (keep the pull endpoint)

**Files:**
- Modify: `routes/web.php:92,94` (remove GET `broadcast` + the `stream`→`broadcast` redirect; KEEP `broadcast/pull`)
- Delete: `resources/js/Pages/Admin/Broadcast.vue`
- Modify: `app/Http/Controllers/Admin/StreamMonitorController.php` (remove `index`, keep `updatePull`)
- Modify: `resources/js/Layouts/AdminLayout.vue:35` (nav link), `:166` (breadcrumb)
- Test: `tests/Feature/Admin/AdminNavOverhaulTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `GET /admin/broadcast` returns 404; `route('admin.broadcast.pull')` (POST) still works — `components/Dash/SignalChain.vue` depends on it.

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/Admin/AdminNavOverhaulTest.php`:
```php
    public function test_broadcast_page_route_is_removed(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin/broadcast')
            ->assertNotFound();
    }

    public function test_broadcast_pull_endpoint_still_exists(): void
    {
        // Pull is POST-only; a GET should be 405 (route exists), not 404.
        $this->actingAs(User::factory()->create())
            ->get('/admin/broadcast/pull')
            ->assertStatus(405);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter=AdminNavOverhaulTest`
Expected: `test_broadcast_page_route_is_removed` FAILS (currently 200).

- [ ] **Step 3: Remove the broadcast GET route, redirect, page, controller method, and nav entries**

- `routes/web.php`: delete `Route::get('broadcast', [StreamMonitorController::class, 'index'])->name('admin.broadcast');` and `Route::redirect('stream', 'broadcast');`. **Keep** the `broadcast/pull` POST route.
- Delete `resources/js/Pages/Admin/Broadcast.vue`.
- `app/Http/Controllers/Admin/StreamMonitorController.php`: remove the `index()` method (and any now-unused private helpers it alone used); keep `updatePull()`. If the class becomes empty except `updatePull`, leave it — it still backs the pull route.
- `resources/js/Layouts/AdminLayout.vue`: delete the Broadcast `<NavLink>` (line 35) and the `'Admin/Broadcast': [home, { label: 'Broadcast' }],` breadcrumb entry.

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact --filter=AdminNavOverhaulTest`
Expected: PASS.

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "refactor(admin): remove Broadcast page, keep pull endpoint"
```

---

## Task 4: Remove Environment and Explore

**Files:**
- Modify: `routes/web.php:97-102` (remove explore ×3, environment ×2, weather redirect), `:8,:8` imports (`ExploreController`, `AdminEnvironmentController`)
- Delete: `resources/js/Pages/Admin/Environment.vue`, `Admin/Explore.vue`, `Admin/ExploreDashboard.vue`, `app/Http/Controllers/Admin/AdminEnvironmentController.php`, `app/Http/Controllers/Admin/ExploreController.php`
- Modify: `resources/js/Layouts/AdminLayout.vue:29,30` (nav links), `:165,167,168` (breadcrumbs)
- Test: `tests/Feature/Admin/AdminNavOverhaulTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `/admin/environment`, `/admin/weather`, `/admin/explore` all 404. Time-series for the new explorer is provided by `DataController` (Task 8), not these.

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/Admin/AdminNavOverhaulTest.php`:
```php
    public function test_environment_and_explore_routes_are_removed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin/environment')->assertNotFound();
        $this->actingAs($user)->get('/admin/weather')->assertNotFound();
        $this->actingAs($user)->get('/admin/explore')->assertNotFound();
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=test_environment_and_explore_routes_are_removed`
Expected: FAIL (currently 200 / redirect).

- [ ] **Step 3: Find any stray references to the removed routes**

Run: `grep -rn "admin.explore\|admin.environment\|/admin/explore\|/admin/environment" resources/js app routes`
Expected: matches only in files being deleted in this task. If any other file references them (e.g. a dashboard linking to Explore), remove that link/usage. (`Environment.vue`'s `exploreLink`/`/admin/environment/series` go away with the file.)

- [ ] **Step 4: Remove routes, controllers, pages, and nav entries**

- `routes/web.php`: delete the three `explore` routes, the two `environment` routes, the `Route::redirect('/weather', '/admin/environment');`, and the `use` imports for `ExploreController` and `AdminEnvironmentController`.
- Delete `resources/js/Pages/Admin/Environment.vue`, `Admin/Explore.vue`, `Admin/ExploreDashboard.vue`.
- Delete `app/Http/Controllers/Admin/AdminEnvironmentController.php` and `app/Http/Controllers/Admin/ExploreController.php`.
- `resources/js/Layouts/AdminLayout.vue`: delete the Environment (line 29) and Explore (line 30) `<NavLink>`s, and the `Admin/Environment`, `Admin/Broadcast` (already gone), `Admin/ExploreDashboard`, `Admin/Explore` breadcrumb entries.

- [ ] **Step 5: Run tests + full suite of nav tests**

Run: `php artisan test --compact --filter=AdminNavOverhaulTest`
Expected: PASS.

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "refactor(admin): remove Environment and Explore (covered by Ops + new Data explorer)"
```

---

## Task 5: Rename Data Mapping → Data (routes, page, references)

**Files:**
- Modify: `routes/web.php:152-159` (rename catalog routes to `admin.data.*` at `/admin/data`)
- Modify: `app/Http/Controllers/Admin/CanonicalCatalogController.php` (render `Admin/Data`)
- Rename: `resources/js/Pages/Admin/Catalog.vue` → `resources/js/Pages/Admin/Data.vue` (h1/title + `route('admin.data.*')` refs)
- Modify: `resources/js/Layouts/AdminLayout.vue:40` (nav label/href), `:171` (breadcrumb)
- Modify: `tests/Feature/Admin/CanonicalCatalogControllerTest.php` (route names + component)

**Interfaces:**
- Consumes: nothing.
- Produces: `route('admin.data.index')` → `GET /admin/data` renders `Admin/Data`; CRUD under `admin.data.{store,inventory,test,rollback,update,destroy}`. Old `/admin/metrics/catalog` 404s.

- [ ] **Step 1: Update the existing catalog test to the new names (these become the failing tests)**

In `tests/Feature/Admin/CanonicalCatalogControllerTest.php`, replace every `route('admin.catalog')` → `route('admin.data.index')`, `route('admin.catalog.store')` → `route('admin.data.store')`, `route('admin.catalog.update', …)` → `route('admin.data.update', …)`, `route('admin.catalog.destroy', …)` → `route('admin.data.destroy', …)`, `route('admin.catalog.test')` → `route('admin.data.test')`, `route('admin.catalog.inventory')` → `route('admin.data.inventory')`, `route('admin.catalog.rollback')` → `route('admin.data.rollback')`. Change every `->component('Admin/Catalog')` → `->component('Admin/Data')`.

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact tests/Feature/Admin/CanonicalCatalogControllerTest.php`
Expected: FAIL — `route('admin.data.index')` not defined yet.

- [ ] **Step 3: Rename the routes**

In `routes/web.php`, replace the `// Canonical metric catalog` block with (note: literal GET routes precede the `{metric}` wildcard added in Task 8; `update`/`destroy` are distinct verbs so order-safe):
```php
    // Canonical metric catalog (Data)
    Route::get('data', [CanonicalCatalogController::class, 'index'])->name('admin.data.index');
    Route::post('data', [CanonicalCatalogController::class, 'store'])->name('admin.data.store');
    Route::get('data/inventory', [CanonicalCatalogController::class, 'inventory'])->name('admin.data.inventory');
    Route::post('data/test', [CanonicalCatalogController::class, 'test'])->name('admin.data.test');
    Route::post('data/rollback', [CanonicalCatalogController::class, 'rollback'])->name('admin.data.rollback');
    Route::put('data/{metric}', [CanonicalCatalogController::class, 'update'])->name('admin.data.update');
    Route::delete('data/{metric}', [CanonicalCatalogController::class, 'destroy'])->name('admin.data.destroy');
```

- [ ] **Step 4: Point the controller at the renamed page**

In `app/Http/Controllers/Admin/CanonicalCatalogController.php`, change `Inertia::render('Admin/Catalog', [` → `Inertia::render('Admin/Data', [`.

- [ ] **Step 5: Rename the Vue page and update its references**

- `git mv resources/js/Pages/Admin/Catalog.vue resources/js/Pages/Admin/Data.vue`
- In `Data.vue`: change `<Head title="Data Mapping" />` → `<Head title="Data" />` and `<h1 ...>Data Mapping</h1>` → `<h1 ...>Data</h1>`. Replace every `route('admin.catalog.*')` call (`admin.catalog.inventory`, `admin.catalog.test`, `admin.catalog.store`, `admin.catalog.update`, `admin.catalog.destroy`, `admin.catalog.rollback`) with the matching `route('admin.data.*')` name.

- [ ] **Step 6: Update the sidebar + breadcrumb**

In `resources/js/Layouts/AdminLayout.vue`:
- Change the Data Mapping link to `<NavLink href="/admin/data" icon="layers" :active="currentPage === 'Admin/Data'" @click="sidebarOpen = false">Data</NavLink>`.
- Replace the `'Admin/Catalog': [home, { label: 'Data Mapping' }],` breadcrumb entry with `'Admin/Data': [home, { label: 'Data' }],`.

- [ ] **Step 7: Add a test that the old path 404s**

Add to `tests/Feature/Admin/AdminNavOverhaulTest.php`:
```php
    public function test_old_catalog_path_is_removed(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin/metrics/catalog')
            ->assertNotFound();
    }
```

- [ ] **Step 8: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Admin/CanonicalCatalogControllerTest.php tests/Feature/Admin/AdminNavOverhaulTest.php`
Expected: PASS.

- [ ] **Step 9: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "refactor(admin): rename Data Mapping to Data under admin.data.* routes"
```

---

## Task 6: New Stream page (camera only) + Tech link

**Files:**
- Create: `app/Http/Controllers/Admin/StreamController.php`
- Create: `resources/js/Pages/Admin/Stream.vue`
- Modify: `routes/web.php` (add `admin.stream` GET + import)
- Modify: `resources/js/Layouts/AdminLayout.vue` (Stream NavLink + breadcrumb), `resources/js/Layouts/NavLink.vue` (add `video` icon)
- Modify: `resources/js/Pages/Admin/Dash/Tech.vue` (Live Camera link card)
- Test: `tests/Feature/Admin/StreamPageTest.php`

**Interfaces:**
- Consumes: `useVideoFeed(videoEl)` from `@/composables/useVideoFeed.js` → returns `{ videoActive, videoChecked, connect, cleanup }`; registers `onUnmounted(cleanup)` internally.
- Produces: `route('admin.stream')` → `GET /admin/stream` renders `Admin/Stream`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Admin/StreamPageTest.php`:
```php
<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StreamPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_stream_page_renders(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.stream'))
            ->assertInertia(fn (Assert $p) => $p->component('Admin/Stream'));
    }

    public function test_stream_page_requires_auth(): void
    {
        $this->get('/admin/stream')->assertRedirect(route('login'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Admin/StreamPageTest.php`
Expected: FAIL — `route('admin.stream')` not defined.

- [ ] **Step 3: Create the controller**

Create `app/Http/Controllers/Admin/StreamController.php`:
```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class StreamController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Stream');
    }
}
```

- [ ] **Step 4: Register the route**

In `routes/web.php`, add the import `use App\Http\Controllers\Admin\StreamController;` and, inside the admin group, add:
```php
    Route::get('stream', [StreamController::class, 'index'])->name('admin.stream');
```

- [ ] **Step 5: Create the page**

Create `resources/js/Pages/Admin/Stream.vue`:
```vue
<template>
    <AdminLayout>
        <Head title="Stream" />
        <div class="stream-stage">
            <video
                ref="videoRef"
                class="stream-video"
                :class="{ 'stream-video--live': videoActive }"
                muted
                playsinline
                autoplay
            ></video>
            <div v-if="!videoActive" class="stream-overlay">
                <div class="stream-pulse" aria-hidden="true"></div>
                <p class="stream-overlay__text">
                    {{ videoChecked ? 'Waiting for camera feed…' : 'Connecting to camera…' }}
                </p>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useVideoFeed } from '@/composables/useVideoFeed.js';

const videoRef = ref(null);
const { videoActive, videoChecked, connect } = useVideoFeed(videoRef);

onMounted(() => {
    connect();
});
</script>

<style scoped>
.stream-stage {
    position: relative;
    width: 100%;
    aspect-ratio: 16 / 9;
    background: #000;
    border-radius: 14px;
    overflow: hidden;
    border: 1px solid var(--color-border);
}

.stream-video {
    width: 100%;
    height: 100%;
    object-fit: contain;
    display: block;
    opacity: 0;
    transition: opacity 0.3s ease-out;
}

.stream-video--live { opacity: 1; }

.stream-overlay {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 14px;
    color: oklch(0.85 0.01 240);
}

.stream-overlay__text {
    font-size: 13px;
    font-weight: 600;
    letter-spacing: 0.04em;
}

.stream-pulse {
    width: 14px;
    height: 14px;
    border-radius: 50%;
    background: var(--color-scarlet);
    animation: stream-pulse 1.4s ease-in-out infinite;
}

@keyframes stream-pulse {
    0%, 100% { opacity: 0.3; transform: scale(0.85); }
    50% { opacity: 1; transform: scale(1.1); }
}

@media (prefers-reduced-motion: reduce) {
    .stream-pulse { animation: none; }
    .stream-video { transition: none; }
}
</style>
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --compact tests/Feature/Admin/StreamPageTest.php`
Expected: PASS.

- [ ] **Step 7: Add a `video` icon to NavLink**

In `resources/js/Layouts/NavLink.vue`, add this branch inside the `<svg>` (after the `globe` branch, line 21):
```html
            <template v-else-if="icon === 'video'"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/></template>
```

- [ ] **Step 8: Add the Stream NavLink + breadcrumb**

In `resources/js/Layouts/AdminLayout.vue`:
- Under the audience-dashboard links (after Skipper), add:
```html
                <NavLink href="/admin/stream" icon="video" :active="currentPage === 'Admin/Stream'" @click="sidebarOpen = false">Stream</NavLink>
```
- Add to `breadcrumbMap`: `'Admin/Stream': [home, { label: 'Stream' }],`.

- [ ] **Step 9: Add the Live Camera link card on Tech**

In `resources/js/Pages/Admin/Dash/Tech.vue`, import `Link`:
```js
import { Head, Link } from '@inertiajs/vue3';
```
Insert between `</SignalChain>`/`<SignalChain .../>` (line 12) and `<TrackerPanel ... />` (line 15):
```html
            <!-- Region 1b: Live camera link -->
            <Link href="/admin/stream" class="tech-camlink">
                <span class="tech-camlink__ico" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/></svg>
                </span>
                <span class="tech-camlink__text">
                    <span class="tech-camlink__title">Live Camera</span>
                    <span class="tech-camlink__sub">Open the broadcast camera feed</span>
                </span>
                <span class="tech-camlink__arrow" aria-hidden="true">→</span>
            </Link>
```
Add to the `<style scoped>` block:
```css
.tech-camlink {
    display: flex;
    align-items: center;
    gap: 14px;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: 14px;
    padding: 16px 20px;
    margin-bottom: 16px;
    text-decoration: none;
    color: var(--color-text-primary);
    transition: border-color 0.15s, box-shadow 0.15s;
}
.tech-camlink:hover {
    border-color: var(--color-scarlet);
    box-shadow: 0 2px 12px oklch(0.5 0.1 25 / 0.08);
}
.tech-camlink__ico { color: var(--color-scarlet); display: flex; }
.tech-camlink__text { display: flex; flex-direction: column; gap: 2px; }
.tech-camlink__title { font-size: 14px; font-weight: 700; }
.tech-camlink__sub { font-size: 12px; color: var(--color-text-dim); }
.tech-camlink__arrow { margin-left: auto; color: var(--color-text-dim); font-size: 18px; }
```

- [ ] **Step 10: Build + manual verification**

Run: `npm run build`
Then `npm run dev`: visit `/admin/dash/tech`, click **Live Camera** → `/admin/stream`, confirm the video stage renders with the connecting/waiting overlay (live video appears if the HLS feed is up).

- [ ] **Step 11: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "feat(admin): camera-only Stream page linked from Tech + sidebar"
```

---

## Task 7: Live value + age on the Data list

**Files:**
- Modify: `app/Http/Controllers/Admin/DataController.php` (create with `current` first)
- Modify: `routes/web.php` (add `admin.data.current`)
- Modify: `resources/js/Pages/Admin/Data.vue` (fetch + render value/age, link to explorer)
- Test: `tests/Feature/Admin/DataExplorerTest.php`

**Interfaces:**
- Consumes: `App\Services\CanonicalReader::readMany(array $keys): array` → `['key' => ['value'=>float,'unit'=>string,'timestamp'=>int,'age'=>int,'stale'=>bool,'resolved_source'=>string]|null]`.
- Produces: `GET /admin/data/current` → JSON `{ [key]: { value:number, unit:string, age_s:number, stale:bool } | null }`. `DataController::current(CanonicalReader $reader): JsonResponse`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Admin/DataExplorerTest.php`:
```php
<?php

namespace Tests\Feature\Admin;

use App\Models\CanonicalMetric;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DataExplorerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Keep CanonicalReader from making real network calls; an empty
        // Prometheus result makes reads resolve to null/[] without error.
        Http::fake(['*' => Http::response(['status' => 'success', 'data' => ['result' => []]], 200)]);
    }

    public function test_current_endpoint_returns_value_age_shape_per_metric(): void
    {
        CanonicalMetric::create([
            'key' => 'wind_speed', 'label' => 'Wind Speed',
            'storage_unit' => 'm/s', 'display_unit' => 'kn',
            'staleness_threshold_s' => 60, 'enabled' => true,
        ]);

        $this->actingAs(User::factory()->create())
            ->getJson(route('admin.data.current'))
            ->assertOk()
            ->assertJsonStructure(['wind_speed']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=test_current_endpoint_returns_value_age_shape_per_metric`
Expected: FAIL — `route('admin.data.current')` not defined.

- [ ] **Step 3: Create DataController with `current()`**

Create `app/Http/Controllers/Admin/DataController.php`:
```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CanonicalMetric;
use App\Services\CanonicalReader;
use Illuminate\Http\JsonResponse;

class DataController extends Controller
{
    public function current(CanonicalReader $reader): JsonResponse
    {
        $metrics = CanonicalMetric::where('enabled', true)->get(['key', 'display_unit']);
        $envelopes = $reader->readMany($metrics->pluck('key')->all());

        $out = [];
        foreach ($metrics as $metric) {
            $envelope = $envelopes[$metric->key] ?? null;
            $out[$metric->key] = $envelope === null ? null : [
                'value' => $envelope['value'],
                'unit' => $metric->display_unit,
                'age_s' => $envelope['age'],
                'stale' => $envelope['stale'],
            ];
        }

        return response()->json($out);
    }
}
```

- [ ] **Step 4: Register the route**

In `routes/web.php`, add `use App\Http\Controllers\Admin\DataController;` and, in the catalog block **before** any `data/{metric}` route, add:
```php
    Route::get('data/current', [DataController::class, 'current'])->name('admin.data.current');
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --compact --filter=test_current_endpoint_returns_value_age_shape_per_metric`
Expected: PASS.

- [ ] **Step 6: Render value/age + explorer link in Data.vue**

In `resources/js/Pages/Admin/Data.vue`:

Add to `<script setup>` (alongside the existing inventory `onMounted`):
```js
import { Link } from '@inertiajs/vue3';

const liveValues = ref({});

onMounted(async () => {
    try {
        const response = await fetch(route('admin.data.current'), { headers: { Accept: 'application/json' } });
        if (response.ok) {
            liveValues.value = await response.json();
        }
    } catch {
        // Live values are advisory; the catalog still works without them.
    }
});

function liveValueDisplay(metric) {
    const live = liveValues.value[metric.key];
    if (!live || live.value == null) {
        return '—';
    }
    const num = Math.abs(live.value) >= 100 ? Math.round(live.value) : Number(live.value).toFixed(1);
    return `${num}${live.unit ? ' ' + live.unit : ''}`;
}

function liveAgeDisplay(metric) {
    const live = liveValues.value[metric.key];
    if (!live || live.age_s == null) {
        return '';
    }
    const s = Math.round(live.age_s);
    if (s < 60) { return `${s}s ago`; }
    if (s < 3600) { return `${Math.round(s / 60)}m ago`; }
    return `${Math.round(s / 3600)}h ago`;
}

function liveStale(metric) {
    return liveValues.value[metric.key]?.stale ?? false;
}
```
(Confirm `ref`, `computed`, `onMounted` are already imported at the top — extend the existing import line if needed.)

In the metrics table `<thead>`, add a header after the `Label` column:
```html
                            <th class="px-5 py-2.5 text-left text-[11px] font-semibold text-text-secondary uppercase tracking-wide">Live</th>
```

In the metric row, make the Label cell link to the explorer and add the Live cell right after it:
```html
                                <td class="px-5 py-3 text-[13px] font-medium">
                                    <Link :href="route('admin.data.show', { metric: metric.key })" class="text-text-primary hover:text-scarlet transition-colors">{{ metric.label }}</Link>
                                </td>
                                <td class="px-5 py-3 whitespace-nowrap" :class="{ 'opacity-45': liveStale(metric) }">
                                    <span class="text-[13px] font-semibold tabular-nums">{{ liveValueDisplay(metric) }}</span>
                                    <span v-if="liveAgeDisplay(metric)" class="text-[11px] text-text-dim ml-1.5">{{ liveAgeDisplay(metric) }}</span>
                                </td>
```
Update the empty-state / sources sub-row `colspan="6"` cells to `colspan="7"` to account for the new column.

- [ ] **Step 7: Build + manual verification**

Run: `npm run build`, then `npm run dev`. Open `/admin/data`: each metric row shows a live value + age (or `—`), label links to the explorer (404 until Task 8 — expected).

- [ ] **Step 8: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "feat(data): live value + age per metric on the Data list"
```

---

## Task 8: Series endpoint + explorer page scaffold

**Files:**
- Modify: `app/Http/Controllers/Admin/DataController.php` (add `series`, `show`)
- Modify: `routes/web.php` (add `admin.data.series`, `admin.data.show`)
- Create: `resources/js/Pages/Admin/MetricExplorer.vue` (scaffold: header, range pills, one chart)
- Modify: `resources/js/Layouts/AdminLayout.vue` (`Admin/MetricExplorer` breadcrumb)
- Test: `tests/Feature/Admin/DataExplorerTest.php`

**Interfaces:**
- Consumes: `CanonicalReader::readRange(string $key, ?string $duration, string $step): array` → `[['t'=>int,'v'=>float], …]`; `CanonicalReader::read(string $key): ?array` (envelope with `value`). `CanonicalMetric` model (`key,label,group,display_unit,enabled`).
- Produces:
  - `GET /admin/data/series?metrics=a,b&range=24h` → JSON `{ [key]: { key:string, label:string, unit:string, data:[{t:int,value:float}], current:number|null } }`.
  - `GET /admin/data/{metric}` → renders `Admin/MetricExplorer` with props `{ metricKey, metricLabel, metricUnit, catalog: [{key,label,group,display_unit}] }`.

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/Admin/DataExplorerTest.php`:
```php
    public function test_series_endpoint_returns_per_metric_shape(): void
    {
        CanonicalMetric::create([
            'key' => 'wind_speed', 'label' => 'Wind Speed',
            'storage_unit' => 'm/s', 'display_unit' => 'kn',
            'staleness_threshold_s' => 60, 'enabled' => true,
        ]);

        $this->actingAs(User::factory()->create())
            ->getJson(route('admin.data.series', ['metrics' => 'wind_speed', 'range' => '24h']))
            ->assertOk()
            ->assertJsonStructure(['wind_speed' => ['key', 'label', 'unit', 'data', 'current']])
            ->assertJsonPath('wind_speed.key', 'wind_speed');
    }

    public function test_explorer_page_renders_with_catalog(): void
    {
        CanonicalMetric::create([
            'key' => 'wind_speed', 'label' => 'Wind Speed',
            'storage_unit' => 'm/s', 'display_unit' => 'kn',
            'staleness_threshold_s' => 60, 'enabled' => true,
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('admin.data.show', ['metric' => 'wind_speed']))
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $p) => $p
                ->component('Admin/MetricExplorer')
                ->where('metricKey', 'wind_speed')
                ->where('metricLabel', 'Wind Speed')
                ->has('catalog', 1));
    }

    public function test_explorer_unknown_metric_is_404(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.data.show', ['metric' => 'nope_not_here']))
            ->assertNotFound();
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Admin/DataExplorerTest.php`
Expected: the three new tests FAIL — routes not defined.

- [ ] **Step 3: Add `series()` and `show()` to DataController**

Add to `app/Http/Controllers/Admin/DataController.php` (add the imports `use App\Services\CanonicalReader;` already present; add `use Illuminate\Http\Request;`, `use Inertia\Inertia;`, `use Inertia\Response;`):
```php
    private const RANGE_STEPS = [
        '6h' => '120s',
        '24h' => '300s',
        '7d' => '1800s',
        '30d' => '7200s',
    ];

    public function show(string $metric): Response
    {
        $target = CanonicalMetric::where('key', $metric)->firstOrFail();

        return Inertia::render('Admin/MetricExplorer', [
            'metricKey' => $target->key,
            'metricLabel' => $target->label,
            'metricUnit' => $target->display_unit,
            'catalog' => CanonicalMetric::where('enabled', true)
                ->orderBy('group')->orderBy('label')
                ->get(['key', 'label', 'group', 'display_unit']),
        ]);
    }

    public function series(Request $request, CanonicalReader $reader): JsonResponse
    {
        $validated = $request->validate([
            'metrics' => ['required', 'string'],
            'range' => ['nullable', 'string'],
        ]);

        $range = $validated['range'] ?? '24h';
        if (! array_key_exists($range, self::RANGE_STEPS)) {
            $range = '24h';
        }
        $step = self::RANGE_STEPS[$range];

        $keys = array_values(array_filter(array_map('trim', explode(',', $validated['metrics']))));
        $metrics = CanonicalMetric::whereIn('key', $keys)->get()->keyBy('key');

        $out = [];
        foreach ($keys as $key) {
            $metric = $metrics->get($key);
            if ($metric === null) {
                continue;
            }
            $points = $reader->readRange($key, $range, $step);
            $current = $reader->read($key);
            $out[$key] = [
                'key' => $key,
                'label' => $metric->label,
                'unit' => $metric->display_unit,
                'data' => array_map(fn (array $point): array => ['t' => $point['t'], 'value' => $point['v']], $points),
                'current' => $current['value'] ?? null,
            ];
        }

        return response()->json($out);
    }
```

- [ ] **Step 4: Register the routes (literals before the wildcard)**

In `routes/web.php`, in the catalog block, add `series` next to `current` and add the `show` wildcard **after** all literal `data/*` GET routes:
```php
    Route::get('data/series', [DataController::class, 'series'])->name('admin.data.series');
    Route::get('data/{metric}', [DataController::class, 'show'])->name('admin.data.show')->where('metric', '[A-Za-z0-9_]+');
```
Ensure the final GET ordering is: `data`, `data/inventory`, `data/current`, `data/series`, then `data/{metric}`.

- [ ] **Step 5: Create the explorer scaffold (single chart, no uPlot yet)**

Create `resources/js/Pages/Admin/MetricExplorer.vue`:
```vue
<template>
    <AdminLayout>
        <Head :title="`Data · ${metricLabel}`" />

        <div class="flex items-center justify-between mb-5">
            <div>
                <h1 class="text-[22px] font-bold">{{ metricLabel }}</h1>
                <p class="text-[13px] text-text-secondary mt-0.5">
                    <code class="font-mono">{{ metricKey }}</code>
                    <span v-if="metricUnit"> · {{ metricUnit }}</span>
                </p>
            </div>
            <div class="flex gap-1">
                <button
                    v-for="r in ranges"
                    :key="r"
                    type="button"
                    class="range-pill"
                    :class="{ 'range-pill--active': range === r }"
                    @click="range = r"
                >{{ r }}</button>
            </div>
        </div>

        <div class="panel p-4">
            <div v-if="loading" class="explorer-skeleton"></div>
            <pre v-else class="text-[11px] text-text-dim overflow-x-auto">{{ debugSummary }}</pre>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    metricKey: { type: String, required: true },
    metricLabel: { type: String, default: '' },
    metricUnit: { type: String, default: '' },
    catalog: { type: Array, default: () => [] },
});

const ranges = ['6h', '24h', '7d', '30d'];
const range = ref('24h');
const loading = ref(false);
const seriesData = ref({});

async function fetchSeries() {
    loading.value = true;
    try {
        const url = route('admin.data.series', { metrics: props.metricKey, range: range.value });
        const response = await fetch(url, { headers: { Accept: 'application/json' } });
        seriesData.value = response.ok ? await response.json() : {};
    } catch {
        seriesData.value = {};
    } finally {
        loading.value = false;
    }
}

const debugSummary = computed(() => {
    const s = seriesData.value[props.metricKey];
    if (!s) { return 'No data'; }
    return `${s.label} (${s.unit}) — ${s.data.length} points, current ${s.current ?? '—'}`;
});

watch(range, fetchSeries);
onMounted(fetchSeries);
</script>

<style scoped>
.range-pill {
    font-size: 11px;
    font-weight: 700;
    padding: 5px 12px;
    border-radius: 6px;
    border: 1px solid var(--color-border);
    background: transparent;
    color: var(--color-text-dim);
    cursor: pointer;
    transition: all 0.15s;
}
.range-pill--active {
    background: var(--color-scarlet);
    border-color: var(--color-scarlet);
    color: white;
}
.explorer-skeleton {
    height: 260px;
    border-radius: 8px;
    background: linear-gradient(90deg, var(--color-bg) 25%, var(--color-border-light) 50%, var(--color-bg) 75%);
    background-size: 200% 100%;
    animation: explorer-shimmer 1.4s ease-in-out infinite;
}
@keyframes explorer-shimmer {
    from { background-position: 200% 0; }
    to { background-position: -200% 0; }
}
@media (prefers-reduced-motion: reduce) {
    .explorer-skeleton { animation: none; }
}
</style>
```

- [ ] **Step 6: Add the breadcrumb**

In `resources/js/Layouts/AdminLayout.vue` `breadcrumbMap`, add:
```js
    'Admin/MetricExplorer': [home, { label: 'Data', href: '/admin/data' }, { label: 'Metric' }],
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Admin/DataExplorerTest.php`
Expected: PASS (all five).

- [ ] **Step 8: Build + manual check**

Run `npm run build`, then `npm run dev`: from `/admin/data`, click a metric → `/admin/data/<key>`; range pills switch; the debug summary shows point count.

- [ ] **Step 9: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "feat(data): series endpoint + metric explorer page scaffold"
```

---

## Task 9: uPlot chart + autocomplete + add/overlay graphs

**Files:**
- Create: `resources/js/components/Admin/UplotChart.vue`
- Create: `resources/js/components/Admin/MetricSelect.vue`
- Modify: `resources/js/Pages/Admin/MetricExplorer.vue` (replace the debug panel with real graphs + controls)

**Interfaces:**
- Consumes: `seriesData` from `admin.data.series` (see Task 8 shape); `catalog` prop `[{key,label,group,display_unit}]`; `uplot` default export + `uplot/dist/uPlot.min.css`.
- Produces: a working Grafana-style explorer — stacked graph panels, each with a left-axis primary series and optional right-axis overlay; typeahead metric picker; shared range; drag-to-zoom.

- [ ] **Step 1: Build the uPlot wrapper**

Create `resources/js/components/Admin/UplotChart.vue`:
```vue
<template>
    <div ref="hostRef" class="uplot-host"></div>
</template>

<script setup>
import { ref, shallowRef, onMounted, onBeforeUnmount, watch, nextTick } from 'vue';
import uPlot from 'uplot';
import 'uplot/dist/uPlot.min.css';

const props = defineProps({
    // series: [{ key, label, unit, axis: 'left'|'right', color, data: [{t, value}] }]
    series: { type: Array, default: () => [] },
    height: { type: Number, default: 260 },
});

const hostRef = ref(null);
const chart = shallowRef(null);
let resizeObserver = null;

function cssVar(name, fallback) {
    const v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    return v || fallback;
}

/** Merge all series onto one shared, sorted timestamp axis. */
function toAlignedData(series) {
    const times = new Set();
    for (const s of series) {
        for (const p of s.data) { times.add(p.t); }
    }
    const xs = [...times].sort((a, b) => a - b);
    const index = new Map(xs.map((t, i) => [t, i]));
    const cols = series.map((s) => {
        const col = new Array(xs.length).fill(null);
        for (const p of s.data) { col[index.get(p.t)] = p.value; }
        return col;
    });
    return [xs, ...cols];
}

function buildOptions() {
    const axisColor = cssVar('--color-text-dim', '#888');
    const gridColor = cssVar('--color-border-light', '#eee');
    const hasRight = props.series.some((s) => s.axis === 'right');
    const palette = ['var(--color-scarlet)', 'var(--color-teal)', 'var(--color-blue)', 'var(--color-amber)', 'var(--color-green)'];

    const axes = [
        { stroke: axisColor, grid: { stroke: gridColor, width: 1 }, ticks: { stroke: gridColor } },
        { scale: 'left', stroke: axisColor, grid: { stroke: gridColor, width: 1 },
          label: leftLabel(), labelSize: 24 },
    ];
    if (hasRight) {
        axes.push({ scale: 'right', side: 1, stroke: axisColor, grid: { show: false },
            label: rightLabel(), labelSize: 24 });
    }

    const uSeries = [{}];
    props.series.forEach((s, i) => {
        uSeries.push({
            label: `${s.label}${s.unit ? ' (' + s.unit + ')' : ''}`,
            stroke: s.color || palette[i % palette.length],
            width: 2,
            scale: s.axis === 'right' ? 'right' : 'left',
            points: { show: false },
        });
    });

    return {
        width: hostRef.value.clientWidth,
        height: props.height,
        scales: { x: { time: true }, left: {}, ...(hasRight ? { right: {} } : {}) },
        axes,
        series: uSeries,
        legend: { show: true },
        cursor: { drag: { x: true, y: false } },
    };
}

function leftLabel() {
    const left = props.series.filter((s) => s.axis !== 'right');
    return left.length ? (left[0].unit || left[0].label) : '';
}

function rightLabel() {
    const right = props.series.filter((s) => s.axis === 'right');
    return right.length ? (right[0].unit || right[0].label) : '';
}

function render() {
    if (chart.value) { chart.value.destroy(); chart.value = null; }
    if (!hostRef.value || props.series.length === 0) { return; }
    const data = toAlignedData(props.series);
    chart.value = new uPlot(buildOptions(), data, hostRef.value);
}

onMounted(async () => {
    await nextTick();
    render();
    resizeObserver = new ResizeObserver(() => {
        if (chart.value && hostRef.value) {
            chart.value.setSize({ width: hostRef.value.clientWidth, height: props.height });
        }
    });
    resizeObserver.observe(hostRef.value);
});

watch(() => props.series, render, { deep: true });

onBeforeUnmount(() => {
    resizeObserver?.disconnect();
    chart.value?.destroy();
    chart.value = null;
});
</script>

<style scoped>
.uplot-host { width: 100%; }
:deep(.u-legend) { font-size: 12px; }
</style>
```

- [ ] **Step 2: Build the typeahead metric picker**

Create `resources/js/components/Admin/MetricSelect.vue`:
```vue
<template>
    <div class="ms-wrap" @keydown.escape="close">
        <input
            ref="inputRef"
            v-model="query"
            type="text"
            class="field-input"
            :placeholder="placeholder"
            autocomplete="off"
            @focus="open = true"
            @input="open = true; highlight = 0"
            @keydown.down.prevent="move(1)"
            @keydown.up.prevent="move(-1)"
            @keydown.enter.prevent="choose(filtered[highlight])"
        />
        <ul v-if="open && filtered.length" class="ms-list">
            <li
                v-for="(m, i) in filtered"
                :key="m.key"
                class="ms-item"
                :class="{ 'ms-item--active': i === highlight }"
                @mousedown.prevent="choose(m)"
                @mouseenter="highlight = i"
            >
                <span class="ms-item__label">{{ m.label }}</span>
                <span class="ms-item__key">{{ m.key }}</span>
            </li>
        </ul>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    options: { type: Array, default: () => [] }, // [{key, label, group, display_unit}]
    placeholder: { type: String, default: 'Search metrics…' },
});
const emit = defineEmits(['select']);

const query = ref('');
const open = ref(false);
const highlight = ref(0);
const inputRef = ref(null);

const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();
    const list = q
        ? props.options.filter((m) => m.label.toLowerCase().includes(q) || m.key.toLowerCase().includes(q))
        : props.options;
    return list.slice(0, 50);
});

function move(dir) {
    if (!filtered.value.length) { return; }
    highlight.value = (highlight.value + dir + filtered.value.length) % filtered.value.length;
}

function choose(metric) {
    if (!metric) { return; }
    emit('select', metric);
    query.value = '';
    open.value = false;
}

function close() {
    open.value = false;
}
</script>

<style scoped>
.ms-wrap { position: relative; }
.ms-list {
    position: absolute;
    z-index: 30;
    top: calc(100% + 4px);
    left: 0;
    right: 0;
    max-height: 280px;
    overflow-y: auto;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: 8px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
    padding: 4px;
}
.ms-item {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    gap: 10px;
    padding: 7px 10px;
    border-radius: 5px;
    cursor: pointer;
}
.ms-item--active { background: var(--color-bg); }
.ms-item__label { font-size: 13px; font-weight: 500; color: var(--color-text-primary); }
.ms-item__key { font-size: 11px; font-family: var(--font-mono, monospace); color: var(--color-text-dim); }
</style>
```

- [ ] **Step 3: Wire panels + add/overlay controls into MetricExplorer**

Replace the `<script setup>` body and the chart panel markup in `resources/js/Pages/Admin/MetricExplorer.vue` so it manages a list of graph panels. Final file:
```vue
<template>
    <AdminLayout>
        <Head :title="`Data · ${metricLabel}`" />

        <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
            <div>
                <h1 class="text-[22px] font-bold">{{ metricLabel }}</h1>
                <p class="text-[13px] text-text-secondary mt-0.5">
                    <code class="font-mono">{{ metricKey }}</code>
                    <span v-if="metricUnit"> · {{ metricUnit }}</span>
                </p>
            </div>
            <div class="flex gap-1">
                <button
                    v-for="r in ranges"
                    :key="r"
                    type="button"
                    class="range-pill"
                    :class="{ 'range-pill--active': range === r }"
                    @click="range = r"
                >{{ r }}</button>
            </div>
        </div>

        <div v-for="(panel, pi) in panels" :key="panel.id" class="panel p-4 mb-4">
            <div class="flex items-center justify-between mb-3 flex-wrap gap-2">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-[12px] font-semibold text-text-secondary">{{ panelTitle(panel) }}</span>
                    <button
                        v-if="panel.overlayKey"
                        type="button"
                        class="text-[11px] text-error hover:underline"
                        @click="removeOverlay(pi)"
                    >remove overlay</button>
                </div>
                <div class="flex items-center gap-2">
                    <div v-if="!panel.overlayKey" class="w-56">
                        <MetricSelect :options="overlayOptions(panel)" placeholder="+ Overlay metric…" @select="(m) => setOverlay(pi, m)" />
                    </div>
                    <button v-if="pi > 0" type="button" class="text-[12px] text-text-dim hover:text-error" @click="removePanel(pi)">✕</button>
                </div>
            </div>

            <div v-if="panel.loading" class="explorer-skeleton"></div>
            <UplotChart v-else-if="panel.series.length" :series="panel.series" :height="260" />
            <p v-else class="text-[13px] text-text-dim py-10 text-center">No data for this range.</p>
        </div>

        <div class="flex items-center gap-3 flex-wrap">
            <div class="w-72">
                <MetricSelect :options="catalog" placeholder="+ Add graph for metric…" @select="addPanel" />
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import UplotChart from '@/components/Admin/UplotChart.vue';
import MetricSelect from '@/components/Admin/MetricSelect.vue';

const props = defineProps({
    metricKey: { type: String, required: true },
    metricLabel: { type: String, default: '' },
    metricUnit: { type: String, default: '' },
    catalog: { type: Array, default: () => [] },
});

const ranges = ['6h', '24h', '7d', '30d'];
const range = ref('24h');
let nextId = 1;

const panels = ref([{ id: nextId++, primaryKey: props.metricKey, overlayKey: null, loading: false, series: [] }]);

async function fetchSeriesFor(keys) {
    const url = route('admin.data.series', { metrics: keys.join(','), range: range.value });
    const response = await fetch(url, { headers: { Accept: 'application/json' } });
    return response.ok ? await response.json() : {};
}

function metaFor(key) {
    return props.catalog.find((m) => m.key === key) || { label: key, display_unit: '' };
}

async function loadPanel(panel) {
    panel.loading = true;
    try {
        const keys = [panel.primaryKey, panel.overlayKey].filter(Boolean);
        const json = await fetchSeriesFor(keys);
        panel.series = keys.map((key, i) => {
            const s = json[key];
            return {
                key,
                label: s?.label ?? metaFor(key).label,
                unit: s?.unit ?? metaFor(key).display_unit,
                axis: i === 0 ? 'left' : 'right',
                data: s?.data ?? [],
            };
        }).filter((s) => s.data.length);
    } finally {
        panel.loading = false;
    }
}

function reloadAll() {
    panels.value.forEach(loadPanel);
}

function panelTitle(panel) {
    const primary = metaFor(panel.primaryKey).label;
    if (!panel.overlayKey) { return primary; }
    return `${primary} + ${metaFor(panel.overlayKey).label}`;
}

function overlayOptions(panel) {
    return props.catalog.filter((m) => m.key !== panel.primaryKey);
}

function addPanel(metric) {
    const panel = { id: nextId++, primaryKey: metric.key, overlayKey: null, loading: false, series: [] };
    panels.value.push(panel);
    loadPanel(panel);
}

function removePanel(index) {
    panels.value.splice(index, 1);
}

function setOverlay(index, metric) {
    panels.value[index].overlayKey = metric.key;
    loadPanel(panels.value[index]);
}

function removeOverlay(index) {
    panels.value[index].overlayKey = null;
    loadPanel(panels.value[index]);
}

watch(range, reloadAll);
onMounted(reloadAll);
</script>

<style scoped>
.range-pill {
    font-size: 11px;
    font-weight: 700;
    padding: 5px 12px;
    border-radius: 6px;
    border: 1px solid var(--color-border);
    background: transparent;
    color: var(--color-text-dim);
    cursor: pointer;
    transition: all 0.15s;
}
.range-pill--active {
    background: var(--color-scarlet);
    border-color: var(--color-scarlet);
    color: white;
}
.explorer-skeleton {
    height: 260px;
    border-radius: 8px;
    background: linear-gradient(90deg, var(--color-bg) 25%, var(--color-border-light) 50%, var(--color-bg) 75%);
    background-size: 200% 100%;
    animation: explorer-shimmer 1.4s ease-in-out infinite;
}
@keyframes explorer-shimmer {
    from { background-position: 200% 0; }
    to { background-position: -200% 0; }
}
@media (prefers-reduced-motion: reduce) {
    .explorer-skeleton { animation: none; }
}
</style>
```

- [ ] **Step 4: Build**

Run: `npm run build`
Expected: builds cleanly (uPlot + CSS import resolve).

- [ ] **Step 5: Re-run the explorer feature tests (no regression)**

Run: `php artisan test --compact tests/Feature/Admin/DataExplorerTest.php`
Expected: PASS (the page still renders `Admin/MetricExplorer` with the catalog prop).

- [ ] **Step 6: Manual verification**

`npm run dev` → `/admin/data` → click a metric. Confirm: the primary graph renders; range pills refetch; **+ Overlay metric** adds a right-axis series with its own labelled axis; **+ Add graph** stacks another panel via autocomplete; drag-select zooms; double-click resets; removing panels/overlays works; lines are crisp in light/dark/night themes (toggle via the top bar).

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat(data): uPlot explorer with autocomplete, stacked graphs, overlay axes"
```

---

## Task 10: Sidebar sectioning

**Files:**
- Modify: `resources/js/Layouts/AdminLayout.vue` (wrap nav links in three labelled sections)

**Interfaces:**
- Consumes: the nav links already present (Stream/Data added in earlier tasks).
- Produces: visual grouping only — VOYAGE / DASHBOARDS / SYSTEM headers.

- [ ] **Step 1: Restructure the `<nav>` into sections**

In `resources/js/Layouts/AdminLayout.vue`, replace the `<nav>` body (the links between `<nav …>` and the trailing `<div class="flex-1"></div>`) with:
```html
                <div class="nav-section">Voyage</div>
                <NavLink href="/admin" icon="home" :active="currentPage === 'Admin/Dashboard'" @click="sidebarOpen = false">Dashboard</NavLink>
                <NavLink href="/admin/journeys" icon="compass" :active="currentPage?.startsWith('Admin/Journey')" @click="sidebarOpen = false">Journeys</NavLink>
                <NavLink href="/admin/log" icon="clipboard" :active="currentPage === 'Admin/Log'" @click="sidebarOpen = false">Ship's Log</NavLink>
                <NavLink href="/admin/planner" icon="route" :active="currentPage?.startsWith('Admin/Planner')" @click="sidebarOpen = false">Planner</NavLink>
                <NavLink href="/admin/tracks" icon="map" :active="currentPage === 'Admin/Tracks'" @click="sidebarOpen = false">Tracks</NavLink>

                <div class="nav-section">Dashboards</div>
                <NavLink href="/admin/dash/main" icon="globe" :active="currentPage === 'Admin/Dash/Main'" @click="sidebarOpen = false">Main</NavLink>
                <NavLink href="/admin/dash/tech" icon="cpu" :active="currentPage === 'Admin/Dash/Tech'" @click="sidebarOpen = false">Tech</NavLink>
                <NavLink href="/admin/dash/ops" icon="anchor" :active="currentPage === 'Admin/Dash/Ops'" @click="sidebarOpen = false">Ops</NavLink>
                <NavLink href="/admin/dash/skipper" icon="navigation" :active="currentPage === 'Admin/Dash/Skipper'" @click="sidebarOpen = false">Skipper</NavLink>
                <NavLink href="/admin/stream" icon="video" :active="currentPage === 'Admin/Stream'" @click="sidebarOpen = false">Stream</NavLink>

                <div class="nav-section">System</div>
                <NavLink href="/admin/data" icon="layers" :active="currentPage === 'Admin/Data'" @click="sidebarOpen = false">Data</NavLink>
                <NavLink href="/admin/settings" icon="settings" :active="currentPage === 'Admin/Settings'" @click="sidebarOpen = false">Settings</NavLink>
                <NavLink href="/admin/team" icon="users" :active="currentPage === 'Admin/Team'" @click="sidebarOpen = false">Team</NavLink>
```
(Note: this assumes `navigation` is a valid icon already used by Skipper — keep whatever icon it currently uses. If `navigation` has no branch in `NavLink.vue`, leave Skipper's existing icon value unchanged.)

- [ ] **Step 2: Replace the old `.nav-divider` style with a `.nav-section` style**

In the `<style scoped>` block of `AdminLayout.vue`, replace the `.nav-divider { … }` rule with:
```css
.nav-section {
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--color-text-dim);
    padding: 14px 12px 4px;
}
.nav-section:first-child { padding-top: 4px; }
```

- [ ] **Step 3: Build + manual check**

Run `npm run build`, then `npm run dev`. Confirm the sidebar shows three labelled groups in order, active states still highlight, and the mobile drawer still closes on navigation.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Layouts/AdminLayout.vue
git commit -m "feat(admin): section the sidebar into Voyage / Dashboards / System"
```

---

## Task 11: Ops weather de-duplication + battery alignment

**Files:**
- Modify: `resources/js/Pages/Admin/Dash/Ops.vue` (Atmosphere/Sailing rows, `windDisplay`→`windSpeedDisplay`, battery min-heights)
- Test: `tests/Feature/Admin/AdminNavOverhaulTest.php` (Ops still renders)

**Interfaces:**
- Consumes: existing `val()`/`stale()` helpers and `gustDisplay`/`windDirDisplay`/`pressureDisplay` computeds.
- Produces: no API change. Atmosphere shows Wind (speed-only) · Direction · Gust · Pressure; Sailing & Nav drops its Wind row; battery cells share a vertical rhythm.

- [ ] **Step 1: Add a speed-only wind computed and remove the old `windDisplay`**

In `resources/js/Pages/Admin/Dash/Ops.vue` `<script setup>`, replace the `windDisplay` computed (lines ~521-527) with a speed-only version:
```js
const windSpeedDisplay = computed(() => {
    const spd = val('wx_wind_speed');
    return spd == null ? '—' : `${fmtInt(spd)} kts`;
});
```
(`gustDisplay` already exists and stays — it is the single home for gust.)

- [ ] **Step 2: Rebuild the Atmosphere column (Wind speed · Direction · Gust · Pressure)**

Replace the Atmosphere column rows (the `.ops-wcol--atmos` block, lines ~227-245) with:
```html
                    <!-- Atmosphere -->
                    <div class="ops-wcol ops-wcol--atmos">
                        <div class="ops-wcl">Atmosphere</div>
                        <div class="ops-wcol__row">
                            <span class="ops-wcol__l">Wind</span>
                            <span class="ops-wcol__v ops-wcol__v--amber" :class="{ 'ops-stale': stale('wx_wind_speed') }">{{ windSpeedDisplay }}</span>
                        </div>
                        <div class="ops-wcol__row">
                            <span class="ops-wcol__l">Direction</span>
                            <span class="ops-wcol__v" :class="{ 'ops-stale': stale('wx_wind_dir') }">{{ windDirDisplay }}</span>
                        </div>
                        <div class="ops-wcol__row">
                            <span class="ops-wcol__l">Gust</span>
                            <span class="ops-wcol__v" :class="{ 'ops-stale': stale('wx_wind_gust') }">{{ gustDisplay }}</span>
                        </div>
                        <div class="ops-wcol__row">
                            <span class="ops-wcol__l">Pressure</span>
                            <span class="ops-wcol__v ops-wcol__v--blue" :class="{ 'ops-stale': stale('wx_pressure') }">{{ pressureDisplay }}</span>
                        </div>
                    </div>
```

- [ ] **Step 3: Remove the duplicate Wind row from Sailing & Navigation**

In the `.ops-saildata` → `.ops-sdrows` block (lines ~303-328), delete the entire Wind `<div class="ops-sdrow">` (the one with label `Wind` and `windDisplay`, lines ~316-319). The remaining rows are SOG, Heading, COG, Depth, ETA.

- [ ] **Step 4: Confirm `windDisplay` is fully removed**

Run: `grep -n "windDisplay" resources/js/Pages/Admin/Dash/Ops.vue`
Expected: no matches (only `windSpeedDisplay`, `windKtsShort`, `wxWindDisplay` remain). If a stray `windDisplay` reference exists, replace it with `windSpeedDisplay`.

- [ ] **Step 5: Align the House and EcoFlow battery cells**

In the Ops `<style scoped>` block, add shared min-heights so both cells' charts/axes line up (uPlot/TrendChart baselines render level thanks to Task 1). Add after the `.ops-sys__header` rule:
```css
.ops-sys__header { min-height: 20px; }
.ops-sys__main { min-height: 46px; }
.ops-sys__det { min-height: 18px; }
```

- [ ] **Step 6: Add an Ops render test**

Add to `tests/Feature/Admin/AdminNavOverhaulTest.php`:
```php
    public function test_ops_dashboard_still_renders(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.dash.ops'))
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $p) => $p->component('Admin/Dash/Ops'));
    }
```

- [ ] **Step 7: Run the test**

Run: `php artisan test --compact --filter=test_ops_dashboard_still_renders`
Expected: PASS.

- [ ] **Step 8: Build + manual verification**

Run `npm run build`, then `npm run dev` → `/admin/dash/ops`. Confirm: wind appears only in Atmosphere (speed) with a single Gust row; Sailing & Navigation has no Wind row; House and EcoFlow battery SOC/detail/chart/axis rows line up across both columns.

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "fix(ops): de-duplicate wind/gust and align battery cells"
```

---

## Task 12: Full-suite verification

**Files:** none (verification only)

- [ ] **Step 1: Run the full backend test suite**

Run: `php artisan test --compact`
Expected: all tests PASS (no regressions from route renames/removals).

- [ ] **Step 2: Production build**

Run: `npm run build`
Expected: clean build.

- [ ] **Step 3: Pint check across touched PHP**

Run: `vendor/bin/pint --dirty --format agent`
Expected: no further changes (already formatted).

- [ ] **Step 4: Final manual smoke**

`npm run dev` and click through: sidebar sections; Data list (value/age + links); metric explorer (add graph, overlay axis, range, zoom); Stream from Tech; Ops layout; Tech/Ops crisp charts. Confirm no console errors and no links to removed pages.

---

## Self-Review

**Spec coverage:**
- A. Navigation cleanup & sectioning → Tasks 2-6 (link removals/additions) + Task 10 (sections). ✓
- B. Remove Tracker/Broadcast/Environment → Tasks 2, 3, 4. ✓
- C. Stream page → Task 6. ✓
- D1. Ops weather de-dup → Task 11. ✓
- D2. Battery alignment → Task 11 + Task 1 (crisp baselines). ✓
- E1. Rename Data → Task 5. ✓
- E2. Live value + age → Task 7. ✓
- E3. Explorer page (series/show, uPlot, autocomplete, add/overlay) → Tasks 8, 9. ✓
- F. Crisp SVG lines → Task 1 (LCARS already done; excluded). ✓
- Testing section → backend feature tests in Tasks 2-11 + Task 12 suite run. ✓

**Type consistency:** `series` endpoint returns object keyed by metric key with `{key,label,unit,data:[{t,value}],current}` — consumed identically in Task 9 `loadPanel`. `current` returns `{value,unit,age_s,stale}` — consumed by `liveValueDisplay/liveAgeDisplay/liveStale` in Task 7. `useVideoFeed` destructure matches its real return (`videoActive,videoChecked,connect`). uPlot series prop shape `{key,label,unit,axis,data}` produced by `loadPanel` and consumed by `UplotChart`. Route names consistent: `admin.data.{index,store,inventory,test,rollback,update,destroy,current,series,show}`.

**Placeholder scan:** No TBD/TODO; every code step shows complete code; tests included with expected pass/fail.

**Note for executor:** Vue template/CSS-only changes (Ops layout, sidebar sectioning, explorer interactions) have no JS test runner here — they are covered by controller/route render tests plus the manual-verification steps. Do not skip the manual steps.
