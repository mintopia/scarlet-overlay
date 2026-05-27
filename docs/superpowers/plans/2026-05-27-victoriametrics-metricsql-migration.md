# VictoriaMetrics MetricsQL Migration

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace PromQL-compatible workarounds with native VictoriaMetrics MetricsQL features to simplify code and reduce HTTP round-trips.

**Architecture:** Five independent improvements to PrometheusService and config/scarlet.php: (1) replace `last_over_time()` wrapping of MQTT sensors with `keep_last_value()`, (2) merge GPS+SignalK position fallback into single `or` queries, (3) simplify null-island filtering with MetricsQL `default()`, (4) make `queryMultiple()` parallel using `Http::pool()`, (5) replace the `queryFresh`/`queryWithAge`/`queryLastOverTime` staleness chain with `lag()`.

**Tech Stack:** PHP 8.4, Laravel 12, VictoriaMetrics MetricsQL, PHPUnit 11

---

### Task 1: Replace `last_over_time()` with `keep_last_value()` for MQTT sensors

MQTT/zigbee sensors report on change, not at fixed intervals. The config currently wraps them in `last_over_time(...[2h])` to handle gaps. VictoriaMetrics' `keep_last_value()` does this automatically in range queries without a lookback window.

**Files:**
- Modify: `config/scarlet.php` (lines 119-131 history mappings, lines 328-415 explore mappings, lines 508-513 log mappings)
- Test: `tests/Unit/PrometheusServiceTest.php`

- [ ] **Step 1: Update history mappings in config/scarlet.php**

Replace `last_over_time()` wrapping with `keep_last_value()` in the `history` section:

```php
'history' => [
    'track_latitude' => 'max(scarlet_gps_latitude_deg != 0)',
    'track_longitude' => 'max(scarlet_gps_longitude_deg != 0)',
    'track_sog' => 'max(scarlet_gps_speed_kn)',
    'battery' => 'max(scarlet_signalk_electrical_batteries_0_voltage)',
    'speed' => 'max(scarlet_signalk_navigation_speedOverGround) * 1.94384',
    'temp_forepeak' => 'max(keep_last_value(scarlet_mqtt_temperature{topic="zigbee2mqtt/Forepeak cabin"}))',
    'temp_quarterberth' => 'max(keep_last_value(scarlet_mqtt_temperature{topic="zigbee2mqtt/Quarterberth"}))',
    'temp_main_cabin' => 'max(keep_last_value(scarlet_mqtt_temperature{topic="zigbee2mqtt/Main Cabin"}))',
    'humidity_forepeak' => 'max(keep_last_value(scarlet_mqtt_humidity{topic="zigbee2mqtt/Forepeak cabin"}))',
    'humidity_quarterberth' => 'max(keep_last_value(scarlet_mqtt_humidity{topic="zigbee2mqtt/Quarterberth"}))',
    'humidity_main_cabin' => 'max(keep_last_value(scarlet_mqtt_humidity{topic="zigbee2mqtt/Main Cabin"}))',
    'pressure_forepeak' => 'max(keep_last_value(scarlet_mqtt_pressure{topic="zigbee2mqtt/Forepeak cabin"}))',
    'battery_current' => 'max(scarlet_signalk_electrical_batteries_0_current)',
    'battery_power' => 'max(scarlet_signalk_electrical_batteries_0_current) * max(scarlet_signalk_electrical_batteries_0_voltage)',
    'fuel_level' => 'max(scarlet_signalk_tanks_fuel_currentLevel) * 100',
    'water_level' => 'max(keep_last_value(scarlet_mqtt_percent{topic="watertank"}))',
    'cpu_usage' => 'max(scarlet_system_cpu_usage_percent)',
],
```

- [ ] **Step 2: Update explore mappings**

Replace `last_over_time()` with `keep_last_value()` in all cabin/tank explore metrics:

```php
'temp_main_cabin' => [
    // ... other keys unchanged ...
    'query' => 'keep_last_value(scarlet_mqtt_temperature{topic="zigbee2mqtt/Main Cabin"})',
],
'temp_forepeak' => [
    // ... other keys unchanged ...
    'query' => 'keep_last_value(scarlet_mqtt_temperature{topic="zigbee2mqtt/Forepeak cabin"})',
],
'temp_quarterberth' => [
    // ... other keys unchanged ...
    'query' => 'keep_last_value(scarlet_mqtt_temperature{topic="zigbee2mqtt/Quarterberth"})',
],
'humidity_forepeak' => [
    // ... other keys unchanged ...
    'query' => 'keep_last_value(scarlet_mqtt_humidity{topic="zigbee2mqtt/Forepeak cabin"})',
],
'humidity_quarterberth' => [
    // ... other keys unchanged ...
    'query' => 'keep_last_value(scarlet_mqtt_humidity{topic="zigbee2mqtt/Quarterberth"})',
],
'humidity_main_cabin' => [
    // ... other keys unchanged ...
    'query' => 'keep_last_value(scarlet_mqtt_humidity{topic="zigbee2mqtt/Main Cabin"})',
],
'pressure_forepeak' => [
    // ... other keys unchanged ...
    'query' => 'keep_last_value(scarlet_mqtt_pressure{topic="zigbee2mqtt/Forepeak cabin"})',
],
'water_level' => [
    // ... other keys unchanged ...
    'query' => 'keep_last_value(scarlet_mqtt_percent{topic="watertank"})',
],
```

- [ ] **Step 3: Update log mappings**

```php
'log' => [
    // ... latitude/longitude/gps_heading/trip_log unchanged ...
    'pressure' => 'max(keep_last_value(scarlet_mqtt_pressure{topic="zigbee2mqtt/Forepeak cabin"}))',
    // ... aws/awa/stw/heading/cog unchanged ...
    'wp_distance' => 'max(scarlet_signalk_navigation_courseGreatCircle_nextPoint_distance) / 1852',
    'wp_ttg' => 'max(scarlet_signalk_navigation_courseGreatCircle_nextPoint_timeToGo)',
    'battery_soc' => 'max(scarlet_signalk_electrical_batteries_0_capacity_stateOfCharge) * 100',
    'water_level' => 'max(keep_last_value(scarlet_mqtt_percent{topic="watertank"}))',
    'fuel_level' => 'max(scarlet_signalk_tanks_fuel_currentLevel) * 100',
],
```

- [ ] **Step 4: Run existing tests to verify no regressions**

