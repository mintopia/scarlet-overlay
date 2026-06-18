# Data Layer — Phase 3 Implementation Plan (Read-contract broadcast + UI recency)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Broadcast the canonical read contract (value + age + stale + resolved_source) plus the catalog version over the existing 15s Reverb push, **additively** (a new `canonical` payload block + `catalog_version` — no existing flat values reshaped), and surface recency/staleness on the fuel & water displays (the only canonical-backed metrics today). Honest "never show stale as live" for those two metrics.

**Architecture:** `CanonicalReader` gains `readMany()` + a `catalogVersion()` passthrough. `MetricsService::getAllMetrics()` adds `canonical` (the full contract per override key, populated only when the flag is on) and `catalog_version`. The `MetricsUpdated` event + `metrics:push` carry the two new fields (defaulted, so nothing breaks when the flag is off). The Vue composable exposes a reactive `canonical` map; `LevelBar` gains optional `age`/`stale` props rendering a subtle recency affordance, wired on the admin fuel/water bars.

**Tech Stack:** Laravel 12, PHP 8.4, PHPUnit 11; Vue 3 + Inertia + Laravel Echo (Reverb). Backend is TDD; frontend changes are additive and verified with `npm run build`.

**Scope:** Additive broadcast + recency UI for fuel/water. **OUT:** reshaping the flat boat/tracker/gps values to contracts (that is Phase 6 consumer cutover), surfacing recency on every metric, `mixed_source` precise computation (deferred — needs per-window source analysis in Phase 5/6). The reader stays flag-gated (`CANONICAL_READER_ENABLED`, default off): when off, `canonical` is `{}` and the UI shows exactly today's behaviour.

**Reference:** `PLAN-data-layer.md` Phase 3; Phase 1/2 delivered `CanonicalReader` (returns value/raw/unit/timestamp/age/stale/resolved_source) reading the DB catalog via `CanonicalCatalog`.

---

## File Structure
- `app/Services/CanonicalReader.php` — add `readMany()`, `catalogVersion()`
- `app/Services/MetricsService.php` — add `canonical` + `catalog_version` to `getAllMetrics()`
- `app/Events/MetricsUpdated.php` — add `canonical`, `catalogVersion` (defaulted)
- `app/Console/Commands/MetricsPushCommand.php` — pass the two new fields
- `resources/js/composables/useScarletMetrics.js` — expose reactive `canonical`
- `resources/js/components/Admin/LevelBar.vue` — optional `age`/`stale` recency affordance
- `resources/js/Pages/Admin/Dashboard.vue` — wire fuel/water bars to `canonical`
- Tests: `tests/Unit/CanonicalReaderTest.php` (append), `tests/Feature/CanonicalFuelWaterTest.php` (append)

---

## Task 1: Reader `readMany()` + `catalogVersion()`

**Files:** Modify `app/Services/CanonicalReader.php`; append to `tests/Unit/CanonicalReaderTest.php`.

- [ ] **Step 1: Failing tests** — append to `tests/Unit/CanonicalReaderTest.php`:

```php
    public function test_read_many_returns_contract_per_key(): void
    {
        $p = $this->createMock(PrometheusService::class);
        $p->method('queryWithTimestamp')->willReturn(['value' => 63.0, 'timestamp' => 1716000000, 'age' => 20]);
        $p->method('coverageRatio')->willReturn(0.9);

        $catalog = $this->createMock(CanonicalCatalog::class);
        $catalog->method('definition')->willReturnMap([
            ['fuel_level', [
                'label' => 'Diesel', 'unit' => '%', 'volatile' => false, 'trend_fn' => 'median', 'trend_window' => '10m',
                'staleness' => 3600, 'coverage_window_seconds' => 3600, 'coverage_min' => 0.5,
                'sources' => [['selector' => 'scarlet_mqtt_percent{topic="tanklevel"}']],
            ]],
        ]);

        $reader = new CanonicalReader($p, $catalog);
        $result = $reader->readMany(['fuel_level', 'missing_key']);

        $this->assertEqualsWithDelta(63.0, $result['fuel_level']['value'], 0.001);
        $this->assertNull($result['missing_key']);
    }

    public function test_catalog_version_delegates_to_catalog(): void
    {
        $catalog = $this->createMock(CanonicalCatalog::class);
        $catalog->method('version')->willReturn(7);

        $reader = new CanonicalReader($this->createMock(PrometheusService::class), $catalog);
        $this->assertSame(7, $reader->catalogVersion());
    }
```

