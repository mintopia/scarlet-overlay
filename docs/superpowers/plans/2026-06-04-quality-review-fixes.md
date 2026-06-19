# Quality Review Fixes Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix all actionable findings from the quality review (docs/quality-review-2026-06-04.md), excluding accepted/no-action items.

**Architecture:** Fixes are grouped by subsystem — critical backend fixes first, then security, runtime bugs, code deduplication, performance, frontend, medium backend, and low-priority. Each task is independent and can be committed separately.

**Tech Stack:** Laravel 12, PHP 8.4, Vue 3, Inertia v3, Tailwind v4, PHPUnit 11

**Exclusions (accepted by stakeholder, no changes):**
- Critical 2 (production secrets in git) — dev-only, production overrides
- Critical 6 (Docker restart policies) — accepted
- Critical 7 (no CI quality gates) — accepted
- High 3 (SRT URL SSRF) — accepted
- High 5 (Prometheus endpoints public) — firewall in place
- All DevOps High findings (17–22) — accepted
- All DevOps Medium findings — accepted
- Static credentials/examples in .env — dev purposes, production overrides
- Medium: TrustProxies `*` — required for Caddy reverse proxy in Docker

---

## Phase 1: Critical Fixes

### Task 1: Remove plan ownership — all users access all plans

The user wants plans to be shared across all authenticated users, not owned by individuals. Remove `user_id` from plans entirely.

**Files:**
- Modify: `app/Http/Controllers/Admin/PlannerController.php`
- Modify: `app/Http/Controllers/PublicPlannerController.php`
- Modify: `app/Models/Plan.php`
- Modify: `database/factories/PlanFactory.php`
- Create: `database/migrations/2026_06_04_200000_remove_user_id_from_plans.php`
- Modify: `tests/Feature/PlannerControllerTest.php`
- Modify: `tests/Feature/PublicPlannerControllerTest.php`

- [ ] **Step 1: Create migration to drop user_id**

```bash
php artisan make:migration remove_user_id_from_plans --no-interaction
```

Migration content:
```php
public function up(): void
{
    Schema::table('plans', function (Blueprint $table) {
        $table->dropForeign(['user_id']);
        $table->dropColumn('user_id');
    });
}

public function down(): void
{
    Schema::table('plans', function (Blueprint $table) {
        $table->foreignId('user_id')->after('title')->constrained()->cascadeOnDelete();
    });
}
```

- [ ] **Step 2: Update Plan model — remove user relationship and user_id from fillable**

In `app/Models/Plan.php`:
- Change `$fillable` from `['slug', 'title', 'user_id', 'share_token']` to `['slug', 'title', 'share_token']`
- Remove the `user()` BelongsTo relationship method entirely

- [ ] **Step 3: Update PlannerController — remove user_id scoping**

In `app/Http/Controllers/Admin/PlannerController.php`:
- `index()`: Change `Plan::where('user_id', auth()->id())` to `Plan::query()`
- `store()`: Remove `'user_id' => auth()->id()` from `Plan::create()`

- [ ] **Step 4: Update PlanFactory — remove user_id**

In `database/factories/PlanFactory.php`:
- Remove `'user_id' => User::factory()` from `definition()`
- Remove the `use App\Models\User;` import

- [ ] **Step 5: Update tests to remove user_id assertions**

In `tests/Feature/PlannerControllerTest.php` and `tests/Feature/PublicPlannerControllerTest.php`:
- Remove any assertions or setup that reference `user_id` on plans
- Ensure plan creation tests no longer pass `user_id`

- [ ] **Step 6: Run migration and tests**

```bash
php artisan migrate
php artisan test --compact --filter=Planner
```

- [ ] **Step 7: Run Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
```

---

### Task 2: Fix broken `/api/v1/metrics` endpoint

**Files:**
- Modify: `app/Http/Controllers/Api/V1/ApiController.php:36`

- [ ] **Step 1: Write a test for the metrics endpoint**

Create or update a test:
```bash
php artisan make:test ApiMetricsTest --phpunit --no-interaction
```

```php
public function test_metrics_endpoint_returns_json(): void
{
    $this->mock(\App\Services\MetricsService::class, function ($mock) {
        $mock->shouldReceive('getAllMetrics')->once()->andReturn([
            'boat' => [], 'tracker' => [], 'gps' => [],
            'weather' => null, 'settings' => [], 'sun' => null,
            'timestamp' => now()->toIso8601String(),
        ]);
    });

    $response = $this->getJson('/api/v1/metrics');
    $response->assertOk()->assertJsonStructure(['boat', 'tracker', 'gps']);
}
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
php artisan test --compact --filter=test_metrics_endpoint_returns_json
```

Expected: FAIL — `getMetrics()` method not found.

- [ ] **Step 3: Fix the method call**

In `app/Http/Controllers/Api/V1/ApiController.php`, line 36:
Change `$metricsService->getMetrics()` to `$metricsService->getAllMetrics()`.

- [ ] **Step 4: Run the test to verify it passes**

```bash
php artisan test --compact --filter=test_metrics_endpoint_returns_json
```

- [ ] **Step 5: Run Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
```

---

### Task 3: Fix weather type-cast operator precedence bug

**Files:**
- Modify: `app/Services/WeatherService.php:86-98`

- [ ] **Step 1: Write a test for weather parsing with null values**

```bash
php artisan make:test WeatherServiceTest --phpunit --no-interaction
```

Test that when API returns null for a field, the Weather DTO gets null (not 0.0):
```php
public function test_null_api_values_become_null_not_zero(): void
{
    Http::fake([
        '*/forecast*' => Http::response([
            'latitude' => 50.0, 'longitude' => -1.0, 'timezone' => 'UTC',
            'current' => [
                'temperature_2m' => null,
                'is_day' => 1,
                'weather_code' => 0,
                'wind_speed_10m' => 10.5,
                'wind_gusts_10m' => null,
                'wind_direction_10m' => 180,
                'surface_pressure' => null,
            ],
            'hourly' => ['time' => [], 'temperature_2m' => [], 'weather_code' => [], 'wind_speed_10m' => [], 'wind_gusts_10m' => [], 'precipitation' => []],
        ]),
        '*/marine*' => Http::response([
            'current' => [
                'sea_surface_temperature' => null,
                'ocean_current_velocity' => 0.5,
                'ocean_current_direction' => 90,
                'wave_height' => null,
                'wave_direction' => 200,
                'wave_period' => null,
            ],
        ]),
    ]);

    $service = app(\App\Services\WeatherService::class);
    $weather = $service->getWeather(force: true);

    $this->assertNull($weather->temp);
    $this->assertNull($weather->windGusts);
    $this->assertNull($weather->pressure);
    $this->assertNull($weather->seaTemp);
    $this->assertNull($weather->waveHeight);
    $this->assertNull($weather->wavePeriod);
    $this->assertEquals(10.5, $weather->windSpeed);
    $this->assertEquals(0.5, $weather->current);
}
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
php artisan test --compact --filter=test_null_api_values_become_null_not_zero
```