Run: `php artisan test --compact`
Expected: All tests pass (config changes don't break any assertions since queries are string config values)

- [ ] **Step 5: Commit**

```bash
git add config/scarlet.php
git commit -m "refactor: replace last_over_time() with keep_last_value() for MQTT sensors

VictoriaMetrics' keep_last_value() fills gaps automatically for
report-on-change sensors without needing a fixed lookback window."
```

---

### Task 2: Merge GPS + SignalK position into single `or` queries

Currently `getGpsMetrics()` makes two separate HTTP batches (GPS + SignalK) and merges in PHP. MetricsQL's `or` operator works per-datapoint, so `signalk_lat or gps_lat` returns SignalK when available and falls back to GPS in one query.

**Files:**
- Modify: `config/scarlet.php` (lines 97-111: gps + signalk_position mappings)
- Modify: `app/Services/MetricsService.php:63-84` (getGpsMetrics)
- Modify: `app/Services/MetricsService.php:167-182` (getAllMetricsAt GPS section)
- Test: `tests/Unit/SunTimesTest.php` (existing tests use MetricsService mock, unaffected)

- [ ] **Step 1: Merge gps and signalk_position config sections**

In `config/scarlet.php`, replace the separate `gps` and `signalk_position` sections:

```php
'gps' => [
    'latitude' => 'scarlet_signalk_navigation_position_latitude != 0 or scarlet_gps_latitude_deg != 0',
    'longitude' => 'scarlet_signalk_navigation_position_longitude != 0 or scarlet_gps_longitude_deg != 0',
    'altitude' => 'scarlet_gps_altitude_meters',
    'satellites' => 'scarlet_gps_satellites',
    'hdop' => 'scarlet_gps_hdop',
    'speed' => 'scarlet_gps_speed_kn',
    'heading' => 'scarlet_gps_heading_deg',
],
```

Remove the `signalk_position` section entirely.

- [ ] **Step 2: Simplify getGpsMetrics() in MetricsService**

The fallback is now handled by `or` in the query. Remove the second query batch:

```php
public function getGpsMetrics(): array
{
    $gps = $this->prometheus->queryMultiple(
        config('scarlet.metrics.mappings.gps')
    );

    if ($this->isNullIsland($gps['latitude'], $gps['longitude'])) {
        $gps['latitude'] = null;
        $gps['longitude'] = null;
    }

    return $gps;
}
```

- [ ] **Step 3: Simplify GPS section in getAllMetricsAt()**

Replace the two-batch GPS lookup at lines 167-182:

```php
$gps = $this->prometheus->queryMultipleAt(
    config('scarlet.metrics.mappings.gps'),
    $timestamp
);
if ($this->isNullIsland($gps['latitude'] ?? null, $gps['longitude'] ?? null)) {
    $gps['latitude'] = null;
    $gps['longitude'] = null;
}
```

- [ ] **Step 4: Update getGpsTrack() to use merged queries**

In `getGpsTrack()`, the SignalK → GPS fallback now happens at query level. Update the config references. In `config/scarlet.php` history section, update track lat/lng:

```php
'track_latitude' => 'max(scarlet_signalk_navigation_position_latitude != 0 or scarlet_gps_latitude_deg != 0)',
'track_longitude' => 'max(scarlet_signalk_navigation_position_longitude != 0 or scarlet_gps_longitude_deg != 0)',
```

Simplify `getGpsTrack()` to remove the SignalK-then-GPS fallback:

```php
public function getGpsTrack(string $duration = '48h', string $step = '30s'): array
{
    $history = config('scarlet.metrics.mappings.history');

    $latData = $this->prometheus->queryRange($history['track_latitude'], $duration, $step, fillGaps: false);
    $lngData = $this->prometheus->queryRange($history['track_longitude'], $duration, $step, fillGaps: false);
    $sogData = $this->prometheus->queryRange($history['track_sog'], $duration, $step, fillGaps: false);

    $lngByTs = collect($lngData)->keyBy('timestamp');
    $sogByTs = collect($sogData)->keyBy('timestamp');

    $raw = [];
    foreach ($latData as $point) {
        $ts = $point['timestamp'];
        $lng = $lngByTs->get($ts);
        if (! $lng) {
            continue;
        }

        if ($this->isNullIsland($point['value'], $lng['value'])) {
            continue;
        }

        $sog = $sogByTs->get($ts);
        $raw[] = [$point['value'], $lng['value'], $sog['value'] ?? 0];
    }

    return $this->filterTrackOutliers($raw);
}
```

- [ ] **Step 5: Update log mappings for merged position**

In `config/scarlet.php` log section, merge the separate GPS and SignalK latitude/longitude entries:

```php
'log' => [
    'latitude' => 'max(scarlet_signalk_navigation_position_latitude != 0 or scarlet_gps_latitude_deg != 0)',
    'longitude' => 'max(scarlet_signalk_navigation_position_longitude != 0 or scarlet_gps_longitude_deg != 0)',
    // Remove 'signalk_latitude' and 'signalk_longitude' entries
    'gps_heading' => 'max(scarlet_gps_heading_deg)',
    // ... rest unchanged ...
],
```

Then update `getLogData()` to remove any references to `signalk_latitude`/`signalk_longitude` if the log consumer merges them. Check current usage: the log data returns rows with both `latitude` and `signalk_latitude` keys. After this change, only `latitude`/`longitude` exist (already merged at query level). Remove the separate signalk entries from the log config.

- [ ] **Step 6: Update ImportJourneyFromPrometheus job**

The job at `app/Jobs/ImportJourneyFromPrometheus.php` currently queries SignalK and GPS separately. Simplify to use the merged query:

```php
public function handle(PrometheusService $prometheus): void
{
    $journey = Journey::findOrFail($this->journeyId);
    $start = Carbon::parse($this->startTime)->timestamp;
    $end = Carbon::parse($this->endTime)->timestamp;
    $step = '30s';

    $history = config('scarlet.metrics.mappings.history');
    $latData = $prometheus->queryRange($history['track_latitude'], '', $step, $start, $end, fillGaps: false);
    $lngData = $prometheus->queryRange($history['track_longitude'], '', $step, $start, $end, fillGaps: false);
    $lngByTs = collect($lngData)->keyBy('timestamp');

    $metrics = [
        'speed_sog' => 'scarlet_gps_speed_kn',
        'heading' => 'scarlet_gps_heading_deg',
        'depth' => 'scarlet_boat_depth_meters',
        'wind_speed_true' => 'scarlet_boat_wind_speed_kn',
        'wind_direction_true' => 'scarlet_boat_wind_direction_deg',
        'wind_speed_apparent' => 'scarlet_signalk_environment_wind_speedApparent * 1.94384',
        'wind_angle_apparent' => 'scarlet_signalk_environment_wind_angleApparent * 180 / 3.14159265359',
        'speed_stw' => 'scarlet_signalk_navigation_speedThroughWater * 1.94384',
        'cog' => 'scarlet_signalk_navigation_courseOverGroundTrue * 180 / 3.14159265359',
        'house_battery_voltage' => 'scarlet_signalk_electrical_batteries_0_voltage',
        'house_battery_current' => 'scarlet_signalk_electrical_batteries_0_current',
        'heel' => 'scarlet_signalk_navigation_attitude_roll * 180 / 3.14159265359',
    ];

    $data = [];
    foreach ($latData as $point) {
        $ts = $point['timestamp'];
        $lng = $lngByTs->get($ts);
        if (! $lng) {
            continue;
        }
        if (abs($point['value']) < 0.1 && abs($lng['value']) < 0.1) {
            continue;
        }
        $data[$ts] = [
            'recorded_at' => date('Y-m-d H:i:s', $ts),
            'latitude' => $point['value'],
            'longitude' => $lng['value'],
        ];
    }

    foreach ($metrics as $key => $query) {
        $results = $prometheus->queryRange($query, '', $step, $start, $end, fillGaps: false);
        foreach ($results as $point) {
            $ts = $point['timestamp'];
            if (isset($data[$ts])) {
                $data[$ts][$key] = $point['value'];
            }
        }
    }

    ksort($data);

    $batch = [];
    foreach ($data as $row) {
        $batch[] = array_merge(['journey_id' => $journey->id], $row);

        if (count($batch) >= 500) {
            JourneyTrackPoint::insert($batch);
            $batch = [];
        }
    }

    if (! empty($batch)) {
        JourneyTrackPoint::insert($batch);
    }

    $pointCount = $journey->trackPoints()->count();
    Log::info("Imported {$pointCount} track points for journey #{$journey->id}");
}
```

- [ ] **Step 7: Run tests**

Run: `php artisan test --compact`
Expected: All tests pass

- [ ] **Step 8: Commit**

```bash
git add config/scarlet.php app/Services/MetricsService.php app/Jobs/ImportJourneyFromPrometheus.php
git commit -m "refactor: merge GPS + SignalK position into single or queries

MetricsQL's or operator handles per-datapoint fallback, eliminating
the need for separate query batches and PHP-side merging."
```

---

### Task 3: Simplify null-island filtering with MetricsQL `!= 0`

The current config uses `(metric > 0.1) or (metric < -0.1)` to filter null island. After Task 2 we already switched to `metric != 0`. This task cleans up any remaining instances and removes the app-side `isNullIsland()` checks that are now redundant for query-filtered data.

Note: `isNullIsland()` should be kept for data that arrives via routes other than these config queries (e.g., websocket data validation). But for config-queried positions, the filtering now happens at the query level.

**Files:**
- Verify: `config/scarlet.php` - all position queries already use `!= 0` from Task 2
- Modify: `app/Services/MetricsService.php` - review `isNullIsland()` call sites

- [ ] **Step 1: Verify config queries use `!= 0`**

After Task 2, all position queries in `gps`, `history.track_*`, and `log` sections should already use `!= 0`. Verify no `> 0.1) or (... < -0.1)` patterns remain.

- [ ] **Step 2: Run tests**

Run: `php artisan test --compact`
Expected: All tests pass

- [ ] **Step 3: Commit (if any changes)**

```bash
git add config/scarlet.php app/Services/MetricsService.php
git commit -m "refactor: clean up null-island filtering with MetricsQL != 0

Query-level filtering replaces verbose (metric > 0.1) or (metric < -0.1)
patterns. App-side isNullIsland() kept as safety net."
```

---

### Task 4: Make `queryMultiple()` parallel with `Http::pool()`

`queryMultiple()` runs queries serially. `queryMultipleAt()` already uses `Http::pool()` for parallelism. Apply the same pattern to `queryMultiple()`.

**Files:**
- Modify: `app/Services/PrometheusService.php:306-314` (queryMultiple method)
- Test: `tests/Unit/PrometheusServiceTest.php`

- [ ] **Step 1: Write test for parallel queryMultiple**

Add to `tests/Unit/PrometheusServiceTest.php`:

```php
public function test_query_multiple_returns_values_for_all_keys(): void
{
    Http::fake([
        '*/api/v1/query*' => Http::response([
            'status' => 'success',
            'data' => [
                'resultType' => 'vector',
                'result' => [['value' => [1716000000, '42.0']]],
            ],
        ]),
    ]);

    $service = new PrometheusService;
    $result = $service->queryMultiple([
        'speed' => 'scarlet_speed',
        'depth' => 'scarlet_depth',
    ]);

    $this->assertArrayHasKey('speed', $result);
    $this->assertArrayHasKey('depth', $result);
    $this->assertEquals(42.0, $result['speed']);
    $this->assertEquals(42.0, $result['depth']);
}

public function test_query_multiple_returns_null_for_missing_metrics(): void
{
    Http::fake([
        '*/api/v1/query*' => Http::response([
            'status' => 'success',
            'data' => ['resultType' => 'vector', 'result' => []],
        ]),
    ]);

    $service = new PrometheusService;
    $result = $service->queryMultiple([
        'speed' => 'scarlet_speed',
    ]);

    $this->assertArrayHasKey('speed', $result);
    $this->assertNull($result['speed']);
}
```

- [ ] **Step 2: Run tests to see them pass (existing behavior)**

Run: `php artisan test --compact --filter=test_query_multiple`
Expected: PASS (current serial implementation already works)

- [ ] **Step 3: Rewrite queryMultiple to use Http::pool()**

In `app/Services/PrometheusService.php`, replace the `queryMultiple` method:

```php
public function queryMultiple(array $queries): array
{
    $responses = Http::pool(function ($pool) use ($queries) {
        foreach ($queries as $key => $promql) {
            $pool->as($key)->timeout(5)->get("{$this->baseUrl}/api/v1/query", [
                'query' => $promql,
            ]);
        }
    });

    $results = [];
    foreach ($queries as $key => $promql) {
        try {
            $response = $responses[$key] ?? null;
            if ($response && $response->ok()) {
                $result = $response->json('data.result');
                $results[$key] = ! empty($result) ? (float) $result[0]['value'][1] : null;
            } else {
                $results[$key] = null;
            }
        } catch (\Throwable $e) {
            Log::warning("Prometheus query failed [{$promql}]: {$e->getMessage()}");
            $results[$key] = null;
        }
    }

    return $results;
}
```

Note: this removes the `last_over_time` fallback that `query()` had. The `query()` method called `queryLastOverTime()` when data was empty. For the parallel version, we skip this because: (a) the MQTT sensors now use `keep_last_value()` in the query itself, (b) SignalK/GPS sensors report frequently so staleness is handled by the freshness checks elsewhere, (c) the old fallback added a second serial round-trip per-metric which defeated the purpose of parallelism.

- [ ] **Step 4: Run tests**

Run: `php artisan test --compact`
Expected: All tests pass

- [ ] **Step 5: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 6: Commit**

```bash
git add app/Services/PrometheusService.php tests/Unit/PrometheusServiceTest.php
git commit -m "perf: parallelize queryMultiple() with Http::pool()

All instant metric queries now execute concurrently instead of
serially. Removes the per-metric last_over_time fallback since
MQTT sensors now use keep_last_value() at the query level."
```

---

### Task 5: Replace staleness chain with `lag()`

The current staleness detection uses a multi-layer chain: `queryFresh()` → `queryWithAge()` → `queryLastOverTime()`, each wrapping the query in `last_over_time()` with regex. VictoriaMetrics' `lag()` function returns the time since the last sample, letting us check freshness directly.

**Files:**
- Modify: `app/Services/PrometheusService.php` (queryFresh, queryTimestamp, queryWithAge, queryLastOverTime methods)
- Test: `tests/Unit/PrometheusServiceTest.php`

- [ ] **Step 1: Write tests for the new freshness approach**

Add to `tests/Unit/PrometheusServiceTest.php`:

```php
public function test_query_fresh_returns_value_when_recent(): void
{
    $now = now()->timestamp;
    Http::fake([
        '*/api/v1/query*' => Http::response([
            'status' => 'success',
            'data' => [
                'resultType' => 'vector',
                'result' => [['value' => [$now, '5.0']]],
            ],
        ]),
    ]);

    $service = new PrometheusService;
    $result = $service->queryFresh('scarlet_metric', 120);
    $this->assertEquals(5.0, $result);
}

public function test_query_fresh_returns_null_when_stale(): void
{
    $staleTs = now()->timestamp - 300;
    Http::fake([
        '*/api/v1/query*' => Http::response([
            'status' => 'success',
            'data' => [
                'resultType' => 'vector',
                'result' => [['value' => [$staleTs, '5.0']]],
            ],
        ]),
    ]);

    $service = new PrometheusService;
    $result = $service->queryFresh('scarlet_metric', 120);
    $this->assertNull($result);
}

public function test_query_timestamp_returns_last_seen_time(): void
{
    $ts = now()->timestamp - 30;
    Http::fake([
        '*/api/v1/query*' => Http::response([
            'status' => 'success',
            'data' => [
                'resultType' => 'vector',
                'result' => [['value' => [$ts, '42.0']]],
            ],
        ]),
    ]);

    $service = new PrometheusService;
    $result = $service->queryTimestamp('scarlet_metric');
    $this->assertEquals($ts, $result);
}
```

- [ ] **Step 2: Run tests to verify they pass with current implementation**

Run: `php artisan test --compact --filter="test_query_fresh|test_query_timestamp_returns"`
Expected: PASS

- [ ] **Step 3: Rewrite queryFresh using lag()**

Replace the `queryFresh`, `queryWithAge`, `queryLastOverTime`, and `queryTimestamp` methods. The key insight: VictoriaMetrics always returns the timestamp of the datapoint in the response `[timestamp, value]`. We can use `last_over_time(metric[lookback])` to get the most recent value within a window, and check its timestamp for freshness. But with `lag()` we can be smarter:

Actually, the simplest approach that preserves behavior: query `last_over_time(metric[24h])` once, check the returned timestamp for freshness. This collapses the multi-layer chain into a single query.

```php
public function queryFresh(string $promql, int $maxAge = 120): ?float
{
    $result = $this->queryWithTimestamp($promql, '24h');
    if ($result === null) {
        return null;
    }

    return $result['age'] <= $maxAge ? $result['value'] : null;
}

public function queryTimestamp(string $promql): ?int
{
    $result = $this->queryWithTimestamp($promql, '24h');

    return $result['timestamp'] ?? null;
}

protected function queryWithTimestamp(string $promql, string $lookback = '24h'): ?array
{
    $wrapped = preg_replace_callback(
        '/\b(scarlet_[a-zA-Z0-9_:]*)(\{[^}]*\})?/',
        fn ($m) => "last_over_time({$m[0]}[{$lookback}])",
        $promql,
    );

    if ($wrapped === $promql) {
        return null;
    }

    try {
        $response = Http::timeout(5)->get("{$this->baseUrl}/api/v1/query", [
            'query' => $wrapped,
        ]);

        if (! $response->ok()) {
            return null;
        }

        $result = $response->json('data.result');
        if (empty($result)) {
            return null;
        }

        $timestamp = (int) $result[0]['value'][0];

        return [
            'value' => (float) $result[0]['value'][1],
            'timestamp' => $timestamp,
            'age' => now()->timestamp - $timestamp,
        ];
    } catch (\Throwable $e) {
        Log::warning("Prometheus query failed [{$promql}]: {$e->getMessage()}");

        return null;
    }
}
```

Remove the old methods: `queryWithAge()`, `queryLastOverTime()`.

Also, the `query()` method currently falls back to `queryLastOverTime()`. Since `queryMultiple()` is now the primary path for batch queries (Task 4), simplify `query()` to just do the 24h lookback in one step:

```php
public function query(string $promql): ?float
{
    $result = $this->queryWithTimestamp($promql, '24h');

    return $result['value'] ?? null;
}
```

- [ ] **Step 4: Remove wrapLastOverTime() if unused**

Check if `wrapLastOverTime()` is used anywhere else:

Run: `grep -rn 'wrapLastOverTime' app/ --include='*.php'`

If only defined in PrometheusService and not called externally, remove it.

- [ ] **Step 5: Update queryMultipleWithStatus**

The `queryMultipleWithStatus()` method calls `queryFresh()` per-key, which is now simpler. No code change needed, but verify it still works by running related tests.

- [ ] **Step 6: Run all tests**

Run: `php artisan test --compact`
Expected: All tests pass

- [ ] **Step 7: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 8: Commit**

```bash
git add app/Services/PrometheusService.php tests/Unit/PrometheusServiceTest.php
git commit -m "refactor: simplify staleness detection with single-query lookback

Collapse the queryFresh/queryWithAge/queryLastOverTime chain into a
single queryWithTimestamp() method. Each freshness check is now one
HTTP request with last_over_time([24h]) instead of up to three."
```

---

### Task 6: Final cleanup and verification

- [ ] **Step 1: Run full test suite**

Run: `php artisan test --compact`
Expected: All tests pass

- [ ] **Step 2: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 3: Verify no stale references**

```bash
grep -rn 'signalk_position' config/scarlet.php app/ --include='*.php'
grep -rn 'queryLastOverTime\|queryWithAge' app/ --include='*.php'
```

Expected: No matches (all removed)

- [ ] **Step 4: Final commit if needed**

Only commit if cleanup was required.