- [ ] **Step 2: Run (red)** — `php artisan test --compact --filter='read_many|catalog_version_delegates' tests/Unit/CanonicalReaderTest.php` — FAIL (undefined methods).

- [ ] **Step 3: Implement** — add to `app/Services/CanonicalReader.php` (after `read()`):

```php
    /**
     * @param  array<int, string>  $keys
     * @return array<string, array<string, mixed>|null>
     */
    public function readMany(array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = $this->read($key);
        }

        return $out;
    }

    public function catalogVersion(): int
    {
        return $this->catalog->version();
    }
```

- [ ] **Step 4: Run (green)** — same filter — PASS.

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/CanonicalReader.php tests/Unit/CanonicalReaderTest.php
git commit -m "feat(canonical): reader readMany + catalogVersion passthrough

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 2: `MetricsService` canonical block + event/command wiring

**Files:** Modify `app/Services/MetricsService.php`, `app/Events/MetricsUpdated.php`, `app/Console/Commands/MetricsPushCommand.php`; append to `tests/Feature/CanonicalFuelWaterTest.php`.

- [ ] **Step 1: Failing test** — append to `tests/Feature/CanonicalFuelWaterTest.php`:

```php
    public function test_get_all_metrics_includes_canonical_block_when_enabled(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            '*' => \Illuminate\Support\Facades\Http::response(['status' => 'success', 'data' => ['result' => []]]),
        ]);

        Config::set('scarlet.canonical.enabled', true);
        Config::set('scarlet.canonical.overrides', ['fuel_level' => 'fuel_level', 'water_fresh_level' => 'water_level']);

        /** @var CanonicalReader&MockObject $reader */
        $reader = $this->createMock(CanonicalReader::class);
        $reader->method('catalogVersion')->willReturn(4);
        $reader->method('readMany')->willReturn([
            'fuel_level' => ['value' => 63.0, 'raw' => 63.0, 'unit' => '%', 'timestamp' => 1, 'age' => 20, 'stale' => false, 'resolved_source' => 'mqtt'],
            'water_fresh_level' => null,
        ]);
        // getBoatMetrics() also calls read() for the override values
        $reader->method('read')->willReturn(['value' => 63.0, 'raw' => 63.0, 'unit' => '%', 'timestamp' => 1, 'age' => 20, 'stale' => false, 'resolved_source' => 'mqtt']);
        $this->app->instance(CanonicalReader::class, $reader);

        $all = $this->app->make(MetricsService::class)->getAllMetrics();

        $this->assertSame(4, $all['catalog_version']);
        $this->assertArrayHasKey('fuel_level', $all['canonical']);
        $this->assertEqualsWithDelta(63.0, $all['canonical']['fuel_level']['value'], 0.001);
        $this->assertSame(20, $all['canonical']['fuel_level']['age']);
        // null contracts are filtered out
        $this->assertArrayNotHasKey('water_fresh_level', $all['canonical']);
    }

    public function test_get_all_metrics_canonical_empty_when_disabled(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            '*' => \Illuminate\Support\Facades\Http::response(['status' => 'success', 'data' => ['result' => []]]),
        ]);
        Config::set('scarlet.canonical.enabled', false);

        /** @var CanonicalReader&MockObject $reader */
        $reader = $this->createMock(CanonicalReader::class);
        $reader->expects($this->never())->method('readMany');
        $this->app->instance(CanonicalReader::class, $reader);

        $all = $this->app->make(MetricsService::class)->getAllMetrics();

        $this->assertSame([], $all['canonical']);
    }
```

- [ ] **Step 2: Run (red)** — `php artisan test --compact tests/Feature/CanonicalFuelWaterTest.php` — FAIL (no `canonical`/`catalog_version` keys).

- [ ] **Step 3: Implement**

(a) In `app/Services/MetricsService.php`, add a method:

```php
    /**
     * @return array{contracts: array<string, array<string, mixed>>, version: int}
     */
    private function getCanonicalContracts(): array
    {
        if (! config('scarlet.canonical.enabled')) {
            return ['contracts' => [], 'version' => 0];
        }

        $keys = array_keys(config('scarlet.canonical.overrides', []));
        $contracts = array_filter($this->canonical->readMany($keys), fn ($c) => $c !== null);

        return ['contracts' => $contracts, 'version' => $this->canonical->catalogVersion()];
    }
```