Expected: FAIL — null becomes 0.0 due to cast precedence.

- [ ] **Step 3: Fix the cast precedence in WeatherService**

Replace lines 86–98 in `app/Services/WeatherService.php` with:

```php
$weather->temp = isset($forecast['current']['temperature_2m']) ? (float) $forecast['current']['temperature_2m'] : null;
$weather->daytime = (bool) ($forecast['current']['is_day'] ?? true);
$weather->wmoCode = (float) ($forecast['current']['weather_code'] ?? 0);
$weather->windSpeed = isset($forecast['current']['wind_speed_10m']) ? (float) $forecast['current']['wind_speed_10m'] : null;
$weather->windGusts = isset($forecast['current']['wind_gusts_10m']) ? (float) $forecast['current']['wind_gusts_10m'] : null;
$weather->windDirection = isset($forecast['current']['wind_direction_10m']) ? (int) $forecast['current']['wind_direction_10m'] : null;
$weather->pressure = isset($forecast['current']['surface_pressure']) ? (float) $forecast['current']['surface_pressure'] : null;
$weather->seaTemp = isset($marine['current']['sea_surface_temperature']) ? (float) $marine['current']['sea_surface_temperature'] : null;
$weather->current = isset($marine['current']['ocean_current_velocity']) ? (float) $marine['current']['ocean_current_velocity'] : null;
$weather->currentDirection = isset($marine['current']['ocean_current_direction']) ? (int) $marine['current']['ocean_current_direction'] : null;
$weather->waveHeight = isset($marine['current']['wave_height']) ? (float) $marine['current']['wave_height'] : null;
$weather->waveDirection = isset($marine['current']['wave_direction']) ? (int) $marine['current']['wave_direction'] : null;
$weather->wavePeriod = isset($marine['current']['wave_period']) ? (float) $marine['current']['wave_period'] : null;
```

- [ ] **Step 4: Add timeout to HTTP requests (Medium: WeatherService no timeout)**

Also in `fetchWeatherForLatLong()`, add timeout to both HTTP calls:
- Change `Http::get(...)` to `Http::timeout(10)->get(...)`

- [ ] **Step 5: Run the test to verify it passes**

```bash
php artisan test --compact --filter=WeatherServiceTest
```

- [ ] **Step 6: Run Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
```

---

### Task 4: Fix MetricsExtractCommand config paths

**Files:**
- Modify: `app/Console/Commands/MetricsExtractCommand.php:178-188`

- [ ] **Step 1: Rewrite `buildQueryList()` to use registry and groups**

Replace the `buildQueryList()` method (lines 178–208) with:

```php
private function buildQueryList(): array
{
    $queries = [];
    $registry = config('scarlet.metrics.registry');
    $groups = config('scarlet.metrics.groups');

    foreach (['boat', 'tracker', 'gps'] as $group) {
        $keys = $groups[$group] ?? [];
        foreach ($keys as $key) {
            $entry = $registry[$key] ?? null;
            if ($entry && isset($entry['query'])) {
                $queries["{$group}.{$key}"] = $entry['query'];
            }
        }
    }

    $queries['weather.temperature'] = 'scarlet_weather_temperature_celsius';
    $queries['weather.pressure'] = 'scarlet_weather_pressure_hpa';
    $queries['weather.wind_speed'] = 'scarlet_weather_wind_speed_kn';
    $queries['weather.wind_direction'] = 'scarlet_weather_wind_direction_deg';
    $queries['weather.wave_height'] = 'scarlet_weather_wave_height_m';
    $queries['weather.wave_period'] = 'scarlet_weather_wave_period_s';
    $queries['weather.wave_direction'] = 'scarlet_weather_wave_direction_deg';
    $queries['weather.current_speed'] = 'scarlet_weather_current_speed_kn';
    $queries['weather.current_direction'] = 'scarlet_weather_current_direction_deg';
    $queries['weather.sea_temperature'] = 'scarlet_weather_sea_temperature_celsius';

    $queries['srt.up'] = 'scarlet_srt_up';
    $queries['srt.publisher_connected'] = 'scarlet_srt_publisher_connected';
    $queries['srt.publisher_bitrate'] = 'scarlet_srt_publisher_bitrate_bps';
    $queries['srt.publisher_rtt'] = 'scarlet_srt_publisher_rtt_ms';
    $queries['srt.publisher_drops'] = 'scarlet_srt_publisher_dropped_packets_total';
    $queries['srt.publisher_latency'] = 'scarlet_srt_publisher_latency_ms';

    return $queries;
}
```

- [ ] **Step 2: Run Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
```

---

## Phase 2: Security Hardening

### Task 5: Add rate limiting to API v1 routes

**Files:**
- Modify: `routes/api.php`

- [ ] **Step 1: Add throttle middleware to API v1 group**

In `routes/api.php`, wrap the route group with throttle:
```php
Route::prefix('v1')->middleware('throttle:60,1')->group(function () {
    Route::get('gps', [ApiController::class, 'gps']);
    Route::get('weather', [ApiController::class, 'weather']);
    Route::get('weather/home', [ApiController::class, 'weather_home']);
    Route::get('metrics', [ApiController::class, 'metrics']);
    Route::get('ping', [PingController::class, 'ping']);
});
```

- [ ] **Step 2: Run Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
```

---

### Task 6: Remove `role` from User $fillable

**Files:**
- Modify: `app/Models/User.php:15`
- Modify: `app/Http/Controllers/Auth/RegisterController.php:39-44`

- [ ] **Step 1: Remove `role` from User $fillable**

In `app/Models/User.php`, change line 15:
```php
protected $fillable = ['name', 'email', 'password'];
```

- [ ] **Step 2: Set role explicitly in RegisterController**

In `app/Http/Controllers/Auth/RegisterController.php`, change the User::create block:
```php
$user = User::create([
    'name' => $validated['name'],
    'email' => $validated['email'],
    'password' => Hash::make($validated['password']),
]);
$user->role = 'crew';
$user->save();
```

- [ ] **Step 3: Run auth tests**

```bash
php artisan test --compact --filter=Auth
```

- [ ] **Step 4: Run Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
```

---

### Task 7: Add GPX file MIME validation

**Files:**
- Modify: `app/Http/Controllers/Admin/PlanRouteController.php:17-18`

- [ ] **Step 1: Add MIME/extension validation to GPX upload**

In `app/Http/Controllers/Admin/PlanRouteController.php`, update the validation rule:
```php
'gpx_files.*' => ['required', 'file', 'max:10240', 'extensions:gpx,xml'],
```

- [ ] **Step 2: Run planner route tests**

```bash
php artisan test --compact --filter=PlanRouteController
```

