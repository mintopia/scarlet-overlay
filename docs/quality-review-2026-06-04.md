# Scarlet Overlay — Quality Review

**Date:** 4 June 2026
**Methodology:** 8 expert subagents (Architecture, Laravel, Code Quality, Security, Maritime, DevOps, Data/Metrics, Frontend) reviewed the full codebase in parallel, followed by a synthesis agent that deduplicated and prioritised findings.

**Result:** 179 raw findings deduplicated to **86 unique issues** — 7 critical, 22 high, 39 medium, 18 low.

---

## Executive Summary

The Scarlet Overlay is a well-architected Laravel 12 sailing telemetry application with clean domain modelling, correct maritime calculations, and a thoughtful config-driven metric registry. However, the review uncovered several critical issues requiring immediate attention: a broken API endpoint, missing ownership authorisation on the Planner feature, production secrets committed to git, no restart policies or scheduler container in Docker, and no CI quality gates before images are pushed.

Beyond these, the codebase has significant code duplication (haversine, null-island check, and true wind calculation each repeated 3-8 times), operator precedence bugs in weather data parsing, and frontend performance concerns from eager-loading all pages into a single bundle.

---

## Critical (7)

### 1. Missing ownership authorisation on Plan/PlanGroup/PlanRoute CRUD
**File:** `app/Http/Controllers/Admin/PlannerController.php` (lines 49-117)
**Flagged by:** Architecture, Code Quality, Security, Laravel

PlannerController's `show()`, `update()`, `destroy()`, `share()`, and `unshare()` accept a Plan via route model binding but never verify the authenticated user owns the plan. Any authenticated user can view, modify, delete, or share any other user's plans by guessing slugs. The same gap exists in PlanGroupController and PlanRouteController.

**Fix:** Create a `PlanPolicy` checking `$plan->user_id === $user->id`, apply `$this->authorize()` in each controller method. Apply ownership verification in PlanGroupController and PlanRouteController by traversing `$group->plan->user_id` and `$route->group->plan->user_id`.

---

### 2. Production secrets committed to git
**File:** `docker/production/production.env` (lines 1-51)
**Flagged by:** DevOps, Security

Contains hardcoded credentials: `DB_PASSWORD=secret`, `REVERB_APP_KEY=scarlet-key`, `REVERB_APP_SECRET=scarlet-secret`. This file is COPY'd into the Docker image at build time.

**Fix:** `git rm --cached docker/production/production.env`, add to `.gitignore`, inject secrets via Docker secrets or orchestrator environment variables.

---

### 3. Broken `/api/v1/metrics` endpoint
**File:** `app/Http/Controllers/Api/V1/ApiController.php` (lines 34-37)
**Flagged by:** Architecture, Code Quality, Laravel

The `metrics()` method calls `$metricsService->getMetrics()`, but `MetricsService` has no method named `getMetrics()`. This throws a `BadMethodCallException` at runtime.

**Fix:** Change to `$metricsService->getAllMetrics()` or create a dedicated `getMetrics()` method. Add a test for this endpoint.

---

### 4. Weather type-cast operator precedence bug
**File:** `app/Services/WeatherService.php` (lines 86-98)
**Flagged by:** Code Quality, Architecture, Maritime, Laravel

Lines like `(float) $forecast['current']['temperature_2m'] ?? null` evaluate as `((float) $value) ?? null`. The cast converts `null` to `0.0`, so the `??` fallback never triggers. Affects 10+ fields. Missing API values silently become 0 instead of null, making 0-degree water temperature indistinguishable from "no data".

**Fix:** Use `isset()` checks or restructure as: `isset($value) ? (float) $value : null`.

---

### 5. MetricsExtractCommand references non-existent config paths
**File:** `app/Console/Commands/MetricsExtractCommand.php` (lines 178-188)
**Flagged by:** Architecture, Data

`buildQueryList()` iterates over `config('scarlet.metrics.mappings.boat')`, `config('scarlet.metrics.mappings.tracker')`, and `config('scarlet.metrics.mappings.gps')` — none of which exist. The foreach loops iterate over null, producing zero queries.

**Fix:** Rewrite `buildQueryList()` to derive queries from `config('scarlet.metrics.registry')` grouped by `config('scarlet.metrics.groups')`.

