# Journey/Passage System Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a journey system that records passages with persistent GPS track data, supports GPX route uploads, provides a shareable timeline replay view, and adds an admin dashboard.

**Architecture:** Two new database tables (`journeys`, `journey_track_points`) with Eloquent models. The existing `MetricsPushCommand` records track points during active journeys. A GPX parsing service extracts planned route waypoints. Admin CRUD via Inertia/Vue pages. Public journey replay view with timeline scrubber reuses the existing map composable. A queued job handles retrospective Prometheus imports.

**Tech Stack:** Laravel 12, PHP 8.2, Vue 3, Inertia.js, Tailwind CSS v4, Leaflet, Prometheus, Laravel Reverb (WebSocket), PHPUnit

**Spec:** `docs/superpowers/specs/2026-05-22-journey-system-design.md`

---

## Task Dependency Graph

```
Task 1 (Migrations)
  └─► Task 2 (Models)
        ├─► Task 3 (GPX Service)         ─┐
        ├─► Task 4 (Journey Service)      ├─► Task 7 (Admin Controller + Routes)
        ├─► Task 5 (Settings Migration)   │     ├─► Task 8 (Admin Nav + Dashboard Page)
        └─► Task 6 (Import Job)          ─┘     ├─► Task 9 (Admin Journey Pages)
                                                ├─► Task 10 (Settings Page Update)
                                                └─► Task 11 (Public Controller + Routes)
                                                      ├─► Task 12 (GPX Map Layer)
                                                      ├─► Task 13 (Dashboard/Overlay Integration)
                                                      └─► Task 14 (Journey Replay Page)
```

**Parallelisable groups after dependencies resolve:**
- After Task 2: Tasks 3, 4, 5, 6
- After Task 7: Tasks 8, 9, 10, 11
- After Task 11: Tasks 12, 13, 14

---

### Task 1: Database Migrations

**Files:**
- Create: `database/migrations/2026_05_22_000001_create_journeys_table.php`
- Create: `database/migrations/2026_05_22_000002_create_journey_track_points_table.php`

- [ ] **Step 1: Create the journeys migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journeys', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('from_port');
            $table->string('to_port');
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->enum('status', ['active', 'completed', 'abandoned'])->default('active');
            $table->boolean('is_public')->default(true);
            $table->string('gpx_route_path')->nullable();
            $table->json('route_waypoints')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journeys');
    }
};
```

Write this to `database/migrations/2026_05_22_000001_create_journeys_table.php`.

- [ ] **Step 2: Create the journey_track_points migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journey_track_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journey_id')->constrained()->cascadeOnDelete();
            $table->timestamp('recorded_at');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->float('speed_sog')->nullable();
            $table->float('speed_stw')->nullable();
            $table->float('heading')->nullable();
            $table->float('cog')->nullable();
            $table->float('depth')->nullable();
            $table->float('wind_speed_apparent')->nullable();
            $table->float('wind_angle_apparent')->nullable();
            $table->float('wind_speed_true')->nullable();
            $table->float('wind_direction_true')->nullable();
            $table->float('house_battery_voltage')->nullable();
            $table->float('house_battery_current')->nullable();
            $table->float('heel')->nullable();

            $table->index(['journey_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journey_track_points');
    }
};
```

Write this to `database/migrations/2026_05_22_000002_create_journey_track_points_table.php`.

- [ ] **Step 3: Run the migrations**

Run: `php artisan migrate`
Expected: Two new tables created without errors.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_05_22_000001_create_journeys_table.php database/migrations/2026_05_22_000002_create_journey_track_points_table.php
git commit -m "feat: add journeys and journey_track_points migrations"
```

---

### Task 2: Eloquent Models

**Files:**
- Create: `app/Models/Journey.php`
- Create: `app/Models/JourneyTrackPoint.php`
- Create: `database/factories/JourneyFactory.php`
- Test: `tests/Feature/JourneyModelTest.php`

- [ ] **Step 1: Write the Journey model test**

```php
<?php

namespace Tests\Feature;

use App\Models\Journey;
use App\Models\JourneyTrackPoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JourneyModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_slug_is_generated_from_ports(): void
    {
        $journey = Journey::create([
            'from_port' => 'Lymington',
            'to_port' => 'Yarmouth',
            'started_at' => now(),
        ]);

        $this->assertEquals('lymington-to-yarmouth', $journey->slug);
        $this->assertEquals('Lymington → Yarmouth', $journey->title);
    }

    public function test_duplicate_slug_gets_suffix(): void
    {
        Journey::create([
            'from_port' => 'Lymington',
            'to_port' => 'Yarmouth',
            'started_at' => now(),
            'status' => 'completed',
            'ended_at' => now(),
        ]);

        $second = Journey::create([
            'from_port' => 'Lymington',
            'to_port' => 'Yarmouth',
            'started_at' => now(),
        ]);

        $this->assertEquals('lymington-to-yarmouth-2', $second->slug);
    }

    public function test_only_one_active_journey_allowed(): void
    {
        Journey::create([
            'from_port' => 'Lymington',
            'to_port' => 'Yarmouth',
            'started_at' => now(),
            'status' => 'active',
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        Journey::startNew('Cowes', 'Portsmouth');
    }

    public function test_current_returns_active_journey(): void
    {
        $this->assertNull(Journey::current());

        $journey = Journey::create([
            'from_port' => 'Lymington',
            'to_port' => 'Yarmouth',
            'started_at' => now(),
            'status' => 'active',
        ]);

        $this->assertEquals($journey->id, Journey::current()->id);
    }

    public function test_distance_computes_from_track_points(): void
    {
        $journey = Journey::create([
            'from_port' => 'A',
            'to_port' => 'B',
            'started_at' => now(),
            'status' => 'active',
        ]);

        // ~60nm apart (1 degree of latitude)
        $journey->trackPoints()->create([
            'recorded_at' => now()->subHour(),
            'latitude' => 50.0,
            'longitude' => -1.5,
        ]);
        $journey->trackPoints()->create([
            'recorded_at' => now(),
            'latitude' => 51.0,
            'longitude' => -1.5,
        ]);

        $this->assertGreaterThan(59, $journey->distance);
        $this->assertLessThan(61, $journey->distance);
    }

    public function test_scopes(): void
    {
        Journey::create(['from_port' => 'A', 'to_port' => 'B', 'started_at' => now(), 'status' => 'active', 'is_public' => true]);
        Journey::create(['from_port' => 'C', 'to_port' => 'D', 'started_at' => now()->subDay(), 'ended_at' => now(), 'status' => 'completed', 'is_public' => false]);

        $this->assertCount(1, Journey::active()->get());
        $this->assertCount(1, Journey::completed()->get());
        $this->assertCount(1, Journey::public()->get());
    }
}
```

Write to `tests/Feature/JourneyModelTest.php`.

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/JourneyModelTest.php`
Expected: All tests fail (Journey class not found).

- [ ] **Step 3: Create the Journey model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Journey extends Model
{
    protected $fillable = [
        'slug', 'title', 'from_port', 'to_port', 'started_at', 'ended_at',
        'status', 'is_public', 'gpx_route_path', 'route_waypoints', 'notes',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'is_public' => 'boolean',
        'route_waypoints' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Journey $journey) {
            if (empty($journey->slug)) {
                $journey->slug = static::generateUniqueSlug($journey->from_port, $journey->to_port);
            }
            if (empty($journey->title)) {
                $journey->title = $journey->from_port . ' → ' . $journey->to_port;
            }
        });

        static::saved(function () {
            Cache::forget('journey.active');
        });
    }

    public function trackPoints(): HasMany
    {
        return $this->hasMany(JourneyTrackPoint::class)->orderBy('recorded_at');
    }

    // ── Scopes ──────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    // ── Static helpers ──────────────────────────────────────────────────

    public static function current(): ?self
    {
        return Cache::remember('journey.active', 60, function () {
            return static::active()->first();
        });
    }

    public static function startNew(string $fromPort, string $toPort, array $extra = []): self
    {
        if (static::active()->exists()) {
            throw ValidationException::withMessages([
                'status' => 'A journey is already active. End it before starting a new one.',
            ]);
        }

        return static::create(array_merge([
            'from_port' => $fromPort,
            'to_port' => $toPort,
            'started_at' => now(),
            'status' => 'active',
        ], $extra));
    }

    // ── Accessors ───────────────────────────────────────────────────────

    public function getDurationAttribute(): ?float
    {
        if (!$this->started_at) return null;
        $end = $this->ended_at ?? now();
        return $this->started_at->diffInSeconds($end);
    }

    public function getDistanceAttribute(): float
    {
        $points = $this->trackPoints()->select(['latitude', 'longitude'])->get();
        if ($points->count() < 2) return 0;

        $total = 0;
        for ($i = 1; $i < $points->count(); $i++) {
            $total += static::haversineNm(
                $points[$i - 1]->latitude, $points[$i - 1]->longitude,
                $points[$i]->latitude, $points[$i]->longitude,
            );
        }
        return round($total, 1);
    }

    // ── Slug generation ─────────────────────────────────────────────────

    protected static function generateUniqueSlug(string $from, string $to): string
    {
        $base = Str::slug($from . ' to ' . $to);
        $slug = $base;
        $counter = 1;

        while (static::where('slug', $slug)->exists()) {
            $counter++;
            $slug = $base . '-' . $counter;
        }

        return $slug;
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    protected static function haversineNm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $r = 3440.065; // Earth radius in nautical miles
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
```

Write to `app/Models/Journey.php`.

- [ ] **Step 4: Create the JourneyTrackPoint model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JourneyTrackPoint extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'journey_id', 'recorded_at', 'latitude', 'longitude',
        'speed_sog', 'speed_stw', 'heading', 'cog', 'depth',
        'wind_speed_apparent', 'wind_angle_apparent',
        'wind_speed_true', 'wind_direction_true',
        'house_battery_voltage', 'house_battery_current', 'heel',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function journey(): BelongsTo
    {
        return $this->belongsTo(Journey::class);
    }
}
```

Write to `app/Models/JourneyTrackPoint.php`.

- [ ] **Step 5: Create the Journey factory**

```php
<?php

namespace Database\Factories;

use App\Models\Journey;
use Illuminate\Database\Eloquent\Factories\Factory;

class JourneyFactory extends Factory
{
    protected $model = Journey::class;

    public function definition(): array
    {
        return [
            'from_port' => fake()->city(),
            'to_port' => fake()->city(),
            'started_at' => now()->subHours(3),
            'status' => 'completed',
            'ended_at' => now(),
            'is_public' => true,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => 'active',
            'ended_at' => null,
        ]);
    }

    public function abandoned(): static
    {
        return $this->state(fn () => [
            'status' => 'abandoned',
        ]);
    }
}
```

Write to `database/factories/JourneyFactory.php`.

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test tests/Feature/JourneyModelTest.php`
Expected: All 6 tests pass.

- [ ] **Step 7: Commit**

```bash
git add app/Models/Journey.php app/Models/JourneyTrackPoint.php database/factories/JourneyFactory.php tests/Feature/JourneyModelTest.php
git commit -m "feat: add Journey and JourneyTrackPoint models with factory and tests"
```

---

### Task 3: GPX Parsing Service

**Files:**
- Create: `app/Services/GpxService.php`
- Test: `tests/Feature/GpxServiceTest.php`
- Create: `tests/fixtures/test-route.gpx` (test fixture)

- [ ] **Step 1: Create the test fixture GPX file**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<gpx version="1.1" creator="test">
  <rte>
    <name>Test Route</name>
    <rtept lat="50.7572" lon="-1.5435">
      <name>Lymington</name>
    </rtept>
    <rtept lat="50.7105" lon="-1.4990">
      <name>Hurst Point</name>
    </rtept>
    <rtept lat="50.7076" lon="-1.4993">
    </rtept>
    <rtept lat="50.7113" lon="-1.5005">
      <name>Yarmouth</name>
    </rtept>
  </rte>
</gpx>
```

Write to `tests/fixtures/test-route.gpx`.

- [ ] **Step 2: Write the GPX service test**

```php
<?php

namespace Tests\Feature;

use App\Services\GpxService;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class GpxServiceTest extends TestCase
{
    public function test_parses_route_waypoints_from_gpx(): void
    {
        $service = new GpxService();
        $path = base_path('tests/fixtures/test-route.gpx');
        $waypoints = $service->parseWaypoints(file_get_contents($path));

        $this->assertCount(4, $waypoints);
        $this->assertEquals('Lymington', $waypoints[0]['name']);
        $this->assertEqualsWithDelta(50.7572, $waypoints[0]['lat'], 0.0001);
        $this->assertEqualsWithDelta(-1.5435, $waypoints[0]['lng'], 0.0001);
        $this->assertNull($waypoints[2]['name']);
        $this->assertEquals('Yarmouth', $waypoints[3]['name']);
    }

    public function test_returns_empty_array_for_gpx_without_route(): void
    {
        $service = new GpxService();
        $xml = '<?xml version="1.0"?><gpx version="1.1"><trk><trkseg><trkpt lat="50.0" lon="-1.0"/></trkseg></trk></gpx>';
        $waypoints = $service->parseWaypoints($xml);

        $this->assertEmpty($waypoints);
    }

    public function test_returns_empty_array_for_invalid_xml(): void
    {
        $service = new GpxService();
        $waypoints = $service->parseWaypoints('not xml at all');

        $this->assertEmpty($waypoints);
    }
}
```

Write to `tests/Feature/GpxServiceTest.php`.

- [ ] **Step 3: Run tests to verify they fail**

Run: `php artisan test tests/Feature/GpxServiceTest.php`
Expected: All tests fail (GpxService class not found).

- [ ] **Step 4: Implement the GpxService**

```php
<?php

namespace App\Services;

class GpxService
{
    public function parseWaypoints(string $gpxContent): array
    {
        $previousErrorReporting = libxml_use_internal_errors(true);

        try {
            $xml = simplexml_load_string($gpxContent);
            if ($xml === false) {
                return [];
            }

            $waypoints = [];

            // Handle namespace-prefixed GPX or plain GPX
            $xml->registerXPathNamespace('gpx', 'http://www.topografix.com/GPX/1/1');
            $routes = $xml->xpath('//gpx:rte/gpx:rtept') ?: $xml->xpath('//rte/rtept');

            if (!$routes) {
                return [];
            }

            foreach ($routes as $point) {
                $lat = (float) $point['lat'];
                $lng = (float) $point['lon'];
                $name = isset($point->name) && (string) $point->name !== '' ? (string) $point->name : null;

                $waypoints[] = [
                    'lat' => $lat,
                    'lng' => $lng,
                    'name' => $name,
                ];
            }

            return $waypoints;
        } finally {
            libxml_use_internal_errors($previousErrorReporting);
        }
    }

    public function storeAndParse(\Illuminate\Http\UploadedFile $file, int $journeyId): array
    {
        $path = $file->storeAs('journeys/gpx', $journeyId . '.gpx');
        $content = file_get_contents($file->getRealPath());
        $waypoints = $this->parseWaypoints($content);

        return [
            'path' => $path,
            'waypoints' => $waypoints,
        ];
    }
}
```

Write to `app/Services/GpxService.php`.

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test tests/Feature/GpxServiceTest.php`
Expected: All 3 tests pass.

- [ ] **Step 6: Commit**

```bash
git add app/Services/GpxService.php tests/Feature/GpxServiceTest.php tests/fixtures/test-route.gpx
git commit -m "feat: add GPX parsing service with waypoint extraction"
```

---

### Task 4: Journey Service

**Files:**
- Create: `app/Services/JourneyService.php`
- Test: `tests/Feature/JourneyServiceTest.php`

- [ ] **Step 1: Write the JourneyService test**

```php
<?php

namespace Tests\Feature;

use App\Models\Journey;
use App\Models\JourneyTrackPoint;
use App\Services\JourneyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class JourneyServiceTest extends TestCase
{
    use RefreshDatabase;

    private JourneyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(JourneyService::class);
    }

    public function test_record_track_point_writes_to_database(): void
    {
        $journey = Journey::factory()->active()->create();

        $metrics = [
            'boat' => [
                'speed_sog' => 4.5, 'speed_stw' => 4.2, 'heading' => 180.0, 'cog' => 175.0,
                'depth' => 8.3, 'wind_speed_apparent' => 12.0, 'wind_angle_apparent' => 45.0,
                'wind_speed_true' => 10.0, 'wind_direction_true' => 220.0,
                'house_battery_voltage' => 12.8, 'house_battery_current' => -3.5, 'heel' => 12.0,
            ],
            'gps' => ['latitude' => 50.75, 'longitude' => -1.54],
        ];

        $this->service->recordTrackPoint($journey, $metrics);

        $this->assertDatabaseCount('journey_track_points', 1);
        $point = JourneyTrackPoint::first();
        $this->assertEqualsWithDelta(50.75, $point->latitude, 0.001);
        $this->assertEquals(4.5, $point->speed_sog);
    }

    public function test_auto_stop_after_stationary_threshold(): void
    {
        $journey = Journey::factory()->active()->create();

        // Simulate 480 stationary pushes
        Cache::put('journey.stationary_count', 479);

        $metrics = [
            'boat' => ['speed_sog' => 0.1],
            'gps' => ['latitude' => 50.75, 'longitude' => -1.54],
        ];

        // Add a track point with speed so ended_at can be backdated
        $journey->trackPoints()->create([
            'recorded_at' => now()->subHours(3),
            'latitude' => 50.75,
            'longitude' => -1.54,
            'speed_sog' => 4.0,
        ]);

        $this->service->recordTrackPoint($journey, $metrics);

        $journey->refresh();
        $this->assertEquals('completed', $journey->status);
        $this->assertNotNull($journey->ended_at);
    }

    public function test_stationary_counter_resets_when_moving(): void
    {
        $journey = Journey::factory()->active()->create();
        Cache::put('journey.stationary_count', 100);

        $metrics = [
            'boat' => ['speed_sog' => 3.5],
            'gps' => ['latitude' => 50.75, 'longitude' => -1.54],
        ];

        $this->service->recordTrackPoint($journey, $metrics);

        $this->assertEquals(0, Cache::get('journey.stationary_count'));
    }

    public function test_end_journey(): void
    {
        $journey = Journey::factory()->active()->create();

        $this->service->endJourney($journey);

        $journey->refresh();
        $this->assertEquals('completed', $journey->status);
        $this->assertNotNull($journey->ended_at);
    }
}
```

Write to `tests/Feature/JourneyServiceTest.php`.

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/JourneyServiceTest.php`
Expected: All tests fail (JourneyService class not found).