- [ ] **Step 3: Run Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
```

---

### Task 8: Use private broadcast channel for metrics

**Files:**
- Modify: `app/Events/MetricsUpdated.php`
- Modify: `routes/channels.php`
- Modify: `resources/js/Pages/Admin/Tracker.vue:255-261`
- Modify: `resources/js/composables/useScarletMetrics.js` (if it subscribes)
- Modify: `resources/js/bootstrap.js` (if Echo is configured)

Note: This is a significant change that would break all public pages that listen to metrics (Dashboard, Overlay, Camera). Since these public pages need real-time metrics WITHOUT authentication, the current public channel is intentional. Skip this finding — a public channel for telemetry data displayed on public pages is the correct architecture. The data is already publicly visible on the public dashboard.

**DECISION: Skip this task.** The public pages (Dashboard, Overlay, Camera) are designed to be unauthenticated and need real-time metrics. Broadcasting on a public channel is correct for this use case.

---

## Phase 3: Runtime Bug Fixes

### Task 9: Fix MetricsFakeCommand missing `sun` parameter

**Files:**
- Modify: `app/Console/Commands/MetricsFakeCommand.php:82-89, 137-144`

- [ ] **Step 1: Fix replayFromFile dispatch — add null sun parameter**

In `app/Console/Commands/MetricsFakeCommand.php`, the dispatch at line 82 currently passes 6 args but MetricsUpdated expects 7. Add `null` for `sun`:

```php
MetricsUpdated::dispatch(
    $boat,
    $tracker,
    $gps,
    $weather,
    $settings,
    null,
    $frame['time'],
);
```

- [ ] **Step 2: Fix replayFromPrometheus dispatch — add null sun parameter**

The dispatch at line 137 also missing `sun`. Change to:

```php
MetricsUpdated::dispatch(
    $all['boat'],
    $all['tracker'],
    $all['gps'],
    $all['weather'],
    $all['settings'],
    null,
    $all['timestamp'],
);
```

- [ ] **Step 3: Run Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
```

---

### Task 10: Fix true wind calculation in extract command

**Files:**
- Modify: `app/Console/Commands/MetricsExtractCommand.php:107-149`

The extract command uses vector addition (`$stwRaw * cos(0.0) + $awsRaw * cos($awaRaw)`) instead of the correct cosine rule formula from MetricsService. The `+` should be subtracted for the boat speed component.

- [ ] **Step 1: Replace both true wind calculation paths with the correct formula**

Replace lines 104–149 in MetricsExtractCommand with code that delegates to the same formula as MetricsService:

```php
// Calculate true wind for each frame using cosine rule
foreach ($timeline as &$frame) {
    $m = $frame['metrics'];

    $awsRaw = $m['boat._aws'] ?? null;
    $awaRaw = $m['boat._awa'] ?? null;
    $stwRaw = $m['boat._stw'] ?? null;
    $hdgRaw = $m['boat._heading'] ?? null;

    // Try raw SI values first (m/s, radians)
    if ($awsRaw !== null && $awaRaw !== null && $stwRaw !== null && $hdgRaw !== null) {
        $tws = sqrt($awsRaw ** 2 + $stwRaw ** 2 - 2 * $awsRaw * $stwRaw * cos($awaRaw));
        $twa = atan2($awsRaw * sin($awaRaw), $awsRaw * cos($awaRaw) - $stwRaw);
        $twd = fmod($hdgRaw + $twa + 2 * M_PI, 2 * M_PI);

        $frame['metrics']['boat.wind_speed_true'] = $tws * 1.94384;
        $frame['metrics']['boat.wind_direction_true'] = $twd * 180 / M_PI;
    }
    // Fallback: use converted values (knots, degrees)
    elseif (($m['boat.wind_speed_apparent'] ?? null) !== null
        && ($m['boat.wind_angle_apparent'] ?? null) !== null
        && ($m['boat.speed_stw'] ?? null) !== null
        && ($m['boat.heading'] ?? null) !== null) {

        $awsKn = $m['boat.wind_speed_apparent'];
        $awaDeg = $m['boat.wind_angle_apparent'];
        $stwKn = $m['boat.speed_stw'];
        $hdgDeg = $m['boat.heading'];

        $awaRad = $awaDeg * M_PI / 180;
        $tws = sqrt($awsKn ** 2 + $stwKn ** 2 - 2 * $awsKn * $stwKn * cos($awaRad));
        $twaRad = atan2($awsKn * sin($awaRad), $awsKn * cos($awaRad) - $stwKn);
        $twdDeg = fmod($hdgDeg + $twaRad * 180 / M_PI + 360, 360);

        $frame['metrics']['boat.wind_speed_true'] = $tws;
        $frame['metrics']['boat.wind_direction_true'] = $twdDeg;
    }

    unset($frame['metrics']['boat._aws']);
    unset($frame['metrics']['boat._awa']);
    unset($frame['metrics']['boat._stw']);
    unset($frame['metrics']['boat._heading']);
}
unset($frame);
```

- [ ] **Step 2: Run Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
```

---

### Task 11: Add magnetic variation metric to registry

**Files:**
- Modify: `config/scarlet.php`

The heading fallback from true to magnetic has no declination correction. SignalK provides `navigation.magneticVariation`. Add it to the registry so it's available for future use and annotate the limitation.

- [ ] **Step 1: Add magnetic_variation to the registry**

In `config/scarlet.php`, add to the registry array after the `heading_raw` entry:

```php
'magnetic_variation' => ['query' => 'scarlet_signalk_navigation_magneticVariation', 'multiply' => 180, 'divide' => 3.14159265359],
```

Add `'magnetic_variation'` to the `'boat'` group in the groups array.

- [ ] **Step 2: Run Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
```

---

### Task 12: Add signal handling to MetricsPushCommand

**Files:**
- Modify: `app/Console/Commands/MetricsPushCommand.php`

- [ ] **Step 1: Add SIGTERM/SIGINT handling**

Replace the `handle()` method:

```php
public function handle(MetricsService $metricsService, JourneyService $journeyService): int
{
    $interval = config('scarlet.metrics.push_interval');
    $this->info("Starting metrics push loop (every {$interval}s)");

    $running = true;
    pcntl_async_signals(true);
    $stop = function () use (&$running) {
        $this->info('Stopping metrics push loop...');
        $running = false;
    };
    pcntl_signal(SIGTERM, $stop);
    pcntl_signal(SIGINT, $stop);

    while ($running) {
        try {
            $all = $metricsService->getAllMetrics();
            MetricsUpdated::dispatch(
                $all['boat'],
                $all['tracker'],
                $all['gps'],
                $all['weather'],
                $all['settings'],
                $all['sun'],
                $all['timestamp'],
            );
            $this->line('Pushed metrics at '.$all['timestamp']);

            $journey = Journey::current();
            if ($journey) {
                $journeyService->recordTrackPoint($journey, $all);
            }
        } catch (\Throwable $e) {
            Log::error("Metrics push failed: {$e->getMessage()}");
            $this->error("Error: {$e->getMessage()}");
        }

        sleep($interval);
    }

    return self::SUCCESS;
}
```