---

### 6. No restart policies on any Docker service
**File:** `docker-compose.yml` (lines 1-116)
**Flagged by:** DevOps

None of the 8+ services have a restart policy. If any container crashes, it stays down permanently.

**Fix:** Add `restart: unless-stopped` to every service definition.

---

### 7. No CI quality gates before pushing Docker images
**File:** `.github/workflows/publish-docker-images.yml` (lines 1-67)
**Flagged by:** DevOps

The workflow builds and pushes Docker images on every push to master/develop without running PHPUnit tests, Pint linting, or npm builds.

**Fix:** Add a test job that runs `php artisan test`, `vendor/bin/pint --test`, and `npm run build` before the Docker build/push step.

---

## High (22)

### Security & Auth

| # | Finding | File | Fix |
|---|---------|------|-----|
| 1 | API v1 routes have no auth or rate limiting — GPS position publicly exposed | `routes/api.php:7-13` | Add `throttle:60,1` middleware; consider `auth:sanctum` for GPS |
| 2 | `role` mass-assignable on User model — privilege escalation risk | `app/Models/User.php:15` | Remove `role` from `$fillable`, set explicitly |
| 3 | SRT URL setting enables SSRF via internal network requests | `app/Http/Controllers/SrtMetricsController.php:13-22` | Restrict settings to owner-only, validate URL against allowlist |
| 4 | No Laravel Policies anywhere; inline auth scattered across controllers | `app/Http/Controllers/Admin/TeamController.php:36-86` | Create Policies for Team, Plan, Settings |
| 5 | Prometheus metrics endpoints publicly accessible | `routes/web.php:41-42` | Protect with auth middleware or IP restrictions |

### Runtime Bugs

| # | Finding | File | Fix |
|---|---------|------|-----|
| 6 | MetricsFakeCommand dispatches MetricsUpdated with wrong argument count (missing `sun`) | `app/Console/Commands/MetricsFakeCommand.php:82-89` | Add `null` sun parameter |
| 7 | True wind calculation in extract command uses incorrect vector math (adds STW instead of subtracting) | `app/Console/Commands/MetricsExtractCommand.php:107-149` | Use cosine rule formula from MetricsService |
| 8 | Heading fallback from true to magnetic has no declination correction (5-20+ degree error) | `config/scarlet.php:38` | Apply magnetic variation or label heading as 'M' vs 'T' |

### Code Duplication

| # | Finding | Locations | Fix |
|---|---------|-----------|-----|
| 9 | Null Island check duplicated in 8 locations | GpsService, MetricsService, JourneyService, etc. | Extract to `GeoUtils::isNullIsland()` |
| 10 | True wind calculation in 3 places with divergent formulas | MetricsService, ResolvesShipLogData, MetricsExtractCommand | Extract to `NavigationMath::calculateTrueWind()` |
| 11 | Haversine formula in 3 places | Journey, GpxService, DashboardController | Extract to `GeoUtils::haversineNm()` |

### Performance

| # | Finding | File | Fix |
|---|---------|------|-----|
| 12 | Journey distance accessor triggers N+1 queries loading all track points per journey | `app/Models/Journey.php:138-154` | Store computed distance as column, updated on journey end |
| 13 | `queryMultipleWithStatus` makes sequential HTTP requests instead of pooled | `app/Services/PrometheusService.php:287-304` | Refactor to use `Http::pool()` |

### Frontend

| # | Finding | File | Fix |
|---|---------|------|-----|
| 14 | All pages eagerly loaded into single 785KB bundle | `resources/js/app.js:8-9` | Remove `{ eager: true }` from `import.meta.glob` |
| 15 | Echo channel subscribed at module scope — duplicate subscriptions and leaks | `resources/js/Pages/Admin/Tracker.vue:254-261` | Move to `onMounted`/`onUnmounted` |
| 16 | Map polyline segments added without cleanup tracking in JourneyView | `resources/js/Pages/Admin/JourneyView.vue:233-248` | Use `L.layerGroup()` |

### DevOps