- [ ] **Step 3: Implement the JourneyService**

```php
<?php

namespace App\Services;

use App\Models\Journey;
use App\Models\JourneyTrackPoint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class JourneyService
{
    const STATIONARY_THRESHOLD = 480; // 2 hours at 15s intervals
    const STATIONARY_SPEED = 0.5; // knots

    public function recordTrackPoint(Journey $journey, array $metrics): void
    {
        $boat = $metrics['boat'] ?? [];
        $gps = $metrics['gps'] ?? [];

        $lat = $gps['latitude'] ?? null;
        $lng = $gps['longitude'] ?? null;

        if ($lat === null || $lng === null || ($lat == 0 && $lng == 0)) {
            return;
        }

        $journey->trackPoints()->create([
            'recorded_at' => now(),
            'latitude' => $lat,
            'longitude' => $lng,
            'speed_sog' => $boat['speed_sog'] ?? null,
            'speed_stw' => $boat['speed_stw'] ?? null,
            'heading' => $boat['heading'] ?? null,
            'cog' => $boat['cog'] ?? null,
            'depth' => $boat['depth'] ?? null,
            'wind_speed_apparent' => $boat['wind_speed_apparent'] ?? null,
            'wind_angle_apparent' => $boat['wind_angle_apparent'] ?? null,
            'wind_speed_true' => $boat['wind_speed_true'] ?? null,
            'wind_direction_true' => $boat['wind_direction_true'] ?? null,
            'house_battery_voltage' => $boat['house_battery_voltage'] ?? null,
            'house_battery_current' => $boat['house_battery_current'] ?? null,
            'heel' => $boat['heel'] ?? null,
        ]);

        $speed = $boat['speed_sog'] ?? 0;

        if ($speed < self::STATIONARY_SPEED) {
            $count = Cache::increment('journey.stationary_count');
            if ($count >= self::STATIONARY_THRESHOLD) {
                $this->autoEndJourney($journey);
            }
        } else {
            Cache::put('journey.stationary_count', 0);
        }
    }

    public function endJourney(Journey $journey): void
    {
        $journey->update([
            'status' => 'completed',
            'ended_at' => now(),
        ]);
        Cache::forget('journey.stationary_count');
    }

    public function abandonJourney(Journey $journey): void
    {
        $journey->update([
            'status' => 'abandoned',
            'ended_at' => now(),
        ]);
        Cache::forget('journey.stationary_count');
    }

    protected function autoEndJourney(Journey $journey): void
    {
        $lastMoving = $journey->trackPoints()
            ->where('speed_sog', '>=', self::STATIONARY_SPEED)
            ->orderByDesc('recorded_at')
            ->first();

        $endedAt = $lastMoving?->recorded_at ?? now();

        $journey->update([
            'status' => 'completed',
            'ended_at' => $endedAt,
        ]);

        if ($lastMoving) {
            $journey->trackPoints()
                ->where('recorded_at', '>', $endedAt)
                ->delete();
        }

        Cache::forget('journey.stationary_count');
        Log::info("Journey #{$journey->id} auto-ended after 2 hours stationary");
    }
}
```