- [ ] **Step 2: Run Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
```

---

## Phase 4: Code Deduplication

### Task 13: Extract GeoUtils — null island check and haversine

**Files:**
- Create: `app/Support/GeoUtils.php`
- Modify: `app/Services/MetricsService.php`
- Modify: `app/Services/GpsService.php`
- Modify: `app/Services/JourneyService.php`
- Modify: `app/Services/GpxService.php`
- Modify: `app/Models/Journey.php`
- Modify: `app/Http/Controllers/JourneyViewController.php`
- Modify: `app/Jobs/ImportJourneyFromPrometheus.php`

- [ ] **Step 1: Create GeoUtils class**

```bash
php artisan make:class Support/GeoUtils --no-interaction
```

```php
<?php

namespace App\Support;

class GeoUtils
{
    public static function isNullIsland(?float $lat, ?float $lng): bool
    {
        return $lat === null || $lng === null
            || (abs($lat) < 0.1 && abs($lng) < 0.1);
    }

    public static function haversineNm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $r = 3440.065;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
```

- [ ] **Step 2: Replace all null island checks with GeoUtils::isNullIsland()**

Replace inline null island checks in:
1. `app/Services/MetricsService.php` — the private `isNullIsland()` method: delete and replace calls with `GeoUtils::isNullIsland()`
2. `app/Services/GpsService.php:29-30` — replace inline check
3. `app/Services/JourneyService.php:23` — replace inline check
4. `app/Http/Controllers/JourneyViewController.php:33, 74` — replace inline checks
5. `app/Jobs/ImportJourneyFromPrometheus.php:52` — replace inline check

- [ ] **Step 3: Replace all haversine implementations with GeoUtils::haversineNm()**

1. `app/Models/Journey.php` — delete the private `haversineNm()` method, call `GeoUtils::haversineNm()` in `getDistanceAttribute()`
2. `app/Services/GpxService.php` — delete the private `haversineNm()` method, call `GeoUtils::haversineNm()` in `calculateDistanceNm()`

- [ ] **Step 4: Run all tests**

```bash
php artisan test --compact
```

- [ ] **Step 5: Run Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
```

---

### Task 14: Extract NavigationMath — true wind calculation

**Files:**
- Create: `app/Support/NavigationMath.php`
- Modify: `app/Services/MetricsService.php`
- Modify: `app/Console/Commands/MetricsExtractCommand.php`

- [ ] **Step 1: Create NavigationMath class**

```bash
php artisan make:class Support/NavigationMath --no-interaction
```

```php
<?php

namespace App\Support;

class NavigationMath
{
    /**
     * Calculate true wind speed and direction using the cosine rule.
     * All inputs in SI units: m/s for speeds, radians for angles.
     *
     * @return array{speed: float|null, direction: float|null} Speed in m/s, direction in radians
     */
    public static function calculateTrueWind(?float $aws, ?float $awa, ?float $stw, ?float $heading): array
    {
        if ($aws === null || $awa === null || $stw === null || $heading === null) {
            return ['speed' => null, 'direction' => null];
        }

        $tws = sqrt($aws ** 2 + $stw ** 2 - 2 * $aws * $stw * cos($awa));
        $twa = atan2($aws * sin($awa), $aws * cos($awa) - $stw);
        $twd = fmod($heading + $twa + 2 * M_PI, 2 * M_PI);

        return ['speed' => $tws, 'direction' => $twd];
    }
}
```

- [ ] **Step 2: Update MetricsService to use NavigationMath**

In `app/Services/MetricsService.php`, replace the private `calculateTrueWind()` method with a call to `NavigationMath::calculateTrueWind()`. The existing method returns speed in knots (multiplied by 1.94384) and direction in degrees — keep that conversion inline or in a wrapper.

Replace the private method (lines 340-354) with:

```php
private function calculateTrueWind(?float $aws, ?float $awa, ?float $sog, ?float $heading): array
{
    $tw = NavigationMath::calculateTrueWind($aws, $awa, $sog, $heading);

    return [
        'speed' => $tw['speed'] !== null ? $tw['speed'] * 1.94384 : null,
        'direction' => $tw['direction'] !== null ? rad2deg($tw['direction']) : null,
    ];
}
```

Add `use App\Support\NavigationMath;` at top.

- [ ] **Step 3: Update MetricsExtractCommand to use NavigationMath**

In the raw SI path of the true wind calculation (modified in Task 10), replace the inline math with:

```php
$tw = \App\Support\NavigationMath::calculateTrueWind($awsRaw, $awaRaw, $stwRaw, $hdgRaw);
$frame['metrics']['boat.wind_speed_true'] = $tw['speed'] * 1.94384;
$frame['metrics']['boat.wind_direction_true'] = rad2deg($tw['direction']);
```

- [ ] **Step 4: Run all tests**

```bash
php artisan test --compact
```

- [ ] **Step 5: Run Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
```

---

### Task 15: Extract shared slug generation

**Files:**
- Create: `app/Support/SlugGenerator.php`
- Modify: `app/Models/Plan.php`
- Modify: `app/Models/Journey.php`

- [ ] **Step 1: Create SlugGenerator class**

```bash
php artisan make:class Support/SlugGenerator --no-interaction
```

```php
<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SlugGenerator
{
    public static function unique(string $modelClass, string $text, string $column = 'slug'): string
    {
        $base = Str::slug($text);
        $slug = $base;
        $counter = 1;

        while ($modelClass::where($column, $slug)->exists()) {
            $counter++;
            $slug = $base.'-'.$counter;
        }

        return $slug;
    }
}
```

- [ ] **Step 2: Update Plan::generateUniqueSlug()**

In `app/Models/Plan.php`, replace `generateUniqueSlug()`:
```php
protected static function generateUniqueSlug(string $title): string
{
    return \App\Support\SlugGenerator::unique(static::class, $title);
}
```

- [ ] **Step 3: Update Journey::generateUniqueSlug()**

In `app/Models/Journey.php`, replace `generateUniqueSlug()`:
```php
protected static function generateUniqueSlug(string $from, string $to): string
{
    return \App\Support\SlugGenerator::unique(static::class, $from.' to '.$to);
}
```

- [ ] **Step 4: Run tests**

```bash
php artisan test --compact --filter=Journey
php artisan test --compact --filter=Planner
```

- [ ] **Step 5: Run Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
```

---

## Phase 5: Performance

### Task 16: Use Http::pool() for sequential Prometheus queries

**Files:**
- Modify: `app/Services/PrometheusService.php:287-304`

- [ ] **Step 1: Refactor queryMultipleWithStatus to use Http::pool()**

Replace the method (lines 287–304):

```php
public function queryMultipleWithStatus(array $queries, int $maxAge = 120): array
{
    $results = [];
    $fetchError = false;
    $keys = array_keys($queries);

    $responses = Http::pool(function (\Illuminate\Http\Client\Pool $pool) use ($queries, $maxAge) {
        foreach ($queries as $key => $promql) {
            $pool->as($key)->timeout(10)->get($this->buildQueryUrl($promql, $maxAge));
        }
    });

    foreach ($keys as $key) {
        try {
            $response = $responses[$key];
            if ($response instanceof \Throwable) {
                throw $response;
            }
            $results[$key] = $this->extractValue($response);
        } catch (\Throwable $e) {
            Log::warning("Prometheus query failed [{$queries[$key]}]: {$e->getMessage()}");
            $fetchError = true;
            $results[$key] = null;
        }
    }

    return ['values' => $results, 'fetchError' => $fetchError];
}
```

Note: This requires understanding the existing `queryFresh()` method to extract the URL building and value extraction logic into helper methods (`buildQueryUrl` and `extractValue`). Read the full PrometheusService to understand the exact API and adapt accordingly. If `queryFresh` does cache lookups before HTTP, keep that logic and only pool the actual HTTP calls.

- [ ] **Step 2: Run tests**

```bash
php artisan test --compact --filter=Prometheus
```

- [ ] **Step 3: Run Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
```

---

### Task 17: Lazy-load pages instead of eager-loading entire bundle

**Files:**
- Modify: `resources/js/app.js:8`

- [ ] **Step 1: Switch from eager to lazy page loading**

In `resources/js/app.js`, change line 8:
```js
const pages = import.meta.glob('./Pages/**/*.vue');
```

And update the resolve function to handle the async import:
```js
resolve: name => {
    const pages = import.meta.glob('./Pages/**/*.vue');
    return pages[`./Pages/${name}.vue`]();
},
```

- [ ] **Step 2: Rebuild and verify**

```bash
npm run build
```

Verify the output creates multiple chunks instead of one large bundle.

- [ ] **Step 3: Commit**

---

## Phase 6: Frontend Fixes

### Task 18: Move Echo subscription to lifecycle hooks

**Files:**
- Modify: `resources/js/Pages/Admin/Tracker.vue:254-262`

- [ ] **Step 1: Move Echo subscription into onMounted/onUnmounted**

Replace lines 254–262:

```js
const metrics = ref(null);
const lastUpdate = ref(props.tracker?.last_seen ? props.tracker.last_seen * 1000 : null);
let echoChannel = null;

onMounted(() => {
    if (typeof window !== 'undefined' && window.Echo) {
        echoChannel = window.Echo.channel('metrics');
        echoChannel.listen('.metrics.updated', (data) => {
            metrics.value = data;
            const ts = data.tracker?.last_seen;
            lastUpdate.value = ts ? ts * 1000 : Date.now();
        });
    }
});

onUnmounted(() => {
    if (echoChannel) {
        echoChannel.stopListening('.metrics.updated');
        window.Echo?.leave('metrics');
        echoChannel = null;
    }
});
```

- [ ] **Step 2: Commit**

---

### Task 19: Fix JourneyView chart ResizeObserver — use setSize instead of destroy/rebuild

**Files:**
- Modify: `resources/js/Pages/Admin/JourneyView.vue:253-262`

- [ ] **Step 1: Replace destroy/rebuild with setSize**

Replace the `setupChartResize` function:

```js
function setupChartResize(key, el) {
    const ro = new ResizeObserver(() => {
        if (chartInstances[key] && el.offsetWidth > 0) {
            chartInstances[key].setSize({ width: el.offsetWidth, height: el.offsetHeight || 160 });
        }
    });
    ro.observe(el);
    resizeObservers.push(ro);
}
```

- [ ] **Step 2: Commit**

---

### Task 20: Fix Broadcast page responsive grid

**Files:**
- Modify: `resources/js/Pages/Admin/Broadcast.vue:35`

- [ ] **Step 1: Add responsive breakpoint to grid**

Change line 35 from:
```html
<div class="grid grid-cols-[1fr_320px] gap-4">
```
to:
```html
<div class="grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-4">
```

- [ ] **Step 2: Commit**

---

### Task 21: Add keyboard accessibility to Settings force-reload modal

**Files:**
- Modify: `resources/js/Pages/Admin/Settings.vue:73-85`

- [ ] **Step 1: Add ESC handler, aria attributes, and focus management**

Replace the modal markup (lines 73–85) with:

```html
<Teleport to="body">
    <div v-if="showReloadModal" class="fixed inset-0 z-[100] flex items-center justify-center" role="dialog" aria-modal="true" aria-labelledby="reload-title" @keydown.escape="showReloadModal = false">
        <div class="absolute inset-0 bg-black/50" @click="showReloadModal = false"></div>
        <div ref="reloadModalRef" tabindex="-1" class="relative bg-surface rounded-xl p-7 max-w-[420px] w-full mx-4 shadow-[0_20px_60px_rgba(0,0,0,0.25)] text-text-primary">
            <h3 id="reload-title" class="text-base font-bold mb-2.5">Force Reload Clients</h3>
            <p class="text-sm text-text-secondary leading-normal mb-6">This will reload all overlay and dashboard browser windows. Continue?</p>
            <div class="flex gap-2.5 justify-end">
                <button type="button" @click="showReloadModal = false" class="btn btn--secondary">Cancel</button>
                <button type="button" @click="confirmForceReload" :disabled="reloading" class="btn btn--danger">
                    {{ reloading ? 'Sending…' : 'Reload All Clients' }}
                </button>
            </div>
        </div>
    </div>
</Teleport>
```

Add to script:

```js
import { ref, nextTick, watch } from 'vue';

const reloadModalRef = ref(null);
watch(showReloadModal, (open) => {
    if (open) nextTick(() => reloadModalRef.value?.focus());
});
```

- [ ] **Step 2: Commit**

---

### Task 22: Add label associations to Settings form inputs

**Files:**
- Modify: `resources/js/Pages/Admin/Settings.vue`

- [ ] **Step 1: Add `for`/`id` pairs to all form labels and inputs**

For each label/input pair, add matching `for` and `id` attributes:
- `<label for="boat_name" ...>` + `<input id="boat_name" ...>`
- `<label for="mmsi" ...>` + `<input id="mmsi" ...>`
- `<label for="srt_url" ...>` + `<input id="srt_url" ...>`
- `<label for="srt_stats_url" ...>` + `<input id="srt_stats_url" ...>`
- `<label for="camera_url" ...>` + `<input id="camera_url" ...>`

- [ ] **Step 2: Commit**

---

### Task 23: Remove dead "Forgot password" link

**Files:**
- Modify: `resources/js/Pages/Auth/Login.vue:40`

- [ ] **Step 1: Remove or disable the forgot password link**

Since there's no forgot-password flow, remove the link. Change line 40 from:
```html
<a href="#" class="forgot-link">Forgot password?</a>
```
to just removing the `<a>` element entirely (or replace with an empty span to preserve layout).

- [ ] **Step 2: Commit**

---

### Task 24: Extract weatherProps composable

**Files:**
- Create: `resources/js/composables/useWeatherProps.js`
- Modify: `resources/js/Pages/Public/Dashboard.vue`
- Modify: `resources/js/Pages/Public/Camera.vue`
- Modify: `resources/js/Pages/Public/Overlay.vue`

- [ ] **Step 1: Create the composable**

```js
import { computed } from 'vue';

export function useWeatherProps(weather, { wxIcon, wxTemp, wxCondition, wxSeaTemp, wxWindSpeed, wxWindDir, wxWaveHeight, wxWavePeriod }) {
    return computed(() => ({
        wxIcon: wxIcon.value,
        wxTemp: wxTemp.value,
        wxCondition: wxCondition.value,
        wxSeaTemp: wxSeaTemp.value,
        wxWindSpeed: wxWindSpeed.value,
        wxWindDir: wxWindDir.value,
        wxWaveHeight: wxWaveHeight.value,
        wxWavePeriod: wxWavePeriod.value,
        rawTemp: weather.value?.temp != null ? Number(weather.value.temp) : null,
        rawSeaTemp: weather.value?.seaTemp != null ? Number(weather.value.seaTemp) : null,
        rawWindSpeed: weather.value?.wind?.speed != null ? Number(weather.value.wind.speed) : null,
        rawWaveHeight: weather.value?.waves?.height != null ? Number(weather.value.waves.height) : null,
        rawWavePeriod: weather.value?.waves?.period != null ? Number(weather.value.waves.period) : null,
    }));
}
```

- [ ] **Step 2: Replace in all three public pages**

In each of Dashboard.vue, Camera.vue, and Overlay.vue, replace the `weatherProps` computed with:
```js
import { useWeatherProps } from '../../composables/useWeatherProps';

const weatherProps = useWeatherProps(weather, { wxIcon, wxTemp, wxCondition, wxSeaTemp, wxWindSpeed, wxWindDir, wxWaveHeight, wxWavePeriod });
```

- [ ] **Step 3: Verify build**

```bash
npm run build
```

- [ ] **Step 4: Commit**

---

### Task 25: Fix window.innerWidth SSR compatibility

**Files:**
- Modify: `resources/js/Pages/Public/Dashboard.vue:75-76`

- [ ] **Step 1: Guard window access and initialize in onMounted**

Replace lines 75-76:
```js
const isMobile = ref(false);
function onResize() { isMobile.value = typeof window !== 'undefined' && window.innerWidth < 640; }

onMounted(() => {
    onResize();
    window.addEventListener('resize', onResize);
});
onUnmounted(() => {
    window.removeEventListener('resize', onResize);
});
```

Ensure `onMounted` and `onUnmounted` are imported from vue.

- [ ] **Step 2: Commit**

---

### Task 26: Protect Math.min/max spread from stack overflow on large arrays

**Files:**
- Modify: `resources/js/Components/Admin/Sparkline.vue:24`
- Modify: `resources/js/Components/Admin/TemperatureGauge.vue:29`

- [ ] **Step 1: In Sparkline.vue, replace Math.min/max spread with reduce**

Replace:
```js
const min = props.zeroLine ? Math.min(0, ...numericValues) : Math.min(...numericValues)
```
with:
```js
const min = props.zeroLine
    ? numericValues.reduce((m, v) => Math.min(m, v), 0)
    : numericValues.reduce((m, v) => Math.min(m, v), Infinity)
```

Do the same for any `Math.max(...numericValues)` on the next line.

- [ ] **Step 2: In TemperatureGauge.vue, replace Math.min/max spread**

Replace:
```js
const min = Math.min(...vals)
```
with:
```js
const min = vals.reduce((m, v) => Math.min(m, v), Infinity)
```

Same for Math.max.

- [ ] **Step 3: Commit**

---

## Phase 7: Medium Backend Fixes

### Task 27: Add Horizon retry — increase tries from 1 to 3

**Files:**
- Modify: `config/horizon.php:209`

- [ ] **Step 1: Change tries from 1 to 3**

In `config/horizon.php`, change:
```php
'tries' => 1,
```
to:
```php
'tries' => 3,
```

- [ ] **Step 2: Commit**

---

### Task 28: Add unique lock and retry policy to ImportJourneyFromPrometheus

**Files:**
- Modify: `app/Jobs/ImportJourneyFromPrometheus.php`

- [ ] **Step 1: Add ShouldBeUnique and retry configuration**

Add the `ShouldBeUnique` interface and configure retry:

```php
use Illuminate\Contracts\Queue\ShouldBeUnique;

class ImportJourneyFromPrometheus implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function uniqueId(): string
    {
        return (string) $this->journeyId;
    }