In `getAllMetrics()`, before the `return [...]`, add:

```php
        $canonical = $this->getCanonicalContracts();
```

and add two keys to the returned array:

```php
            'canonical' => $canonical['contracts'],
            'catalog_version' => $canonical['version'],
```

(Leave `getAllMetricsAt()` unchanged.)

(b) In `app/Events/MetricsUpdated.php`, add two defaulted constructor params after `$timestamp`:

```php
    public function __construct(
        public array $boat,
        public array $tracker,
        public array $gps,
        public ?array $weather,
        public array $settings,
        public ?array $sun,
        public string $timestamp,
        public array $canonical = [],
        public int $catalogVersion = 0,
    ) {}
```

(c) In `app/Console/Commands/MetricsPushCommand.php`, update the dispatch to pass the new fields:

```php
                MetricsUpdated::dispatch(
                    $all['boat'],
                    $all['tracker'],
                    $all['gps'],
                    $all['weather'],
                    $all['settings'],
                    $all['sun'],
                    $all['timestamp'],
                    $all['canonical'],
                    $all['catalog_version'],
                );
```

- [ ] **Step 4: Run (green)** — `php artisan test --compact tests/Feature/CanonicalFuelWaterTest.php` — PASS (4 tests).

- [ ] **Step 5: Regression** — `php artisan test --compact tests/Feature/ApiMetricsTest.php tests/Unit/SunTimesTest.php` — PASS (MetricsService constructor unchanged; event params are defaulted).

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/MetricsService.php app/Events/MetricsUpdated.php app/Console/Commands/MetricsPushCommand.php tests/Feature/CanonicalFuelWaterTest.php
git commit -m "feat(canonical): broadcast canonical read-contract block + catalog_version

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 3: Frontend — composable `canonical` + LevelBar recency + admin wiring

No JS test framework in repo; verify with `npm run build`. Changes are additive and must not alter behaviour when `canonical` is absent/empty.

**Files:** Modify `resources/js/composables/useScarletMetrics.js`, `resources/js/components/Admin/LevelBar.vue`, `resources/js/Pages/Admin/Dashboard.vue`.

- [ ] **Step 1: Composable — expose reactive `canonical`**

In `resources/js/composables/useScarletMetrics.js`:
- After the `const boat = ref(...)` block (near line 20), add:
```js
    const canonical = ref(initialMetrics?.canonical ?? {});
```
- In the `.metrics.updated` listener (the `echoChannel.listen('.metrics.updated', (data) => {...})` body), after `boat.value = merged;`, add:
```js
            if (data.canonical) canonical.value = data.canonical;
```
- Add `canonical` to the returned object (in the `return { ... }` near line 267, add `canonical,` next to `boat, gps, weather, ...`).

- [ ] **Step 2: LevelBar — optional age/stale recency affordance**

Replace `resources/js/components/Admin/LevelBar.vue` `<script setup>` props + add a formatter, and add the affordance to the template. Full updated file:

```vue
<script setup>
const props = defineProps({
    value: { type: Number, default: 0 },
    label: { type: String, required: true },
    color: { type: String, default: 'green' },
    age: { type: Number, default: null },   // seconds since last real sample
    stale: { type: Boolean, default: false },
})

const gradients = {
    green: 'linear-gradient(90deg, var(--color-green), color-mix(in oklch, var(--color-green) 80%, white))',
    amber: 'linear-gradient(90deg, var(--color-amber), color-mix(in oklch, var(--color-amber) 80%, white))',
    blue: 'linear-gradient(90deg, var(--color-blue), color-mix(in oklch, var(--color-blue) 80%, white))',
}

const glows = {
    green: '0 0 6px color-mix(in oklch, var(--color-green) 15%, transparent)',
    amber: '0 0 6px color-mix(in oklch, var(--color-amber) 12%, transparent)',
    blue: '0 0 6px color-mix(in oklch, var(--color-blue) 12%, transparent)',
}

function formatAge(s) {
    if (s == null) return ''
    if (s < 60) return `${Math.round(s)}s`
    if (s < 3600) return `${Math.round(s / 60)}m`
    if (s < 86400) return `${Math.round(s / 3600)}h`
    return `${Math.round(s / 86400)}d`
}
</script>

<template>
    <div class="level-bar">
        <span class="level-bar__label">{{ label }}</span>
        <div class="level-bar__track">
            <div
                class="level-bar__fill"
                :style="{
                    width: Math.min(100, Math.max(0, value)) + '%',
                    background: gradients[color] || gradients.green,
                    boxShadow: value > 0 ? (glows[color] || glows.green) : 'none',
                }"
            />
        </div>
        <span
            v-if="age !== null"
            class="level-bar__age"
            :class="{ 'level-bar__age--stale': stale }"
            :title="stale ? 'Stale — last known value' : 'Age of last reading'"
        >{{ stale ? 'stale' : formatAge(age) }}</span>
        <span class="level-bar__value" :class="{ 'level-bar__value--stale': stale }">{{ value > 0 ? Math.round(value) + '%' : '—' }}</span>
    </div>
</template>

<style scoped>
.level-bar {
    display: flex;
    align-items: center;
    gap: 10px;
}
.level-bar__label {
    font-size: 12px;
    font-weight: 600;
    color: var(--color-text-dim);
    width: 56px;
    flex-shrink: 0;
}
.level-bar__track {
    flex: 1;
    height: 10px;
    background: var(--color-bg);
    border-radius: 5px;
    overflow: hidden;
}
.level-bar__fill {
    height: 100%;
    border-radius: 5px;
    transition: width 0.6s cubic-bezier(0.25, 1, 0.5, 1);
}
.level-bar__age {
    font-size: 10px;
    font-weight: 600;
    color: var(--color-text-dim);
    flex-shrink: 0;
    opacity: 0.7;
}
.level-bar__age--stale {
    color: var(--color-amber);
    opacity: 1;
}
.level-bar__value {
    font-family: 'Nunito Sans', sans-serif;
    font-size: 14px;
    font-weight: 600;
    width: 34px;
    text-align: right;
    flex-shrink: 0;
}
.level-bar__value--stale {
    opacity: 0.55;
}
</style>
```

- [ ] **Step 3: Admin Dashboard — wire fuel/water to canonical**

In `resources/js/Pages/Admin/Dashboard.vue`:
- Pull `canonical` from the composable. Find where `useScarletMetrics(...)` is destructured (the `const { ... } = useScarletMetrics(...)`) and add `canonical` to the destructured names.
- The fuel/water bars currently read `:value="fuelLevel"` / `:value="waterLevel"`. Add age/stale bindings sourced from `canonical` (canonical keys: `fuel_level` and `water_fresh_level`; the water bar's boat key is `water_level` but its canonical contract is `water_fresh_level`). Update the two `<LevelBar>` usages:
```vue
                <LevelBar
                    :value="fuelLevel"
                    label="Fuel"
                    color="amber"
                    :age="canonical?.fuel_level?.age ?? null"
                    :stale="canonical?.fuel_level?.stale ?? false"
                />
```
```vue
                <LevelBar
                    :value="waterLevel"
                    label="Water"
                    color="blue"
                    :age="canonical?.water_fresh_level?.age ?? null"
                    :stale="canonical?.water_fresh_level?.stale ?? false"
                />
```
(Match the existing indentation/attribute style in the file. If the bars are written inline on one line, expand them to multi-line as above.)

- [ ] **Step 4: Build** — `npm run build` — expect success (no errors). If `npm run build` is unavailable in the environment, report DONE_WITH_CONCERNS noting the build wasn't run.

- [ ] **Step 5: Commit**

```bash
git add resources/js/composables/useScarletMetrics.js resources/js/components/Admin/LevelBar.vue resources/js/Pages/Admin/Dashboard.vue
git commit -m "feat(canonical): surface age/stale recency on admin fuel/water bars

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Final verification
- [ ] `php artisan test --compact` — all PASS (watch `ApiMetricsTest`, `SunTimesTest`, `CanonicalFuelWaterTest`, `CanonicalReaderTest`).
- [ ] `npm run build` succeeds.
- [ ] `vendor/bin/pint --dirty --format agent` clean.
- [ ] Flag still OFF by default → `canonical` is `{}` in the payload and the LevelBars render exactly as before (age prop null → affordance hidden).

## Self-review notes
- Additive only: existing flat `boat`/`gps`/etc. values and all current consumers are untouched; the `canonical` block + `catalog_version` are new and ignored by code that doesn't read them.
- DEFERRED: `mixed_source` precise computation; surfacing recency beyond fuel/water; reshaping consumers to read contracts (Phase 6).
- Recency UI reuses existing idioms (text-dim + amber stale, like the dashboard's health dots / stale pills).