Write to `app/Services/JourneyService.php`.

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Feature/JourneyServiceTest.php`
Expected: All 4 tests pass.

- [ ] **Step 5: Commit**

```bash
git add app/Services/JourneyService.php tests/Feature/JourneyServiceTest.php
git commit -m "feat: add JourneyService with track recording and auto-stop"
```

---

### Task 5: Settings Migration + MetricsPushCommand Update

**Files:**
- Modify: `app/Services/MetricsService.php` (update `getSettings()`)
- Modify: `app/Console/Commands/MetricsPushCommand.php` (add track recording)
- Modify: `app/Http/Controllers/DashboardController.php` (add routeWaypoints prop)
- Modify: `app/Http/Controllers/OverlayController.php` (add routeWaypoints prop)

- [ ] **Step 1: Update MetricsService::getSettings() to read from active journey**

In `app/Services/MetricsService.php`, replace the `getSettings()` method:

```php
public function getSettings(): array
{
    $journey = \App\Models\Journey::current();

    return [
        'boat_name' => \App\Models\BoatSetting::getValue('boat_name', config('scarlet.name')),
        'passage_from' => $journey?->from_port ?? '',
        'passage_to' => $journey?->to_port ?? '',
        'port_name' => \App\Models\BoatSetting::getValue('port_name', ''),
    ];
}
```

- [ ] **Step 2: Update MetricsPushCommand to record track points**

In `app/Console/Commands/MetricsPushCommand.php`, modify the `handle` method to inject `JourneyService` and record track points after broadcasting. Replace the full `handle` method:

```php
public function handle(MetricsService $metricsService, \App\Services\JourneyService $journeyService): int
{
    $interval = config('scarlet.metrics.push_interval');
    $this->info("Starting metrics push loop (every {$interval}s)");

    while (true) {
        try {
            $all = $metricsService->getAllMetrics();
            MetricsUpdated::dispatch(
                $all['boat'],
                $all['tracker'],
                $all['gps'],
                $all['weather'],
                $all['settings'],
                $all['timestamp'],
            );
            $this->line('Pushed metrics at ' . $all['timestamp']);

            $journey = \App\Models\Journey::current();
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

- [ ] **Step 3: Update DashboardController to pass routeWaypoints**

In `app/Http/Controllers/DashboardController.php`, add the active journey's route waypoints to the Inertia props. Replace the `index` method:

```php
public function index(MetricsService $metrics)
{
    $journey = \App\Models\Journey::current();

    return Inertia::render('Public/Dashboard', [
        'initialMetrics' => $metrics->getAllMetrics(),
        'gpsTrack' => $metrics->getGpsTrack(),
        'boatName' => BoatSetting::getValue('boat_name', config('scarlet.name')),
        'passageFrom' => $journey?->from_port ?? '',
        'passageTo' => $journey?->to_port ?? '',
        'portName' => BoatSetting::getValue('port_name', ''),
        'tileUrl' => '/openseamap/{z}/{x}/{y}',
        'reverb' => config('scarlet.reverb'),
        'reverbKey' => config('broadcasting.connections.reverb.key'),
        'tripOffset' => (float) BoatSetting::getValue('trip_offset', 0),
        'routeWaypoints' => $journey?->route_waypoints ?? [],
    ]);
}
```

- [ ] **Step 4: Update OverlayController to pass routeWaypoints**

In `app/Http/Controllers/OverlayController.php`, same change. Replace the `index` method:

```php
public function index(MetricsService $metrics)
{
    Inertia::setRootView('overlay-app');

    $journey = \App\Models\Journey::current();

    return Inertia::render('Public/Overlay', [
        'initialMetrics' => $metrics->getAllMetrics(),
        'gpsTrack' => $metrics->getGpsTrack(),
        'boatName' => BoatSetting::getValue('boat_name', config('scarlet.name')),
        'passageFrom' => $journey?->from_port ?? '',
        'passageTo' => $journey?->to_port ?? '',
        'portName' => BoatSetting::getValue('port_name', ''),
        'routeWaypoints' => $journey?->route_waypoints ?? [],
    ]);
}
```

- [ ] **Step 5: Run existing tests to verify nothing is broken**

Run: `php artisan test`
Expected: All existing tests pass.

- [ ] **Step 6: Commit**

```bash
git add app/Services/MetricsService.php app/Console/Commands/MetricsPushCommand.php app/Http/Controllers/DashboardController.php app/Http/Controllers/OverlayController.php
git commit -m "feat: integrate journey with metrics push and settings"
```

---

### Task 6: Import Job

**Files:**
- Create: `app/Jobs/ImportJourneyFromPrometheus.php`
- Test: `tests/Feature/ImportJourneyJobTest.php`

- [ ] **Step 1: Write the import job test**

```php
<?php

namespace Tests\Feature;

use App\Jobs\ImportJourneyFromPrometheus;
use App\Models\Journey;
use App\Services\PrometheusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ImportJourneyJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_creates_track_points_from_prometheus(): void
    {
        $journey = Journey::create([
            'from_port' => 'Lymington',
            'to_port' => 'Yarmouth',
            'started_at' => now()->subHours(3),
            'ended_at' => now(),
            'status' => 'completed',
        ]);

        $timestamps = [
            ['timestamp' => now()->subHours(3)->timestamp, 'value' => 50.75],
            ['timestamp' => now()->subHours(2)->timestamp, 'value' => 50.71],
        ];

        $mock = Mockery::mock(PrometheusService::class);
        $mock->shouldReceive('queryRange')->andReturn($timestamps);
        $this->app->instance(PrometheusService::class, $mock);

        $job = new ImportJourneyFromPrometheus(
            $journey->id,
            now()->subHours(3)->toIso8601String(),
            now()->toIso8601String(),
        );
        $job->handle(app(PrometheusService::class));

        $this->assertGreaterThan(0, $journey->trackPoints()->count());
    }
}
```

Write to `tests/Feature/ImportJourneyJobTest.php`.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/ImportJourneyJobTest.php`
Expected: FAIL (ImportJourneyFromPrometheus not found).

- [ ] **Step 3: Implement the import job**

```php
<?php

namespace App\Jobs;

use App\Models\Journey;
use App\Models\JourneyTrackPoint;
use App\Services\PrometheusService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ImportJourneyFromPrometheus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $journeyId,
        public string $startTime,
        public string $endTime,
    ) {}

    public function handle(PrometheusService $prometheus): void
    {
        $journey = Journey::findOrFail($this->journeyId);
        $duration = $this->computeDuration();
        $step = '30s';

        $metrics = [
            'latitude' => 'scarlet_signalk_navigation_position_latitude',
            'longitude' => 'scarlet_signalk_navigation_position_longitude',
            'speed_sog' => 'scarlet_signalk_navigation_speedOverGround * 1.94384',
            'speed_stw' => 'scarlet_signalk_navigation_speedThroughWater * 1.94384',
            'heading' => 'scarlet_signalk_navigation_headingMagnetic * 180 / 3.14159265359',
            'cog' => 'scarlet_signalk_navigation_courseOverGroundTrue * 180 / 3.14159265359',
            'depth' => 'scarlet_signalk_environment_depth_belowSurface',
            'wind_speed_apparent' => 'scarlet_signalk_environment_wind_speedApparent * 1.94384',
            'wind_angle_apparent' => 'scarlet_signalk_environment_wind_angleApparent * 180 / 3.14159265359',
            'wind_speed_true' => 'scarlet_boat_wind_speed_kn',
            'wind_direction_true' => 'scarlet_boat_wind_direction_deg',
            'house_battery_voltage' => 'scarlet_signalk_electrical_batteries_0_voltage',
            'house_battery_current' => 'scarlet_signalk_electrical_batteries_0_current',
            'heel' => 'scarlet_signalk_navigation_attitude_roll * 180 / 3.14159265359',
        ];

        $data = [];
        foreach ($metrics as $key => $query) {
            $results = $prometheus->queryRange($query, $duration, $step);
            foreach ($results as $point) {
                $ts = $point['timestamp'];
                if (!isset($data[$ts])) {
                    $data[$ts] = ['recorded_at' => date('Y-m-d H:i:s', $ts)];
                }
                $data[$ts][$key] = $point['value'];
            }
        }

        ksort($data);

        $batch = [];
        foreach ($data as $row) {
            if (!isset($row['latitude']) || !isset($row['longitude'])) {
                continue;
            }

            $batch[] = array_merge(['journey_id' => $journey->id], $row);

            if (count($batch) >= 500) {
                JourneyTrackPoint::insert($batch);
                $batch = [];
            }
        }

        if (!empty($batch)) {
            JourneyTrackPoint::insert($batch);
        }

        $pointCount = $journey->trackPoints()->count();
        Log::info("Imported {$pointCount} track points for journey #{$journey->id}");
    }

    protected function computeDuration(): string
    {
        $start = \Carbon\Carbon::parse($this->startTime);
        $end = \Carbon\Carbon::parse($this->endTime);
        $seconds = $end->diffInSeconds($start);
        return $seconds . 's';
    }
}
```

Write to `app/Jobs/ImportJourneyFromPrometheus.php`.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/ImportJourneyJobTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Jobs/ImportJourneyFromPrometheus.php tests/Feature/ImportJourneyJobTest.php
git commit -m "feat: add ImportJourneyFromPrometheus queued job"
```

---

### Task 7: Admin Journey Controller + Routes

**Files:**
- Create: `app/Http/Controllers/Admin/JourneyController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/JourneyControllerTest.php`

- [ ] **Step 1: Write the controller tests**

```php
<?php

namespace Tests\Feature;

use App\Models\Journey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class JourneyControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->owner()->create();
    }

    public function test_journey_list_page_renders(): void
    {
        $response = $this->actingAs($this->user)->get('/admin/journeys');
        $response->assertStatus(200);
    }

    public function test_can_start_journey(): void
    {
        $response = $this->actingAs($this->user)->post('/admin/journeys', [
            'from_port' => 'Lymington',
            'to_port' => 'Yarmouth',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('journeys', [
            'from_port' => 'Lymington',
            'to_port' => 'Yarmouth',
            'status' => 'active',
        ]);
    }

    public function test_can_start_journey_with_gpx(): void
    {
        Storage::fake();

        $gpx = UploadedFile::fake()->createWithContent(
            'route.gpx',
            '<?xml version="1.0"?><gpx version="1.1"><rte><rtept lat="50.75" lon="-1.54"><name>Start</name></rtept></rte></gpx>'
        );

        $response = $this->actingAs($this->user)->post('/admin/journeys', [
            'from_port' => 'Lymington',
            'to_port' => 'Yarmouth',
            'gpx_file' => $gpx,
        ]);

        $response->assertRedirect();
        $journey = Journey::first();
        $this->assertNotNull($journey->route_waypoints);
        $this->assertCount(1, $journey->route_waypoints);
    }

    public function test_cannot_start_second_active_journey(): void
    {
        Journey::factory()->active()->create();

        $response = $this->actingAs($this->user)->post('/admin/journeys', [
            'from_port' => 'Cowes',
            'to_port' => 'Portsmouth',
        ]);

        $response->assertSessionHasErrors('status');
    }

    public function test_can_end_journey(): void
    {
        $journey = Journey::factory()->active()->create();

        $response = $this->actingAs($this->user)->post("/admin/journeys/{$journey->id}/end");
        $response->assertRedirect();

        $journey->refresh();
        $this->assertEquals('completed', $journey->status);
    }

    public function test_can_update_journey(): void
    {
        $journey = Journey::factory()->create();

        $response = $this->actingAs($this->user)->put("/admin/journeys/{$journey->id}", [
            'title' => 'Updated Title',
            'slug' => 'custom-slug',
            'from_port' => $journey->from_port,
            'to_port' => $journey->to_port,
            'is_public' => false,
            'notes' => 'Some notes',
        ]);

        $response->assertRedirect();
        $journey->refresh();
        $this->assertEquals('Updated Title', $journey->title);
        $this->assertEquals('custom-slug', $journey->slug);
        $this->assertFalse($journey->is_public);
    }

    public function test_can_delete_journey(): void
    {
        $journey = Journey::factory()->create();

        $response = $this->actingAs($this->user)->delete("/admin/journeys/{$journey->id}");
        $response->assertRedirect();

        $this->assertDatabaseMissing('journeys', ['id' => $journey->id]);
    }
}
```

Write to `tests/Feature/JourneyControllerTest.php`.

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/JourneyControllerTest.php`
Expected: All tests fail (404s — routes don't exist yet).

- [ ] **Step 3: Create the JourneyController**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ImportJourneyFromPrometheus;
use App\Models\Journey;
use App\Services\GpxService;
use App\Services\JourneyService;
use App\Services\MetricsService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class JourneyController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Journeys', [
            'journeys' => Journey::orderByDesc('started_at')->get()->map(fn (Journey $j) => [
                'id' => $j->id,
                'slug' => $j->slug,
                'title' => $j->title,
                'from_port' => $j->from_port,
                'to_port' => $j->to_port,
                'started_at' => $j->started_at->toIso8601String(),
                'ended_at' => $j->ended_at?->toIso8601String(),
                'status' => $j->status,
                'is_public' => $j->is_public,
                'distance' => $j->distance,
                'duration' => $j->duration,
            ]),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/JourneyCreate', [
            'hasActive' => Journey::active()->exists(),
        ]);
    }

    public function store(Request $request, GpxService $gpxService)
    {
        $validated = $request->validate([
            'from_port' => ['required', 'string', 'max:100'],
            'to_port' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'gpx_file' => ['nullable', 'file', 'max:10240'],
        ]);

        try {
            $journey = Journey::startNew($validated['from_port'], $validated['to_port'], [
                'notes' => $validated['notes'] ?? null,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        if ($request->hasFile('gpx_file')) {
            $result = $gpxService->storeAndParse($request->file('gpx_file'), $journey->id);
            $journey->update([
                'gpx_route_path' => $result['path'],
                'route_waypoints' => $result['waypoints'],
            ]);
        }

        return redirect()->route('admin.journeys')->with('success', 'Journey started.');
    }

    public function importForm()
    {
        return Inertia::render('Admin/JourneyImport');
    }

    public function import(Request $request, GpxService $gpxService)
    {
        $validated = $request->validate([
            'from_port' => ['required', 'string', 'max:100'],
            'to_port' => ['required', 'string', 'max:100'],
            'started_at' => ['required', 'date'],
            'ended_at' => ['required', 'date', 'after:started_at'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'gpx_file' => ['nullable', 'file', 'max:10240'],
        ]);

        $journey = Journey::create([
            'from_port' => $validated['from_port'],
            'to_port' => $validated['to_port'],
            'started_at' => $validated['started_at'],
            'ended_at' => $validated['ended_at'],
            'status' => 'completed',
            'notes' => $validated['notes'] ?? null,
        ]);

        if ($request->hasFile('gpx_file')) {
            $result = $gpxService->storeAndParse($request->file('gpx_file'), $journey->id);
            $journey->update([
                'gpx_route_path' => $result['path'],
                'route_waypoints' => $result['waypoints'],
            ]);
        }

        ImportJourneyFromPrometheus::dispatch(
            $journey->id,
            $validated['started_at'],
            $validated['ended_at'],
        );

        return redirect()->route('admin.journeys')->with('success', 'Import started. Track data will appear shortly.');
    }

    public function edit(Journey $journey)
    {
        return Inertia::render('Admin/JourneyEdit', [
            'journey' => $journey->only('id', 'slug', 'title', 'from_port', 'to_port', 'is_public', 'notes', 'status', 'gpx_route_path'),
        ]);
    }

    public function update(Request $request, Journey $journey)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['required', 'string', 'max:200', 'unique:journeys,slug,' . $journey->id],
            'from_port' => ['required', 'string', 'max:100'],
            'to_port' => ['required', 'string', 'max:100'],
            'is_public' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $journey->update($validated);

        return redirect()->route('admin.journeys')->with('success', 'Journey updated.');
    }

    public function destroy(Journey $journey)
    {
        $journey->delete();

        return redirect()->route('admin.journeys')->with('success', 'Journey deleted.');
    }

    public function end(Journey $journey, JourneyService $journeyService)
    {
        $journeyService->endJourney($journey);

        return redirect()->route('admin.journeys')->with('success', 'Journey ended.');
    }

    public function uploadGpx(Request $request, Journey $journey, GpxService $gpxService)
    {
        $request->validate([
            'gpx_file' => ['required', 'file', 'max:10240'],
        ]);

        $result = $gpxService->storeAndParse($request->file('gpx_file'), $journey->id);
        $journey->update([
            'gpx_route_path' => $result['path'],
            'route_waypoints' => $result['waypoints'],
        ]);

        return back()->with('success', 'GPX route uploaded.');
    }
}
```

Write to `app/Http/Controllers/Admin/JourneyController.php`.

- [ ] **Step 4: Add routes to routes/web.php**

In `routes/web.php`, add the admin journey routes inside the existing `Route::middleware('auth')->prefix('admin')->group(...)` block, and add the import for JourneyController at the top of the file alongside the other admin controller imports. Add these routes after the existing admin routes:

```php
// Journey management
Route::get('/journeys', [JourneyController::class, 'index'])->name('admin.journeys');
Route::get('/journeys/create', [JourneyController::class, 'create'])->name('admin.journeys.create');
Route::get('/journeys/import', [JourneyController::class, 'importForm'])->name('admin.journeys.import');
Route::post('/journeys', [JourneyController::class, 'store'])->name('admin.journeys.store');
Route::post('/journeys/import', [JourneyController::class, 'import'])->name('admin.journeys.import.store');
Route::get('/journeys/{journey}/edit', [JourneyController::class, 'edit'])->name('admin.journeys.edit');
Route::put('/journeys/{journey}', [JourneyController::class, 'update'])->name('admin.journeys.update');
Route::delete('/journeys/{journey}', [JourneyController::class, 'destroy'])->name('admin.journeys.destroy');
Route::post('/journeys/{journey}/end', [JourneyController::class, 'end'])->name('admin.journeys.end');
Route::post('/journeys/{journey}/gpx', [JourneyController::class, 'uploadGpx'])->name('admin.journeys.gpx');
```

Also add `use App\Http\Controllers\Admin\JourneyController;` to the top use statements.

- [ ] **Step 5: Create stub Vue pages so routes don't 500**

Create minimal stub files for the Inertia pages referenced by the controller. These will be fleshed out in later tasks.

`resources/js/Pages/Admin/Journeys.vue`:
```vue
<template><AdminLayout><Head title="Journeys" /><h1 class="text-[22px] font-bold mb-6">Journeys</h1><p>Coming soon.</p></AdminLayout></template>
<script setup>
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
defineProps({ journeys: Array });
</script>
```

`resources/js/Pages/Admin/JourneyCreate.vue`:
```vue
<template><AdminLayout><Head title="Start Journey" /><h1 class="text-[22px] font-bold mb-6">Start Journey</h1></AdminLayout></template>
<script setup>
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
defineProps({ hasActive: Boolean });
</script>
```

`resources/js/Pages/Admin/JourneyImport.vue`:
```vue
<template><AdminLayout><Head title="Import Journey" /><h1 class="text-[22px] font-bold mb-6">Import from History</h1></AdminLayout></template>
<script setup>
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
</script>
```

`resources/js/Pages/Admin/JourneyEdit.vue`:
```vue
<template><AdminLayout><Head title="Edit Journey" /><h1 class="text-[22px] font-bold mb-6">Edit Journey</h1></AdminLayout></template>
<script setup>
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
defineProps({ journey: Object });
</script>
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test tests/Feature/JourneyControllerTest.php`
Expected: All 7 tests pass.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Admin/JourneyController.php routes/web.php tests/Feature/JourneyControllerTest.php resources/js/Pages/Admin/Journeys.vue resources/js/Pages/Admin/JourneyCreate.vue resources/js/Pages/Admin/JourneyImport.vue resources/js/Pages/Admin/JourneyEdit.vue
git commit -m "feat: add admin journey controller, routes, and stub pages"
```

---

### Task 8: Admin Navigation + Dashboard Page

**Files:**
- Modify: `resources/js/Layouts/AdminLayout.vue` (add Dashboard + Journeys nav items)
- Modify: `resources/js/Layouts/NavLink.vue` (add new icons)
- Create: `app/Http/Controllers/Admin/AdminDashboardController.php`
- Create: `resources/js/Pages/Admin/Dashboard.vue`
- Modify: `routes/web.php` (add `/admin` route)

- [ ] **Step 1: Add new icons to NavLink.vue**

In `resources/js/Layouts/NavLink.vue`, add two new icon templates inside the `<svg>` after the existing `v-else-if="icon === 'radio'"` template:

```vue
<template v-else-if="icon === 'home'"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></template>
<template v-else-if="icon === 'compass'"><circle cx="12" cy="12" r="10"/><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/></template>
```

- [ ] **Step 2: Update AdminLayout.vue sidebar navigation**

In `resources/js/Layouts/AdminLayout.vue`, replace the `<nav>` content to add Dashboard and Journeys items:

```vue
<nav class="px-2.5 py-3 flex-1 flex flex-col gap-0.5">
    <div class="nav-label first:pt-1">Boat</div>
    <NavLink href="/admin" icon="home" :active="currentPage === 'Admin/Dashboard'">Dashboard</NavLink>
    <NavLink href="/admin/settings" icon="settings" :active="currentPage === 'Admin/Settings'">Settings</NavLink>
    <NavLink href="/admin/journeys" icon="compass" :active="currentPage?.startsWith('Admin/Journey')">Journeys</NavLink>
    <NavLink href="/admin/tracker" icon="activity" :active="currentPage === 'Admin/Tracker'">Tracker</NavLink>
    <NavLink href="/admin/metrics" icon="chart" :active="currentPage === 'Admin/BoatMetrics'">Boat Metrics</NavLink>
    <NavLink href="/admin/stream" icon="radio" :active="currentPage === 'Admin/StreamMonitor'">Stream Monitor</NavLink>

    <div class="nav-label">Links</div>
    <a href="/overlay" target="_blank" class="nav-link">
        <svg class="nav-icon" viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
        Broadcast Overlay
    </a>
    <a href="/dashboard" target="_blank" class="nav-link">
        <svg class="nav-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/></svg>
        Public Dashboard
    </a>

    <div class="nav-label">Account</div>
    <NavLink href="/admin/profile" icon="user" :active="currentPage === 'Admin/Profile'">Profile</NavLink>
    <NavLink href="/admin/team" icon="users" :active="currentPage === 'Admin/Team'">Team</NavLink>
</nav>
```

- [ ] **Step 3: Create AdminDashboardController**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Journey;
use App\Services\MetricsService;
use Inertia\Inertia;

class AdminDashboardController extends Controller
{
    public function index(MetricsService $metrics)
    {
        $activeJourney = Journey::current();
        $recentJourneys = Journey::completed()
            ->orderByDesc('ended_at')
            ->take(5)
            ->get()
            ->map(fn (Journey $j) => [
                'id' => $j->id,
                'slug' => $j->slug,
                'title' => $j->title,
                'started_at' => $j->started_at->toIso8601String(),
                'ended_at' => $j->ended_at?->toIso8601String(),
                'distance' => $j->distance,
                'duration' => $j->duration,
            ]);

        return Inertia::render('Admin/Dashboard', [
            'boat' => $metrics->getBoatMetrics(),
            'tracker' => $metrics->getTrackerMetrics(),
            'activeJourney' => $activeJourney ? [
                'id' => $activeJourney->id,
                'title' => $activeJourney->title,
                'from_port' => $activeJourney->from_port,
                'to_port' => $activeJourney->to_port,
                'started_at' => $activeJourney->started_at->toIso8601String(),
                'distance' => $activeJourney->distance,
                'duration' => $activeJourney->duration,
            ] : null,
            'recentJourneys' => $recentJourneys,
        ]);
    }
}
```

Write to `app/Http/Controllers/Admin/AdminDashboardController.php`.

- [ ] **Step 4: Create the Admin Dashboard Vue page**

```vue
<template>
    <AdminLayout>
        <Head title="Dashboard" />
        <h1 class="text-[22px] font-bold mb-6">Dashboard</h1>

        <!-- Active Journey / Start Journey -->
        <div class="panel mb-4">
            <div class="panel-title mb-3">Journey</div>
            <template v-if="activeJourney">
                <div class="flex items-baseline justify-between mb-2">
                    <div>
                        <span class="text-[16px] font-semibold">{{ activeJourney.title }}</span>
                        <span class="ml-2 text-[10px] font-semibold tracking-wide px-2 py-0.5 rounded bg-green/10 text-green">ACTIVE</span>
                    </div>
                </div>
                <div class="space-y-2 text-[13px] mb-4">
                    <div class="data-row"><span>Elapsed</span><span>{{ fmtDuration(activeJourney.duration) }}</span></div>
                    <div class="data-row"><span>Distance</span><span>{{ activeJourney.distance }} nm</span></div>
                    <div class="data-row"><span>Speed</span><span class="text-scarlet">{{ fmt(boat?.speed_sog) }} kn</span></div>
                </div>
                <div class="flex gap-2">
                    <Link :href="`/admin/journeys/${activeJourney.id}/end`" method="post" as="button" class="btn btn--danger">End Journey</Link>
                    <Link href="/admin/journeys" class="btn btn--ghost">All Journeys</Link>
                </div>
            </template>
            <template v-else>
                <p class="text-[13px] text-text-secondary mb-3">No active journey.</p>
                <div class="flex gap-2">
                    <Link href="/admin/journeys/create" class="btn btn--primary">Start Journey</Link>
                    <Link href="/admin/journeys/import" class="btn btn--ghost">Import from History</Link>
                </div>
            </template>
        </div>

        <!-- Boat Status -->
        <div class="panel mb-4">
            <div class="flex items-baseline justify-between mb-3">
                <span class="panel-title">Boat Status</span>
                <Link href="/admin/metrics" class="text-[12px] text-scarlet font-medium hover:underline">View Metrics →</Link>
            </div>
            <div class="strip">
                <div class="strip-cell"><div class="strip-label">SOG</div><div class="strip-value text-scarlet">{{ fmt(boat?.speed_sog) }}</div><div class="strip-unit">kn</div></div>
                <div class="strip-cell"><div class="strip-label">Heading</div><div class="strip-value">{{ fmt(boat?.heading, 0) }}</div><div class="strip-unit">°</div></div>
                <div class="strip-cell"><div class="strip-label">Depth</div><div class="strip-value" style="color: oklch(0.55 0.15 240)">{{ fmt(boat?.depth) }}</div><div class="strip-unit">m</div></div>
                <div class="strip-cell"><div class="strip-label">Battery</div><div class="strip-value text-green">{{ fmt(boat?.house_battery_voltage, 2) }}</div><div class="strip-unit">V</div></div>
            </div>
        </div>

        <!-- Tracker Status -->
        <div class="panel mb-4">
            <div class="flex items-baseline justify-between mb-3">
                <span class="panel-title">Tracker</span>
                <Link href="/admin/tracker" class="text-[12px] text-scarlet font-medium hover:underline">View Tracker →</Link>
            </div>
            <div class="space-y-2 text-[13px]">
                <div class="data-row">
                    <span>Connection</span>
                    <span>{{ tracker?.lte_connected ? 'LTE' : tracker?.wifi_connected ? 'WiFi' : 'Disconnected' }}</span>
                </div>
                <div class="data-row">
                    <span>Signal</span>
                    <span>{{ tracker?.lte_connected ? fmt(tracker.lte_rssi, 0) + ' dBm' : tracker?.wifi_connected ? fmt(tracker.wifi_rssi, 0) + ' dBm' : '—' }}</span>
                </div>
                <div class="data-row">
                    <span>Battery</span>
                    <span>{{ tracker?.battery_percent != null ? fmt(tracker.battery_percent, 0) + '%' : '—' }}</span>
                </div>
            </div>
        </div>

        <!-- Recent Journeys -->
        <div class="panel mb-4" v-if="recentJourneys.length">
            <div class="flex items-baseline justify-between mb-3">
                <span class="panel-title">Recent Journeys</span>
                <Link href="/admin/journeys" class="text-[12px] text-scarlet font-medium hover:underline">View All →</Link>
            </div>
            <div class="space-y-2">
                <Link v-for="j in recentJourneys" :key="j.id" :href="`/journey/${j.slug}`" class="flex items-baseline justify-between text-[13px] py-1.5 hover:text-scarlet transition-colors">
                    <span class="font-medium">{{ j.title }}</span>
                    <span class="text-text-dim tabular-nums">{{ fmtDate(j.started_at) }} · {{ j.distance }} nm</span>
                </Link>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="panel">
            <div class="panel-title mb-3">Quick Links</div>
            <div class="flex flex-wrap gap-2">
                <Link href="/admin/settings" class="btn btn--ghost">Settings</Link>
                <Link href="/admin/stream" class="btn btn--ghost">Stream Monitor</Link>
                <Link href="/admin/team" class="btn btn--ghost">Team</Link>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps({
    boat: Object,
    tracker: Object,
    activeJourney: Object,
    recentJourneys: Array,
});

function fmt(val, decimals = 1) {
    if (val == null || isNaN(val)) return '—';
    return Number(val).toFixed(decimals);
}

function fmtDuration(seconds) {
    if (seconds == null) return '—';
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    return h > 0 ? `${h}h ${m}m` : `${m}m`;
}

function fmtDate(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
}
</script>

<style scoped>
.panel {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: 10px;
    padding: 16px;
}

.panel-title {
    font-size: 15px;
    font-weight: 600;
}

.data-row {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
}

.data-row span:first-child { color: var(--color-text-secondary); }
.data-row span:last-child { font-variant-numeric: tabular-nums; font-weight: 600; }

.strip {
    display: flex;
    background: var(--color-bg);
    border: 1px solid var(--color-border-light);
    border-radius: 8px;
    overflow: hidden;
}

.strip-cell { flex: 1; padding: 10px 12px; text-align: center; }
.strip-cell + .strip-cell { border-left: 1px solid var(--color-border-light); }
.strip-label { font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; color: var(--color-text-dim); margin-bottom: 2px; }
.strip-value { font-size: 20px; font-weight: 700; font-variant-numeric: tabular-nums; line-height: 1; }
.strip-unit { font-size: 10px; color: var(--color-text-dim); margin-top: 2px; }

.btn {
    display: inline-flex;
    align-items: center;
    height: 36px;
    padding: 0 14px;
    border-radius: 7px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.12s ease-out;
}

.btn--primary { background: oklch(0.54 0.22 27); color: white; }
.btn--primary:hover { background: oklch(0.48 0.22 27); }
.btn--ghost { background: var(--color-bg); border: 1px solid var(--color-border); color: var(--color-text-secondary); }
.btn--ghost:hover { border-color: var(--color-text-dim); color: var(--color-text-primary); }
.btn--danger { background: oklch(0.55 0.20 25); color: white; }
.btn--danger:hover { background: oklch(0.48 0.20 25); }
</style>
```

Write to `resources/js/Pages/Admin/Dashboard.vue`.

- [ ] **Step 5: Add the /admin route**

In `routes/web.php`, add inside the admin group, before the settings route:

```php
Route::get('/', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
```

Add `use App\Http\Controllers\Admin\AdminDashboardController;` to the top imports.

- [ ] **Step 6: Build and verify**

Run: `npx vite build && php artisan test`
Expected: Build succeeds, all tests pass.

- [ ] **Step 7: Commit**

```bash
git add resources/js/Layouts/AdminLayout.vue resources/js/Layouts/NavLink.vue app/Http/Controllers/Admin/AdminDashboardController.php resources/js/Pages/Admin/Dashboard.vue routes/web.php
git commit -m "feat: add admin dashboard page and update sidebar navigation"
```

---

### Task 9: Admin Journey Pages (List, Create, Import, Edit)

**Files:**
- Modify: `resources/js/Pages/Admin/Journeys.vue` (replace stub)
- Modify: `resources/js/Pages/Admin/JourneyCreate.vue` (replace stub)
- Modify: `resources/js/Pages/Admin/JourneyImport.vue` (replace stub)
- Modify: `resources/js/Pages/Admin/JourneyEdit.vue` (replace stub)

- [ ] **Step 1: Build the Journey List page**

Replace `resources/js/Pages/Admin/Journeys.vue` with the full implementation. This page shows a table of all journeys with status, date, distance, and actions.

```vue
<template>
    <AdminLayout>
        <Head title="Journeys" />

        <div class="flex items-baseline justify-between mb-6">
            <h1 class="text-[22px] font-bold">Journeys</h1>
            <div class="flex gap-2">
                <Link href="/admin/journeys/create" class="btn btn--primary">Start Journey</Link>
                <Link href="/admin/journeys/import" class="btn btn--ghost">Import from History</Link>
            </div>
        </div>

        <div v-if="journeys.length === 0" class="panel text-center py-12">
            <p class="text-text-secondary text-[14px]">No journeys yet.</p>
        </div>

        <div v-else class="panel overflow-hidden">
            <table class="w-full text-[13px]">
                <thead>
                    <tr class="border-b border-border-light text-left text-text-dim text-[11px] uppercase tracking-wide">
                        <th class="px-4 py-3 font-semibold">Journey</th>
                        <th class="px-4 py-3 font-semibold">Date</th>
                        <th class="px-4 py-3 font-semibold">Duration</th>
                        <th class="px-4 py-3 font-semibold">Distance</th>
                        <th class="px-4 py-3 font-semibold">Status</th>
                        <th class="px-4 py-3 font-semibold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="j in journeys" :key="j.id" class="border-b border-border-light last:border-0 hover:bg-bg/50">
                        <td class="px-4 py-3 font-medium">{{ j.title }}</td>
                        <td class="px-4 py-3 tabular-nums text-text-secondary">{{ fmtDate(j.started_at) }}</td>
                        <td class="px-4 py-3 tabular-nums text-text-secondary">{{ fmtDuration(j.duration) }}</td>
                        <td class="px-4 py-3 tabular-nums text-text-secondary">{{ j.distance }} nm</td>
                        <td class="px-4 py-3">
                            <span class="status-badge" :class="`status-badge--${j.status}`">{{ j.status }}</span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex gap-2 justify-end">
                                <Link v-if="j.status === 'active'" :href="`/admin/journeys/${j.id}/end`" method="post" as="button" class="text-[12px] text-red-500 font-medium hover:underline">End</Link>
                                <Link :href="`/admin/journeys/${j.id}/edit`" class="text-[12px] text-scarlet font-medium hover:underline">Edit</Link>
                                <a :href="`/journey/${j.slug}`" target="_blank" class="text-[12px] text-text-dim font-medium hover:underline">View</a>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps({ journeys: Array });

function fmtDuration(seconds) {
    if (seconds == null) return '—';
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    return h > 0 ? `${h}h ${m}m` : `${m}m`;
}

function fmtDate(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
}
</script>

<style scoped>
.panel { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: 10px; padding: 0; }
.btn { display: inline-flex; align-items: center; height: 36px; padding: 0 14px; border-radius: 7px; font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.12s ease-out; }
.btn--primary { background: oklch(0.54 0.22 27); color: white; }
.btn--primary:hover { background: oklch(0.48 0.22 27); }
.btn--ghost { background: var(--color-surface); border: 1px solid var(--color-border); color: var(--color-text-secondary); }
.btn--ghost:hover { border-color: var(--color-text-dim); color: var(--color-text-primary); }
.status-badge { font-size: 10px; font-weight: 600; letter-spacing: 0.03em; padding: 3px 8px; border-radius: 4px; text-transform: capitalize; }
.status-badge--active { color: oklch(0.62 0.15 155); background: oklch(0.62 0.15 155 / 0.10); }
.status-badge--completed { color: oklch(0.55 0.15 240); background: oklch(0.55 0.15 240 / 0.10); }
.status-badge--abandoned { color: oklch(0.60 0.06 55); background: oklch(0.60 0.06 55 / 0.10); }
</style>
```

- [ ] **Step 2: Build the Start Journey page**

Replace `resources/js/Pages/Admin/JourneyCreate.vue`:

```vue
<template>
    <AdminLayout>
        <Head title="Start Journey" />
        <h1 class="text-[22px] font-bold mb-6">Start Journey</h1>

        <div v-if="hasActive" class="panel mb-6 border-amber-200 bg-amber-50">
            <p class="text-[13px] text-amber-800">A journey is already active. End it before starting a new one.</p>
        </div>

        <form @submit.prevent="form.post(route('admin.journeys.store'))" class="panel p-6">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="field-label">From</label>
                    <input v-model="form.from_port" type="text" required placeholder="e.g. Lymington" class="field-input" />
                    <p v-if="form.errors.from_port" class="field-error">{{ form.errors.from_port }}</p>
                </div>
                <div>
                    <label class="field-label">To</label>
                    <input v-model="form.to_port" type="text" required placeholder="e.g. Yarmouth" class="field-input" />
                    <p v-if="form.errors.to_port" class="field-error">{{ form.errors.to_port }}</p>
                </div>
            </div>
            <div class="mt-4">
                <label class="field-label">GPX Route (optional)</label>
                <input type="file" accept=".gpx" @change="form.gpx_file = $event.target.files[0]" class="field-input text-[13px]" />
                <p v-if="form.errors.gpx_file" class="field-error">{{ form.errors.gpx_file }}</p>
            </div>
            <div class="mt-4">
                <label class="field-label">Notes (optional)</label>
                <textarea v-model="form.notes" rows="3" class="field-input" placeholder="Any notes about this passage..."></textarea>
            </div>
            <div class="flex items-center gap-3 mt-5">
                <button type="submit" :disabled="form.processing || hasActive" class="btn btn--primary">Start Journey</button>
                <Link href="/admin/journeys" class="btn btn--ghost">Cancel</Link>
            </div>
        </form>
    </AdminLayout>
</template>

<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps({ hasActive: Boolean });

const form = useForm({
    from_port: '',
    to_port: '',
    gpx_file: null,
    notes: '',
});

function route(name) {
    const routes = { 'admin.journeys.store': '/admin/journeys' };
    return routes[name];
}
</script>

<style scoped>
.panel { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: 10px; }
.field-label { display: block; font-size: 13px; font-weight: 500; color: var(--color-text-secondary); margin-bottom: 6px; }
.field-input { width: 100%; height: 42px; padding: 0 14px; font-size: 14px; background: var(--color-bg); border: 1px solid var(--color-border); border-radius: 7px; font-family: inherit; outline: none; }
.field-input:focus { border-color: oklch(0.54 0.22 27); box-shadow: 0 0 0 2px oklch(0.54 0.22 27 / 0.1); }
textarea.field-input { height: auto; padding: 10px 14px; resize: vertical; }
.field-error { margin-top: 4px; font-size: 12px; color: var(--color-error); }
.btn { display: inline-flex; align-items: center; height: 36px; padding: 0 14px; border-radius: 7px; font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.12s ease-out; }
.btn--primary { background: oklch(0.54 0.22 27); color: white; }
.btn--primary:hover { background: oklch(0.48 0.22 27); }
.btn--primary:disabled { opacity: 0.5; cursor: not-allowed; }
.btn--ghost { background: var(--color-bg); border: 1px solid var(--color-border); color: var(--color-text-secondary); }
.btn--ghost:hover { border-color: var(--color-text-dim); color: var(--color-text-primary); }
</style>
```

- [ ] **Step 3: Build the Import from History page**

Replace `resources/js/Pages/Admin/JourneyImport.vue`:

```vue
<template>
    <AdminLayout>
        <Head title="Import Journey" />
        <h1 class="text-[22px] font-bold mb-6">Import from History</h1>

        <form @submit.prevent="form.post('/admin/journeys/import')" class="panel p-6">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="field-label">From</label>
                    <input v-model="form.from_port" type="text" required placeholder="e.g. Lymington" class="field-input" />
                    <p v-if="form.errors.from_port" class="field-error">{{ form.errors.from_port }}</p>
                </div>
                <div>
                    <label class="field-label">To</label>
                    <input v-model="form.to_port" type="text" required placeholder="e.g. Yarmouth" class="field-input" />
                    <p v-if="form.errors.to_port" class="field-error">{{ form.errors.to_port }}</p>
                </div>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 mt-4">
                <div>
                    <label class="field-label">Start Time</label>
                    <input v-model="form.started_at" type="datetime-local" required class="field-input tabular-nums" />
                    <p v-if="form.errors.started_at" class="field-error">{{ form.errors.started_at }}</p>
                </div>
                <div>
                    <label class="field-label">End Time</label>
                    <input v-model="form.ended_at" type="datetime-local" required class="field-input tabular-nums" />
                    <p v-if="form.errors.ended_at" class="field-error">{{ form.errors.ended_at }}</p>
                </div>
            </div>
            <div class="mt-4">
                <label class="field-label">GPX Route (optional)</label>
                <input type="file" accept=".gpx" @change="form.gpx_file = $event.target.files[0]" class="field-input text-[13px]" />
            </div>
            <div class="mt-4">
                <label class="field-label">Notes (optional)</label>
                <textarea v-model="form.notes" rows="3" class="field-input" placeholder="Any notes about this passage..."></textarea>
            </div>
            <p class="mt-3 text-[12px] text-text-dim">Track data will be imported from Prometheus. This is limited by Prometheus retention (typically 15-30 days).</p>
            <div class="flex items-center gap-3 mt-5">
                <button type="submit" :disabled="form.processing" class="btn btn--primary">Import Journey</button>
                <Link href="/admin/journeys" class="btn btn--ghost">Cancel</Link>
            </div>
        </form>
    </AdminLayout>
</template>

<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const form = useForm({
    from_port: '',
    to_port: '',
    started_at: '',
    ended_at: '',
    gpx_file: null,
    notes: '',
});
</script>

<style scoped>
.panel { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: 10px; }
.field-label { display: block; font-size: 13px; font-weight: 500; color: var(--color-text-secondary); margin-bottom: 6px; }
.field-input { width: 100%; height: 42px; padding: 0 14px; font-size: 14px; background: var(--color-bg); border: 1px solid var(--color-border); border-radius: 7px; font-family: inherit; outline: none; }
.field-input:focus { border-color: oklch(0.54 0.22 27); box-shadow: 0 0 0 2px oklch(0.54 0.22 27 / 0.1); }
textarea.field-input { height: auto; padding: 10px 14px; resize: vertical; }
.field-error { margin-top: 4px; font-size: 12px; color: var(--color-error); }
.btn { display: inline-flex; align-items: center; height: 36px; padding: 0 14px; border-radius: 7px; font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.12s ease-out; }
.btn--primary { background: oklch(0.54 0.22 27); color: white; }
.btn--primary:hover { background: oklch(0.48 0.22 27); }
.btn--primary:disabled { opacity: 0.5; cursor: not-allowed; }
.btn--ghost { background: var(--color-bg); border: 1px solid var(--color-border); color: var(--color-text-secondary); }
.btn--ghost:hover { border-color: var(--color-text-dim); color: var(--color-text-primary); }
</style>
```

- [ ] **Step 4: Build the Edit Journey page**

Replace `resources/js/Pages/Admin/JourneyEdit.vue`:

```vue
<template>
    <AdminLayout>
        <Head title="Edit Journey" />
        <h1 class="text-[22px] font-bold mb-6">Edit Journey</h1>

        <form @submit.prevent="form.put(`/admin/journeys/${journey.id}`)" class="panel p-6 mb-6">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="field-label">Title</label>
                    <input v-model="form.title" type="text" required class="field-input" />
                    <p v-if="form.errors.title" class="field-error">{{ form.errors.title }}</p>
                </div>
                <div>
                    <label class="field-label">Slug</label>
                    <input v-model="form.slug" type="text" required class="field-input" />
                    <p v-if="form.errors.slug" class="field-error">{{ form.errors.slug }}</p>
                </div>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 mt-4">
                <div>
                    <label class="field-label">From</label>
                    <input v-model="form.from_port" type="text" required class="field-input" />
                </div>
                <div>
                    <label class="field-label">To</label>
                    <input v-model="form.to_port" type="text" required class="field-input" />
                </div>
            </div>
            <div class="mt-4">
                <label class="field-label">Notes</label>
                <textarea v-model="form.notes" rows="3" class="field-input"></textarea>
            </div>
            <div class="mt-4 flex items-center gap-3">
                <label class="flex items-center gap-2 text-[13px] cursor-pointer">
                    <input v-model="form.is_public" type="checkbox" class="w-4 h-4 rounded border-border accent-scarlet" />
                    <span>Publicly visible</span>
                </label>
            </div>
            <div class="flex items-center gap-3 mt-5">
                <button type="submit" :disabled="form.processing" class="btn btn--primary">Save Changes</button>
                <Link href="/admin/journeys" class="btn btn--ghost">Cancel</Link>
                <Transition name="saved-fade"><span v-if="form.wasSuccessful" class="text-[13px] text-green">Saved.</span></Transition>
            </div>
        </form>

        <!-- GPX Upload -->
        <form @submit.prevent="gpxForm.post(`/admin/journeys/${journey.id}/gpx`)" class="panel p-6 mb-6">
            <h2 class="text-[15px] font-semibold mb-4">GPX Route</h2>
            <p v-if="journey.gpx_route_path" class="text-[13px] text-green mb-3">Route file uploaded.</p>
            <div>
                <label class="field-label">{{ journey.gpx_route_path ? 'Replace GPX file' : 'Upload GPX file' }}</label>
                <input type="file" accept=".gpx" @change="gpxForm.gpx_file = $event.target.files[0]" class="field-input text-[13px]" />
            </div>
            <div class="flex items-center gap-3 mt-4">
                <button type="submit" :disabled="gpxForm.processing || !gpxForm.gpx_file" class="btn btn--primary">Upload</button>
                <Transition name="saved-fade"><span v-if="gpxForm.wasSuccessful" class="text-[13px] text-green">Uploaded.</span></Transition>
            </div>
        </form>

        <!-- Danger Zone -->
        <div class="panel p-6 border-red-200">
            <h2 class="text-[15px] font-semibold mb-3 text-red-600">Danger Zone</h2>
            <div class="flex items-center justify-between">
                <p class="text-[13px] text-text-secondary">Permanently delete this journey and all its track data.</p>
                <Link :href="`/admin/journeys/${journey.id}`" method="delete" as="button" class="btn btn--danger" @click="(e) => { if (!confirm('Delete this journey?')) e.preventDefault(); }">Delete Journey</Link>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({ journey: Object });

const form = useForm({
    title: props.journey.title,
    slug: props.journey.slug,
    from_port: props.journey.from_port,
    to_port: props.journey.to_port,
    is_public: props.journey.is_public,
    notes: props.journey.notes ?? '',
});

const gpxForm = useForm({
    gpx_file: null,
});
</script>

<style scoped>
.panel { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: 10px; }
.field-label { display: block; font-size: 13px; font-weight: 500; color: var(--color-text-secondary); margin-bottom: 6px; }
.field-input { width: 100%; height: 42px; padding: 0 14px; font-size: 14px; background: var(--color-bg); border: 1px solid var(--color-border); border-radius: 7px; font-family: inherit; outline: none; }
.field-input:focus { border-color: oklch(0.54 0.22 27); box-shadow: 0 0 0 2px oklch(0.54 0.22 27 / 0.1); }
textarea.field-input { height: auto; padding: 10px 14px; resize: vertical; }
.field-error { margin-top: 4px; font-size: 12px; color: var(--color-error); }
.btn { display: inline-flex; align-items: center; height: 36px; padding: 0 14px; border-radius: 7px; font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.12s ease-out; }
.btn--primary { background: oklch(0.54 0.22 27); color: white; }
.btn--primary:hover { background: oklch(0.48 0.22 27); }
.btn--primary:disabled { opacity: 0.5; cursor: not-allowed; }
.btn--ghost { background: var(--color-bg); border: 1px solid var(--color-border); color: var(--color-text-secondary); }
.btn--ghost:hover { border-color: var(--color-text-dim); color: var(--color-text-primary); }
.btn--danger { background: oklch(0.55 0.20 25); color: white; }
.btn--danger:hover { background: oklch(0.48 0.20 25); }
.saved-fade-enter-active { transition: opacity 0.3s; }
.saved-fade-enter-from { opacity: 0; }
</style>
```

- [ ] **Step 5: Build and verify**

Run: `npx vite build && php artisan test`
Expected: Build succeeds, all tests pass.

- [ ] **Step 6: Commit**

```bash
git add resources/js/Pages/Admin/Journeys.vue resources/js/Pages/Admin/JourneyCreate.vue resources/js/Pages/Admin/JourneyImport.vue resources/js/Pages/Admin/JourneyEdit.vue
git commit -m "feat: build admin journey list, create, import, and edit pages"
```

---

### Task 10: Update Admin Settings Page

**Files:**
- Modify: `resources/js/Pages/Admin/Settings.vue` (remove Current Passage section)
- Modify: `app/Http/Controllers/Admin/SettingsController.php` (remove updatePassage)
- Modify: `routes/web.php` (remove passage route)

- [ ] **Step 1: Remove the Current Passage section from Settings.vue**

In `resources/js/Pages/Admin/Settings.vue`, delete the entire `<!-- Current Passage -->` form block (lines 44-92 in the original file). Also remove the `passageForm` from the `<script setup>` section:

Remove these lines from the script:
```js
const passageForm = useForm({
    passage_from: props.settings?.passage_from ?? '',
    passage_to: props.settings?.passage_to ?? '',
    trip_offset: props.settings?.trip_offset ?? 0,
});
```

Keep the trip_offset in the Port Settings section instead — add a trip offset field to the port form. In the `portForm` definition, add `trip_offset`:

```js
const portForm = useForm({
    port_name: props.settings?.port_name ?? '',
    trip_offset: props.settings?.trip_offset ?? 0,
});
```

In the Port Settings template section, add the trip offset field after the port name input:

```vue
<div class="mt-4">
    <label class="block text-[13px] font-medium text-text-secondary mb-1.5">Trip Offset (nm)</label>
    <input
        v-model.number="portForm.trip_offset"
        type="number"
        step="0.1"
        min="0"
        placeholder="0"
        class="w-full h-[42px] px-3.5 text-sm bg-bg border border-border rounded-[7px] font-sans focus:border-scarlet focus:ring-1 focus:ring-scarlet/20 outline-none tabular-nums sm:max-w-[200px]"
    />
    <p class="mt-1 text-[12px] text-text-secondary">Subtracted from the trip log when displayed on the dashboard and stream.</p>
</div>
```

- [ ] **Step 2: Update SettingsController**

In `app/Http/Controllers/Admin/SettingsController.php`, remove the `updatePassage` method entirely. Update `updatePort` to also handle trip_offset:

```php
public function updatePort(Request $request)
{
    $validated = $request->validate([
        'port_name' => ['nullable', 'string', 'max:100'],
        'trip_offset' => ['nullable', 'numeric', 'min:0'],
    ]);

    BoatSetting::setValue('port_name', $validated['port_name'] ?? '');
    BoatSetting::setValue('trip_offset', $validated['trip_offset'] ?? 0);

    return back()->with('success', 'Port settings updated.');
}
```

- [ ] **Step 3: Remove the passage route from web.php**

In `routes/web.php`, remove:
```php
Route::put('/settings/passage', [SettingsController::class, 'updatePassage'])->name('admin.settings.passage');
```

- [ ] **Step 4: Update the SettingsTest**

In `tests/Feature/SettingsTest.php`, remove or update the `test_can_save_passage` test if it exists, since that route no longer exists. Check the file first and adjust accordingly.

- [ ] **Step 5: Run tests**

Run: `php artisan test`
Expected: All tests pass.

- [ ] **Step 6: Commit**

```bash
git add resources/js/Pages/Admin/Settings.vue app/Http/Controllers/Admin/SettingsController.php routes/web.php tests/Feature/SettingsTest.php
git commit -m "feat: remove passage settings, move trip offset to port settings"
```

---

### Task 11: Public Journey Controller + Routes

**Files:**
- Create: `app/Http/Controllers/JourneyViewController.php`
- Modify: `routes/web.php` (add public journey routes)
- Test: `tests/Feature/JourneyViewTest.php`

- [ ] **Step 1: Write tests**

```php
<?php

namespace Tests\Feature;

use App\Models\Journey;
use App\Models\JourneyTrackPoint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JourneyViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_journey_renders(): void
    {
        $journey = Journey::factory()->create(['is_public' => true]);

        $response = $this->get("/journey/{$journey->slug}");
        $response->assertStatus(200);
    }

    public function test_private_journey_returns_403_for_guest(): void
    {
        $journey = Journey::factory()->create(['is_public' => false]);

        $response = $this->get("/journey/{$journey->slug}");
        $response->assertStatus(403);
    }

    public function test_private_journey_renders_for_authenticated_user(): void
    {
        $journey = Journey::factory()->create(['is_public' => false]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get("/journey/{$journey->slug}");
        $response->assertStatus(200);
    }

    public function test_journey_index_redirects_to_active(): void
    {
        $journey = Journey::factory()->active()->create();

        $response = $this->get('/journey');
        $response->assertRedirect("/journey/{$journey->slug}");
    }

    public function test_journey_index_returns_404_when_no_active(): void
    {
        $response = $this->get('/journey');
        $response->assertStatus(404);
    }

    public function test_track_api_returns_json(): void
    {
        $journey = Journey::factory()->create(['is_public' => true]);
        $journey->trackPoints()->create([
            'recorded_at' => now(),
            'latitude' => 50.75,
            'longitude' => -1.54,
            'speed_sog' => 4.5,
        ]);

        $response = $this->getJson("/api/journey/{$journey->slug}/track");
        $response->assertStatus(200);
        $response->assertJsonCount(1);
    }
}
```

Write to `tests/Feature/JourneyViewTest.php`.

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/JourneyViewTest.php`
Expected: All tests fail (404s).

- [ ] **Step 3: Create the JourneyViewController**

```php
<?php

namespace App\Http\Controllers;

use App\Models\Journey;
use Illuminate\Http\Request;
use Inertia\Inertia;

class JourneyViewController extends Controller
{
    public function index()
    {
        $active = Journey::current();

        if (!$active) {
            abort(404);
        }

        return redirect("/journey/{$active->slug}");
    }

    public function show(Request $request, string $slug)
    {
        $journey = Journey::where('slug', $slug)->firstOrFail();

        if (!$journey->is_public && !$request->user()) {
            abort(403);
        }

        $trackPoints = $journey->trackPoints()
            ->select(['recorded_at', 'latitude', 'longitude', 'speed_sog', 'heading', 'cog', 'depth', 'wind_speed_apparent', 'wind_angle_apparent', 'wind_speed_true', 'wind_direction_true', 'house_battery_voltage', 'house_battery_current', 'heel'])
            ->get();

        $track = $trackPoints->map(fn ($p) => [
            $p->latitude, $p->longitude, $p->speed_sog ?? 0,
        ])->values()->toArray();

        $decimated = count($track) > 2000
            ? $this->decimateTrack($track, 2000)
            : $track;

        return Inertia::render('Public/Journey', [
            'journey' => [
                'id' => $journey->id,
                'slug' => $journey->slug,
                'title' => $journey->title,
                'from_port' => $journey->from_port,
                'to_port' => $journey->to_port,
                'started_at' => $journey->started_at->toIso8601String(),
                'ended_at' => $journey->ended_at?->toIso8601String(),
                'status' => $journey->status,
                'distance' => $journey->distance,
                'duration' => $journey->duration,
                'notes' => $journey->notes,
            ],
            'routeWaypoints' => $journey->route_waypoints ?? [],
            'gpsTrack' => $decimated,
            'trackPoints' => $trackPoints->values()->toArray(),
        ]);
    }

    public function track(Request $request, string $slug)
    {
        $journey = Journey::where('slug', $slug)->firstOrFail();

        if (!$journey->is_public && !$request->user()) {
            abort(403);
        }

        $points = $journey->trackPoints()
            ->select(['recorded_at', 'latitude', 'longitude', 'speed_sog', 'heading', 'cog', 'depth', 'wind_speed_apparent', 'wind_angle_apparent', 'wind_speed_true', 'wind_direction_true', 'house_battery_voltage', 'house_battery_current', 'heel'])
            ->get();

        return response()->json($points);
    }

    protected function decimateTrack(array $track, int $maxPoints): array
    {
        $step = max(1, (int) ceil(count($track) / $maxPoints));
        $result = [];
        for ($i = 0; $i < count($track); $i += $step) {
            $result[] = $track[$i];
        }
        if (end($result) !== end($track)) {
            $result[] = end($track);
        }
        return $result;
    }
}
```

Write to `app/Http/Controllers/JourneyViewController.php`.

- [ ] **Step 4: Add public routes**

In `routes/web.php`, add these public routes (outside the admin group, near the other public routes):

```php
Route::get('/journey', [JourneyViewController::class, 'index'])->name('journey.index');
Route::get('/journey/{slug}', [JourneyViewController::class, 'show'])->name('journey.show');
Route::get('/api/journey/{slug}/track', [JourneyViewController::class, 'track'])->name('journey.track');
```

Add `use App\Http\Controllers\JourneyViewController;` to the imports.

- [ ] **Step 5: Create stub Journey Vue page**

```vue
<template>
    <Head :title="journey.title" />
    <div class="journey">
        <p>Journey replay page — to be built in Task 14.</p>
    </div>
</template>

<script setup>
import { Head } from '@inertiajs/vue3';
defineProps({ journey: Object, routeWaypoints: Array, gpsTrack: Array, trackPoints: Array });
</script>
```

Write to `resources/js/Pages/Public/Journey.vue`.

- [ ] **Step 6: Run tests**

Run: `php artisan test tests/Feature/JourneyViewTest.php`
Expected: All 6 tests pass.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/JourneyViewController.php routes/web.php tests/Feature/JourneyViewTest.php resources/js/Pages/Public/Journey.vue
git commit -m "feat: add public journey view controller and routes"
```

---

### Task 12: GPX Route Layer on Maps

**Files:**
- Modify: `resources/js/composables/useScarletMetrics.js` (add route waypoints rendering)
- Modify: `resources/js/scarlet.js` (add route polyline helper)

- [ ] **Step 1: Add route rendering helper to scarlet.js**

In `resources/js/scarlet.js`, add at the end of the file:

```js
export function addRouteLayer(map, waypoints) {
    if (!waypoints?.length) return { polyline: null, markers: [] };

    const latlngs = waypoints.map(w => [w.lat, w.lng]);
    const polyline = L.polyline(latlngs, {
        color: 'oklch(0.65 0.10 240 / 0.5)',
        weight: 2.5,
        dashArray: '8, 6',
        opacity: 0.8,
    }).addTo(map);

    const markers = waypoints
        .filter(w => w.name)
        .map(w => {
            return L.circleMarker([w.lat, w.lng], {
                radius: 4,
                color: 'oklch(0.65 0.10 240 / 0.6)',
                fillColor: 'oklch(0.65 0.10 240 / 0.3)',
                fillOpacity: 1,
                weight: 1.5,
            })
            .bindTooltip(w.name, { direction: 'top', offset: [0, -6], className: 'route-tooltip' })
            .addTo(map);
        });

    return { polyline, markers };
}
```

- [ ] **Step 2: Add routeWaypoints support to useScarletMetrics.js**

In `resources/js/composables/useScarletMetrics.js`, add `routeWaypoints` to the options destructuring, and add route rendering in `addMapTarget`. Update the import to include `addRouteLayer`:

At the top, update the import:
```js
import { speedToColor, makeBoatIcon, formatCoord, getWeatherIcon, getWeatherLabel, addRouteLayer } from '../scarlet';
```

In the options destructuring, add:
```js
routeWaypoints = [],
```

In the `addMapTarget` function, after the existing track rendering block (after the closing `}` of the `if (gps.value?.latitude && !trackPoints.length)` block), add:

```js
if (routeWaypoints.length) {
    addRouteLayer(target.map, routeWaypoints);
}
```

Also add `routeWaypoints` to the options destructuring at the top of `useScarletMetrics`.

- [ ] **Step 3: Build and verify**

Run: `npx vite build`
Expected: Build succeeds.

- [ ] **Step 4: Commit**

```bash
git add resources/js/scarlet.js resources/js/composables/useScarletMetrics.js
git commit -m "feat: add GPX planned route rendering on maps"
```

---

### Task 13: Dashboard + Overlay GPX Integration

**Files:**
- Modify: `resources/js/Pages/Public/Dashboard.vue` (pass routeWaypoints to composable)
- Modify: `resources/js/Pages/Public/Overlay.vue` (pass routeWaypoints to composable)

- [ ] **Step 1: Update Dashboard.vue**

In `resources/js/Pages/Public/Dashboard.vue`, add `routeWaypoints` to the props:

```js
routeWaypoints: { type: Array, default: () => [] },
```

Then pass it to the `useScarletMetrics` call:

```js
routeWaypoints: props.routeWaypoints,
```

- [ ] **Step 2: Update Overlay.vue**

In `resources/js/Pages/Public/Overlay.vue`, add `routeWaypoints` to the props:

```js
routeWaypoints: { type: Array, default: () => [] },
```

Then pass it to the `useScarletMetrics` call:

```js
routeWaypoints: props.routeWaypoints,
```

- [ ] **Step 3: Build and verify**

Run: `npx vite build`
Expected: Build succeeds.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/Public/Dashboard.vue resources/js/Pages/Public/Overlay.vue
git commit -m "feat: pass GPX route waypoints to dashboard and overlay maps"
```

---

### Task 14: Journey Replay Page

**Files:**
- Modify: `resources/js/Pages/Public/Journey.vue` (replace stub with full implementation)

This is the largest frontend task. The page needs: full-viewport map, speed-coloured track, planned route overlay, timeline scrubber, floating metric pills, boat icon that moves with the scrubber.

- [ ] **Step 1: Build the Journey replay page**

Replace `resources/js/Pages/Public/Journey.vue` with the full implementation. The page reuses `speedToColor`, `makeBoatIcon`, and `addRouteLayer` from `scarlet.js`, and the map tile layer pattern from the composable.

```vue
<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { speedToColor, makeBoatIcon, formatCoord, addRouteLayer } from '../../scarlet';

const props = defineProps({
    journey: Object,
    routeWaypoints: { type: Array, default: () => [] },
    gpsTrack: { type: Array, default: () => [] },
    trackPoints: { type: Array, default: () => [] },
});

const mapEl = ref(null);
const scrubIndex = ref(0);
const playing = ref(false);
let map = null;
let marker = null;
let playInterval = null;

const currentPoint = computed(() => props.trackPoints[scrubIndex.value] ?? null);
const totalPoints = computed(() => props.trackPoints.length);
const progress = computed(() => totalPoints.value > 1 ? scrubIndex.value / (totalPoints.value - 1) : 0);

const startTime = computed(() => {
    if (!props.journey.started_at) return '';
    return new Date(props.journey.started_at).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
});

const endTime = computed(() => {
    if (!props.journey.ended_at) return '';
    return new Date(props.journey.ended_at).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
});

const scrubTime = computed(() => {
    const p = currentPoint.value;
    if (!p?.recorded_at) return '';
    return new Date(p.recorded_at).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
});

const journeyDate = computed(() => {
    if (!props.journey.started_at) return '';
    return new Date(props.journey.started_at).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
});

const durationText = computed(() => {
    const s = props.journey.duration;
    if (s == null) return '';
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    return h > 0 ? `${h}h ${m}m` : `${m}m`;
});

function fmt(val, decimals = 1) {
    if (val == null || isNaN(val)) return '--';
    return Number(val).toFixed(decimals);
}

function togglePlay() {
    if (playing.value) {
        stopPlay();
    } else {
        if (scrubIndex.value >= totalPoints.value - 1) scrubIndex.value = 0;
        playing.value = true;
        playInterval = setInterval(() => {
            if (scrubIndex.value >= totalPoints.value - 1) {
                stopPlay();
                return;
            }
            scrubIndex.value++;
        }, 100);
    }
}

function stopPlay() {
    playing.value = false;
    if (playInterval) { clearInterval(playInterval); playInterval = null; }
}

function skipStart() { stopPlay(); scrubIndex.value = 0; }
function skipEnd() { stopPlay(); scrubIndex.value = Math.max(0, totalPoints.value - 1); }

watch(scrubIndex, (idx) => {
    const p = props.trackPoints[idx];
    if (!p || !map) return;
    const pos = [p.latitude, p.longitude];
    const heading = p.heading ?? p.cog ?? 0;
    const icon = makeBoatIcon(heading);
    if (marker) {
        marker.setLatLng(pos).setIcon(icon);
    } else {
        marker = L.marker(pos, { icon }).addTo(map);
    }
    map.panTo(pos, { animate: true, duration: 0.3 });
});

onMounted(() => {
    if (!mapEl.value || !props.gpsTrack.length) return;

    const firstPt = props.gpsTrack[0];
    map = L.map(mapEl.value, { zoomControl: false, attributionControl: false })
        .setView([firstPt[0], firstPt[1]], 14);
    L.tileLayer('/openseamap/{z}/{x}/{y}', { maxZoom: 18 }).addTo(map);

    // Draw full track
    for (let i = 1; i < props.gpsTrack.length; i++) {
        L.polyline(
            [[props.gpsTrack[i - 1][0], props.gpsTrack[i - 1][1]], [props.gpsTrack[i][0], props.gpsTrack[i][1]]],
            { color: speedToColor(props.gpsTrack[i][2]), weight: 3, opacity: 0.85 }
        ).addTo(map);
    }

    // Fit map to track bounds
    const bounds = L.latLngBounds(props.gpsTrack.map(p => [p[0], p[1]]));
    map.fitBounds(bounds, { padding: [60, 60] });

    // Draw planned route
    if (props.routeWaypoints.length) {
        addRouteLayer(map, props.routeWaypoints);
    }

    // Place boat at start
    if (props.trackPoints.length) {
        const first = props.trackPoints[0];
        const icon = makeBoatIcon(first.heading ?? first.cog ?? 0);
        marker = L.marker([first.latitude, first.longitude], { icon }).addTo(map);
    }
});

onUnmounted(() => {
    stopPlay();
    map?.remove();
});
</script>

<template>
    <Head :title="journey.title" />

    <div class="journey-view">
        <div ref="mapEl" class="journey-map"></div>

        <!-- Journey title overlay -->
        <div class="title-overlay">
            <div class="title-name">{{ journey.title }}</div>
            <div class="title-meta">{{ journeyDate }} · {{ durationText }} · {{ journey.distance }} nm</div>
        </div>

        <!-- Metric pills -->
        <div class="metrics-overlay" v-if="currentPoint">
            <div class="metric-pill">
                <div class="metric-label">SPEED</div>
                <div class="metric-value">{{ fmt(currentPoint.speed_sog) }} kn</div>
            </div>
            <div class="metric-pill">
                <div class="metric-label">HEADING</div>
                <div class="metric-value">{{ fmt(currentPoint.heading ?? currentPoint.cog, 0) }}°</div>
            </div>
            <div class="metric-pill">
                <div class="metric-label">DEPTH</div>
                <div class="metric-value">{{ fmt(currentPoint.depth) }} m</div>
            </div>
            <div class="metric-pill">
                <div class="metric-label">WIND</div>
                <div class="metric-value">{{ fmt(currentPoint.wind_speed_true ?? currentPoint.wind_speed_apparent) }} kn</div>
            </div>
            <div class="metric-pill">
                <div class="metric-label">HEEL</div>
                <div class="metric-value">{{ fmt(currentPoint.heel, 0) }}°</div>
            </div>
        </div>

        <!-- Scrub time -->
        <div class="scrub-time" v-if="currentPoint">{{ scrubTime }}</div>

        <!-- Timeline scrubber -->
        <div class="timeline" v-if="totalPoints > 0">
            <span class="timeline-time">{{ startTime }}</span>
            <div class="timeline-track">
                <input
                    type="range"
                    :min="0"
                    :max="totalPoints - 1"
                    v-model.number="scrubIndex"
                    class="timeline-slider"
                    @input="stopPlay()"
                />
                <div class="timeline-fill" :style="{ width: (progress * 100) + '%' }"></div>
            </div>
            <span class="timeline-time">{{ endTime }}</span>
            <div class="timeline-controls">
                <button class="tl-btn" @click="skipStart" title="Skip to start">⏮</button>
                <button class="tl-btn" @click="togglePlay" :title="playing ? 'Pause' : 'Play'">{{ playing ? '⏸' : '▶' }}</button>
                <button class="tl-btn" @click="skipEnd" title="Skip to end">⏭</button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.journey-view {
    position: fixed;
    inset: 0;
    font-family: 'Outfit', system-ui, sans-serif;
    font-variant-numeric: tabular-nums;
    color: oklch(0.96 0.005 70);
    overflow: hidden;
}

.journey-map { position: absolute; inset: 0; z-index: 0; background: oklch(0.12 0.02 210); }

.title-overlay {
    position: absolute;
    top: 16px;
    left: 16px;
    z-index: 10;
    background: oklch(0.08 0.008 40 / 0.72);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    border-radius: 10px;
    padding: 12px 18px;
    border: 1px solid oklch(0.32 0.01 40 / 0.18);
}

.title-name { font-size: 18px; font-weight: 600; }
.title-meta { font-size: 12px; color: oklch(0.62 0.008 70); margin-top: 3px; }

.metrics-overlay {
    position: absolute;
    bottom: 70px;
    right: 16px;
    z-index: 10;
    display: flex;
    gap: 5px;
}

.metric-pill {
    background: oklch(0.08 0.008 40 / 0.72);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    border-radius: 7px;
    padding: 7px 12px;
    text-align: center;
    border: 1px solid oklch(0.32 0.01 40 / 0.18);
    min-width: 52px;
}

.metric-label {
    font-size: 8px;
    font-weight: 600;
    letter-spacing: 0.04em;
    color: oklch(0.62 0.008 70);
    margin-bottom: 2px;
}

.metric-value {
    font-size: 14px;
    font-weight: 500;
    color: oklch(0.96 0.005 70);
    line-height: 1.1;
}

.scrub-time {
    position: absolute;
    bottom: 70px;
    left: 16px;
    z-index: 10;
    background: oklch(0.08 0.008 40 / 0.72);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    border-radius: 7px;
    padding: 7px 12px;
    border: 1px solid oklch(0.32 0.01 40 / 0.18);
    font-size: 14px;
    font-weight: 500;
}

.timeline {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 10;
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    background: oklch(0.06 0.008 40 / 0.85);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    border-top: 1px solid oklch(0.20 0.01 40 / 0.3);
}

.timeline-time {
    font-size: 12px;
    color: oklch(0.62 0.008 70);
    white-space: nowrap;
    min-width: 40px;
}

.timeline-track {
    flex: 1;
    position: relative;
    height: 6px;
    background: oklch(0.25 0.005 40);
    border-radius: 3px;
    overflow: hidden;
}

.timeline-fill {
    position: absolute;
    top: 0;
    left: 0;
    height: 100%;
    border-radius: 3px;
    background: oklch(0.54 0.22 27);
    pointer-events: none;
}

.timeline-slider {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    cursor: pointer;
    margin: 0;
    -webkit-appearance: none;
    z-index: 2;
}

.timeline-controls {
    display: flex;
    gap: 6px;
    margin-left: 4px;
}

.tl-btn {
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: oklch(0.20 0.005 40 / 0.6);
    border-radius: 6px;
    border: 1px solid oklch(0.30 0.01 40 / 0.3);
    color: oklch(0.85 0.005 70);
    font-size: 13px;
    cursor: pointer;
    transition: background 0.12s;
}

.tl-btn:hover { background: oklch(0.28 0.005 40 / 0.7); }
</style>

<style>
.boat-marker { background: transparent !important; border: none !important; }
.route-tooltip {
    background: oklch(0.08 0.008 40 / 0.85) !important;
    color: oklch(0.90 0.005 70) !important;
    border: 1px solid oklch(0.32 0.01 40 / 0.3) !important;
    border-radius: 5px !important;
    font-family: 'Outfit', system-ui, sans-serif !important;
    font-size: 12px !important;
    padding: 4px 8px !important;
    box-shadow: none !important;
}
.route-tooltip::before { border-top-color: oklch(0.32 0.01 40 / 0.3) !important; }
</style>
```

Write to `resources/js/Pages/Public/Journey.vue`.

- [ ] **Step 2: Build and verify**

Run: `npx vite build && php artisan test`
Expected: Build succeeds, all tests pass.

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/Public/Journey.vue
git commit -m "feat: build journey replay page with timeline scrubber and metric pills"
```

---

## Self-Review Checklist

**Spec coverage:**
- [x] Data model (journeys + track points) — Task 1
- [x] Eloquent models with scopes, accessors, slug generation — Task 2
- [x] GPX parsing — Task 3
- [x] Journey lifecycle (start, end, auto-stop, abandon) — Task 4
- [x] Settings migration (passage_from/to from active journey) — Task 5
- [x] MetricsPushCommand track recording — Task 5
- [x] Retrospective import job — Task 6
- [x] Admin routes + controller — Task 7
- [x] Admin navigation update — Task 8
- [x] Admin dashboard page — Task 8
- [x] Admin journey list/create/import/edit pages — Task 9
- [x] Settings page update (remove passage) — Task 10
- [x] Public journey routes + controller — Task 11
- [x] GPX route rendering on maps — Task 12
- [x] Dashboard/overlay GPX integration — Task 13
- [x] Journey replay page with timeline — Task 14

**Placeholder scan:** No TBDs, TODOs, or vague steps found.

**Type consistency:** Verified model properties, method signatures, and prop names are consistent across tasks.