    // ... rest unchanged
}
```

- [ ] **Step 2: Make import idempotent — delete existing track points before importing**

At the start of `handle()`, add:
```php
$journey->trackPoints()->delete();
```

This ensures re-running the import doesn't create duplicates.

- [ ] **Step 3: Run tests**

```bash
php artisan test --compact --filter=ImportJourney
```

- [ ] **Step 4: Run Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
```

---

### Task 29: Use route model binding for public journey routes

**Files:**
- Modify: `app/Http/Controllers/JourneyViewController.php`
- Modify: `routes/web.php:45-47`

- [ ] **Step 1: Update routes to use route model binding**

In `routes/web.php`, change:
```php
Route::get('/journey/{slug}', [JourneyViewController::class, 'show'])->name('journey.show');
Route::get('/api/journey/{slug}/track', [JourneyViewController::class, 'track'])->name('journey.track');
```
to:
```php
Route::get('/journey/{journey:slug}', [JourneyViewController::class, 'show'])->name('journey.show');
Route::get('/api/journey/{journey:slug}/track', [JourneyViewController::class, 'track'])->name('journey.track');
```

- [ ] **Step 2: Update controller to accept Journey model**

In `app/Http/Controllers/JourneyViewController.php`:

Change `show(Request $request, string $slug)` to `show(Request $request, Journey $journey)` and remove the `Journey::where('slug', $slug)->firstOrFail()` line.