| # | Finding | File | Fix |
|---|---------|------|-----|
| 17 | No scheduler container — cron tasks never run in production | `docker-compose.yml` | Add scheduler service running `php artisan schedule:work` |
| 18 | No health checks on MariaDB/Redis — migration runs before DB is ready | `docker-compose.yml:2-50` | Add healthcheck + `condition: service_healthy` |
| 19 | MediaMTX has no authentication on publish/read/API | `docker/mediamtx/mediamtx.yml:8-26` | Configure auth for publish at minimum |
| 20 | Redis has no password — full access from any container on the network | `docker-compose.yml:64-69` | Set `REDIS_PASSWORD` + `--requirepass` |
| 21 | Containers run as root | `docker/production/Dockerfile:1-43` | Add non-root user, `USER app` |
| 22 | VictoriaMetrics retention 100y with no disk management | `docker-compose.yml:95-107` | Set reasonable retention + `-storage.minFreeDiskSpaceBytes` |

---

## Medium (39)

### Security
- Hardcoded `APP_KEY` in `.env.example` — replace with empty string
- Static Reverb WebSocket credentials in production template
- `TrustProxies` configured to trust all proxies (`*`)
- GPX file upload has no MIME type validation
- Broadcast events use public channels exposing all telemetry

### Architecture & Laravel
- `MetricsService` has too many responsibilities (426 lines, 5+ concerns) — split into focused services
- `ExploreController` is 327 lines of business logic — extract to service
- No Form Request classes anywhere (15+ endpoints with inline validation)
- Casts defined inconsistently (`$casts` property vs `casts()` method)
- `PrometheusService` errors silently return null with no logging
- `WeatherService` HTTP requests have no timeout or error handling
- Satellite tile proxy has no rate limiting, bounds checking, or error handling
- Horizon retry `tries=1` — transient failures permanently lose jobs
- Import job not idempotent, no unique lock, no retry policy
- Migration uses raw SQL for ENUM (breaks SQLite testing)
- `MetricsPushCommand` uses `while(true)` with no signal handling
- Public journey routes use manual slug lookup instead of route model binding
- Battery power computation duplicated across 3 controllers
- Plan serialisation duplicated between PlannerController and PublicPlannerController
- Distance/DMG cumulative calculation duplicated

### Frontend
- `ResizeObserver` destroys/rebuilds uPlot charts instead of `setSize()`
- Multiple modals lack keyboard accessibility (ESC, focus trap, ARIA)
- Settings form inputs missing label association
- Broadcast page grid breaks on small screens
- Chart fetch errors silently swallowed with no user feedback
- "Forgot password" link is a dead anchor (`href='#'`)
- `Math.min(...vals)` spread risks stack overflow on large arrays

### DevOps
- All infrastructure images use `:latest` tags
- Migrations run on every container start with no concurrency protection
- VictoriaMetrics and OTEL ports exposed to host unnecessarily
- WebRTC has no STUN/TURN servers configured
- No resource limits on any container
- GitHub Actions uses outdated `actions/checkout@v3`
- Composer install uses `--ignore-platform-reqs`
- `.dockerignore` too minimal — sends .env, node_modules to build context
- Production `LOG_LEVEL=debug`

### Maritime
- Speed legend claims 0-10kn but colour gradient maxes at 7kn
- Depth metric has no shallow water alert threshold

---

## Low (18)

- Password double-hashed (ProfileController + RegisterController) — model cast already hashes
- `generateUniqueSlug` duplicated between Plan and Journey models
- Journey status and User role use magic strings instead of enums
- Gps and Weather DTOs live in `app/Models` alongside Eloquent models — move to `app/DTOs`
- Missing factories for BoatSetting and Invite
- `share_token` mass-assignable on Plan model
- Placeholder test (`true === true`) in ExampleTest
- Public overlay uses decimal degrees instead of maritime degrees-minutes format
- HDOP stored as integer loses precision (0.8 and 1.4 both become 1)
- Inconsistent component directory casing (`Components/` vs `components/`)
- Video feed retry loop has no maximum retry limit
- `weatherProps` computed duplicated across 3 public pages
- `window.innerWidth` initialisation breaks SSR compatibility
- Octane server default is `roadrunner` but application uses FrankenPHP
- Node.js build uses `node:23` (non-LTS, EOL June 2025)
