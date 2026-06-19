<?php

namespace Tests\Feature;

use App\Models\Journey;
use App\Models\ShipLog;
use App\Services\CanonicalReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipLogGenerateCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Mock the canonical reader's readAt() to return canonical display-unit values
     * for the ship-log fields. Keys are canonical catalog keys.
     *
     * @param  array<string, ?float>  $values
     */
    private function mockReader(array $values = []): void
    {
        // Defaults in canonical display units: angles/headings in degrees, speeds in knots.
        $defaults = [
            'position_latitude' => 50.75,
            'position_longitude' => -1.54,
            'trip_log' => 12.3,
            'wind_speed_apparent' => 16.52,   // ~8.5 m/s in kn
            'wind_angle_apparent' => 44.69,   // ~0.78 rad in deg
            'speed_stw' => 6.03,              // ~3.1 m/s in kn
            'heading_true' => 273.87,         // ~4.78 rad in deg
            'cog' => 273.87,                  // ~4.78 rad in deg
            'cabin_pressure_forepeak' => 1013.2,
            'wp_distance' => 8.2,
            'wp_ttg' => 10800.0,
            'house_battery_soc' => 87.0,
            'water_fresh_level' => 62.0,
            'fuel_level' => 95.0,
        ];

        $merged = array_merge($defaults, $values);

        $reader = $this->createMock(CanonicalReader::class);
        $reader->method('readAt')->willReturnCallback(function (string $key) use ($merged): ?array {
            $value = $merged[$key] ?? null;
            if ($value === null) {
                return null;
            }

            return ['value' => $value, 'raw' => $value, 'unit' => '', 'timestamp' => 0, 'age' => 0, 'stale' => false, 'resolved_source' => 'x'];
        });
        $this->app->instance(CanonicalReader::class, $reader);
    }

    public function test_generate_creates_ship_log_entry(): void
    {
        $this->mockReader();

        $this->artisan('ship-log:generate')
            ->assertSuccessful();

        $this->assertDatabaseCount('ship_logs', 1);

        $log = ShipLog::first();
        $this->assertEquals(1013.2, (float) $log->pressure);
        $this->assertEquals(50.75, (float) $log->latitude);
        $this->assertNotNull($log->wind_speed);
        $this->assertNotNull($log->wind_direction);
        $this->assertEqualsWithDelta(273.87, (float) $log->course, 0.1);
    }

    public function test_generate_is_idempotent(): void
    {
        $this->mockReader();

        $this->artisan('ship-log:generate')->assertSuccessful();
        $this->artisan('ship-log:generate')->assertSuccessful();

        $this->assertDatabaseCount('ship_logs', 1);
    }

    public function test_generate_associates_active_journey(): void
    {
        $journey = Journey::factory()->active()->create();
        $this->mockReader();

        $this->artisan('ship-log:generate')->assertSuccessful();

        $log = ShipLog::first();
        $this->assertEquals($journey->id, $log->journey_id);
    }

    public function test_generate_handles_null_metrics(): void
    {
        $reader = $this->createMock(CanonicalReader::class);
        $reader->method('readAt')->willReturn(null);
        $this->app->instance(CanonicalReader::class, $reader);

        $this->artisan('ship-log:generate')->assertSuccessful();

        $log = ShipLog::first();
        $this->assertNull($log->latitude);
        $this->assertNull($log->wind_speed);
        $this->assertNull($log->course);
    }

    public function test_generate_falls_back_to_heading_when_cog_null(): void
    {
        $this->mockReader(['cog' => null, 'heading_true' => 273.87]);

        $this->artisan('ship-log:generate')->assertSuccessful();

        $log = ShipLog::first();
        $this->assertEqualsWithDelta(273.87, (float) $log->course, 0.1);
    }

    public function test_generate_filters_null_island_position(): void
    {
        $this->mockReader([
            'position_latitude' => 0.0,
            'position_longitude' => 0.0,
        ]);

        $this->artisan('ship-log:generate')->assertSuccessful();

        $log = ShipLog::first();
        $this->assertNull($log->latitude);
        $this->assertNull($log->longitude);
    }

    public function test_generate_stores_valid_position(): void
    {
        $this->mockReader([
            'position_latitude' => 43.54,
            'position_longitude' => 3.89,
        ]);

        $this->artisan('ship-log:generate')->assertSuccessful();

        $log = ShipLog::first();
        $this->assertEqualsWithDelta(43.54, (float) $log->latitude, 0.01);
        $this->assertEqualsWithDelta(3.89, (float) $log->longitude, 0.01);
    }
}