Change `track(Request $request, string $slug)` to `track(Request $request, Journey $journey)` and remove the `Journey::where('slug', $slug)->firstOrFail()` line.

- [ ] **Step 3: Run tests**

```bash
php artisan test --compact --filter=JourneyView
```

- [ ] **Step 4: Run Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
```

---

### Task 30: Fix PrometheusService silent error logging

**Files:**
- Modify: `app/Services/PrometheusService.php`

Review the service and ensure all catch blocks log the error. The `queryMultipleWithStatus` method already logs warnings. Check other methods that catch exceptions silently.

- [ ] **Step 1: Add logging to any catch blocks that silently return null**

Scan the service for `catch` blocks that don't log. Add `Log::warning(...)` to any that silently swallow errors.

- [ ] **Step 2: Run Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
```

---

### Task 31: Standardize model casts to use `casts()` method

**Files:**
- Modify: `app/Models/Journey.php`

- [ ] **Step 1: Convert $casts property to casts() method**

In `app/Models/Journey.php`, replace:
```php
protected $casts = [
    'started_at' => 'datetime',
    'ended_at' => 'datetime',
    'is_public' => 'boolean',
    'route_waypoints' => 'array',
];
```
with:
```php
protected function casts(): array
{
    return [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'is_public' => 'boolean',
        'route_waypoints' => 'array',
    ];
}
```

- [ ] **Step 2: Run tests**

```bash
php artisan test --compact --filter=Journey
```

- [ ] **Step 3: Run Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
```

---

### Task 32: Extract plan serialisation to shared method

**Files:**
- Modify: `app/Http/Controllers/Admin/PlannerController.php`
- Modify: `app/Http/Controllers/PublicPlannerController.php`

- [ ] **Step 1: Add a `toArray` or serialization method to Plan model or a shared helper**

Create a method in `Plan.php`:
```php
public function toDetailArray(): array
{
    return [
        'id' => $this->id,
        'slug' => $this->slug,
        'title' => $this->title,
        'share_token' => $this->share_token ?? null,
        'created_at' => $this->created_at->toIso8601String(),
        'groups' => $this->groups->map(fn ($g) => [
            'id' => $g->id,
            'name' => $g->name,
            'color_index' => $g->color_index,
            'sort_order' => $g->sort_order ?? 0,
            'routes' => $g->routes->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'distance_nm' => (float) $r->distance_nm,
                'is_enabled' => $r->is_enabled,
                'color_index' => $r->color_index,
                'track_points' => $r->track_points,
                'waypoints' => $r->waypoints,
            ]),
        ]),
    ];
}
```

- [ ] **Step 2: Use in both controllers**

In `PlannerController::show()` and `PublicPlannerController::show()`, replace inline serialization with `$plan->toDetailArray()`.

- [ ] **Step 3: Run tests**

```bash
php artisan test --compact --filter=Planner
```

- [ ] **Step 4: Run Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
```

---

## Phase 8: Low Priority Fixes

### Task 33: Fix password double-hashing

**Files:**
- Modify: `app/Http/Controllers/Admin/ProfileController.php:42-43`
- Modify: `app/Http/Controllers/Auth/RegisterController.php:39-44`

The User model has a `'password' => 'hashed'` cast, which automatically hashes the password on set. Both ProfileController and RegisterController also call `Hash::make()`, causing double-hashing.

- [ ] **Step 1: Remove Hash::make() from ProfileController**

In `app/Http/Controllers/Admin/ProfileController.php`, change:
```php
$request->user()->update([
    'password' => Hash::make($validated['password']),
]);
```
to:
```php
$request->user()->update([
    'password' => $validated['password'],
]);
```

Remove the `use Illuminate\Support\Facades\Hash;` import if no longer needed.

- [ ] **Step 2: Remove Hash::make() from RegisterController**

In `app/Http/Controllers/Auth/RegisterController.php`, change:
```php
'password' => Hash::make($validated['password']),
```
to:
```php
'password' => $validated['password'],
```

Remove the `use Illuminate\Support\Facades\Hash;` import if no longer needed.

- [ ] **Step 3: Run auth tests**

```bash
php artisan test --compact --filter=Auth
php artisan test --compact --filter=Profile
```

- [ ] **Step 4: Run Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
```

---

### Task 34: Create enums for Journey status and User role

**Files:**
- Create: `app/Enums/JourneyStatus.php`
- Create: `app/Enums/UserRole.php`
- Modify: `app/Models/Journey.php`
- Modify: `app/Models/User.php`

- [ ] **Step 1: Create JourneyStatus enum**

```php
<?php

namespace App\Enums;

enum JourneyStatus: string
{
    case Planned = 'planned';
    case Active = 'active';
    case Completed = 'completed';
    case Abandoned = 'abandoned';
}
```

- [ ] **Step 2: Create UserRole enum**

```php
<?php

namespace App\Enums;

enum UserRole: string
{
    case Owner = 'owner';
    case Crew = 'crew';
}
```

- [ ] **Step 3: Update Journey model to use enum cast**

In the `casts()` method, add:
```php
'status' => JourneyStatus::class,
```

Update scope methods:
```php
public function scopePlanned($query) { return $query->where('status', JourneyStatus::Planned); }
public function scopeActive($query) { return $query->where('status', JourneyStatus::Active); }
public function scopeCompleted($query) { return $query->where('status', JourneyStatus::Completed); }
```

Update `createPlanned()`, `activate()` and anywhere else that uses string literals.

- [ ] **Step 4: Update User model to use enum cast**

In the `casts()` method (create one if needed), add:
```php
'role' => UserRole::class,
```

Update `isOwner()`:
```php
public function isOwner(): bool { return $this->role === UserRole::Owner; }
```

Update RegisterController to use `UserRole::Crew` instead of `'crew'`.

- [ ] **Step 5: Update tests that compare status/role strings**

Update any tests that assert `'active'`, `'planned'`, etc. to use the enum.

- [ ] **Step 6: Run all tests**

```bash
php artisan test --compact
```

- [ ] **Step 7: Run Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
```

---

### Task 35: Remove `share_token` from Plan $fillable

**Files:**
- Modify: `app/Models/Plan.php`

- [ ] **Step 1: Remove share_token from fillable**

After Task 1, `$fillable` should be `['slug', 'title', 'share_token']`. Remove `share_token`:
```php
protected $fillable = ['slug', 'title'];
```

Update `generateShareToken()` and the `unshare()` controller to use `$plan->share_token = ...` and `$plan->save()` instead of `$plan->update(['share_token' => ...])`.

In `PlannerController::unshare()`:
```php
$plan->share_token = null;
$plan->save();
```

In `Plan::generateShareToken()`:
```php
public function generateShareToken(): string
{
    $token = Str::random(32);
    $this->share_token = $token;
    $this->save();
    return $token;
}
```

- [ ] **Step 2: Run tests**

```bash
php artisan test --compact --filter=Planner
```

- [ ] **Step 3: Run Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
```

---

### Task 36: Replace placeholder ExampleTest

**Files:**
- Modify: `tests/Unit/ExampleTest.php`

- [ ] **Step 1: Replace with a real test or delete**

Replace the placeholder with a real unit test (e.g., test GeoUtils):
```php
<?php

namespace Tests\Unit;

use App\Support\GeoUtils;
use PHPUnit\Framework\TestCase;

class GeoUtilsTest extends TestCase
{
    public function test_null_island_detection(): void
    {
        $this->assertTrue(GeoUtils::isNullIsland(null, null));
        $this->assertTrue(GeoUtils::isNullIsland(0.05, 0.05));
        $this->assertFalse(GeoUtils::isNullIsland(50.0, -1.0));
    }

    public function test_haversine_known_distance(): void
    {
        // Southampton to Cowes ~10nm
        $nm = GeoUtils::haversineNm(50.9097, -1.4044, 50.7622, -1.2996);
        $this->assertGreaterThan(8, $nm);
        $this->assertLessThan(12, $nm);
    }
}
```

Rename the file to `GeoUtilsTest.php`.

- [ ] **Step 2: Run test**

```bash
php artisan test --compact --filter=GeoUtilsTest
```

- [ ] **Step 3: Commit**

---

### Task 37: Use maritime degrees-minutes format for overlay coordinates

**Files:**
- Modify: `resources/js/scarlet.js:62-67`

- [ ] **Step 1: Replace decimal degrees with degrees-minutes format**

Replace `formatCoord`:
```js
export function formatCoord(lat, lon) {
    if (lat == null || lon == null) return '--';
    const latDir = lat >= 0 ? 'N' : 'S';
    const lonDir = lon >= 0 ? 'E' : 'W';
    const fmtDM = (deg) => {
        const abs = Math.abs(deg);
        const d = Math.floor(abs);
        const m = ((abs - d) * 60).toFixed(2);
        return `${d}°${m.padStart(5, '0')}'`;
    };
    return `${fmtDM(lat)}${latDir}  ${fmtDM(lon)}${lonDir}`;
}
```

- [ ] **Step 2: Build and commit**

```bash
npm run build
```

---

### Task 38: Store HDOP as float for precision

**Files:**
- Modify: `app/Services/GpsService.php:36`

- [ ] **Step 1: Remove integer cast from HDOP**

In `app/Services/GpsService.php`, change:
```php
$gps->hdop = (int) ($data['gps_hdop'] ?? 9999);
```
to:
```php
$gps->hdop = (float) ($data['gps_hdop'] ?? 9999);
```

- [ ] **Step 2: Run Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
```

---

### Task 39: Fix component directory casing inconsistency

**Files:**
- `resources/js/Components/` (capitalized — used by Admin subdirectory)
- `resources/js/components/` (lowercase — used by shared components)

- [ ] **Step 1: Verify which casing convention is dominant**

Check imports across the codebase. The Admin layout uses `@/Components/Admin/...` and shared components use `@/components/...`. Standardize on lowercase `components/` since that's the more common convention and matches Vue/Vite defaults.

- [ ] **Step 2: Move `Components/Admin/` to `components/Admin/`**

```bash
mv resources/js/Components/Admin resources/js/components/Admin
mv resources/js/Components/ToastContainer.vue resources/js/components/ToastContainer.vue
rmdir resources/js/Components
```

- [ ] **Step 3: Update all imports referencing `@/Components/`**

Search and replace `@/Components/` with `@/components/` in all `.vue` files:
```bash
grep -rl '@/Components/' resources/js/ --include="*.vue" | xargs sed -i 's|@/Components/|@/components/|g'
```

- [ ] **Step 4: Build and verify**

```bash
npm run build
```

- [ ] **Step 5: Commit**

---

### Task 40: Create missing BoatSetting and Invite factories

**Files:**
- Create: `database/factories/BoatSettingFactory.php`
- Create: `database/factories/InviteFactory.php`

- [ ] **Step 1: Create BoatSettingFactory**

```bash
php artisan make:factory BoatSettingFactory --no-interaction
```

```php
public function definition(): array
{
    return [
        'key' => fake()->unique()->word(),
        'value' => fake()->word(),
    ];
}
```

- [ ] **Step 2: Create InviteFactory**

```bash
php artisan make:factory InviteFactory --no-interaction
```

```php
public function definition(): array
{
    return [
        'email' => fake()->unique()->safeEmail(),
        'token' => fake()->sha1(),
        'invited_by' => \App\Models\User::factory(),
    ];
}
```

- [ ] **Step 3: Run Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
```

---

### Task 41: Add max retry limit to video feed

**Files:**
- Modify: `resources/js/composables/useVideoFeed.js`

- [ ] **Step 1: Add retry counter and max limit**

Near the top of the composable, add:
```js
const MAX_RETRIES = 20;
let retryCount = 0;
```

In the `connect()` function, increment and check:
```js
if (retryCount >= MAX_RETRIES) {
    console.warn('Video feed: max retries reached, stopping');
    setActive(false);
    return;
}
retryCount++;
```

Reset `retryCount = 0` on successful connection (when video starts playing).

- [ ] **Step 2: Commit**

---

### Task 42: Change Octane default to FrankenPHP

**Files:**
- Modify: `config/octane.php:41`

- [ ] **Step 1: Change default server**

Change:
```php
'server' => env('OCTANE_SERVER', 'roadrunner'),
```
to:
```php
'server' => env('OCTANE_SERVER', 'frankenphp'),
```

- [ ] **Step 2: Commit**

---

### Task 43: Update Node.js to LTS in Dockerfile

**Files:**
- Modify: `docker/production/Dockerfile:19`

- [ ] **Step 1: Change Node 23 to Node 22 LTS**

Change:
```dockerfile
FROM node:23 AS npm
```
to:
```dockerfile
FROM node:22 AS npm
```

Node 22 is the current LTS release.

- [ ] **Step 2: Commit**

---

### Task 44: Replace hardcoded APP_KEY in .env.example

**Files:**
- Modify: `.env.example:3`

- [ ] **Step 1: Clear the APP_KEY value**

Change:
```
APP_KEY=base64:A/QLzK6J0KhdQrZi9CHpo7HTSBxkF4y4MyIQuoEze8E=
```
to:
```
APP_KEY=
```

- [ ] **Step 2: Commit**

---

## Phase 9: Additional Planner Fixes (User Request)

### Task 45: Fix shared planner view — visibility toggle should not rezoom/recenter map

**Files:**
- Modify: `resources/js/Pages/Public/Planner.vue`

- [ ] **Step 1: Ensure toggle visibility doesn't trigger map fit/zoom**

Review the Planner.vue component. When visibility toggles change, the map should NOT call `fitBounds()` or `setView()`. Only fit bounds on initial load or explicit user action.

If the map recalculates bounds in a watcher that fires on group/route visibility changes, add a guard:
- Track whether the map has been initialized
- Only fit bounds once on mount, not on subsequent visibility changes

- [ ] **Step 2: Verify in browser and commit**

---

### Task 46: Add per-route visibility toggles to shared planner view

**Files:**
- Modify: `resources/js/Pages/Public/Planner.vue`

- [ ] **Step 1: Add route-level toggle checkboxes within each group**

Each route within a group should have its own visibility checkbox in the sidebar, similar to the group-level toggle but controlling individual route polylines.

- [ ] **Step 2: Wire toggles to map layer visibility**

When a route toggle is unchecked, hide that specific route's polyline/markers on the map without affecting other routes in the group.

- [ ] **Step 3: Verify in browser and commit**

---

## Final Verification

### Task 47: Full test suite and final checks

- [ ] **Step 1: Run full test suite**

```bash
php artisan test --compact
```

- [ ] **Step 2: Run Pint on all modified files**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 3: Build frontend**

```bash
npm run build
```

- [ ] **Step 4: Verify no regressions**

Review git diff for any unintended changes.
