<?php

namespace Tests\Feature;

use App\Models\Journey;
use App\Models\ShipLog;
use App\Services\MetricRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipLogGenerateCommandTest extends TestCase
{
    use RefreshDatabase;

    private function mockRegistry(array $values = []): void
    {
        $defaults = [
            'track_latitude' => 50.75,
            'track_longitude' => -1.54,
            'trip_log' => 12.3,
            'wind_speed_apparent_raw' => 8.5,
            'wind_angle_apparent_raw' => 0.78,
            'speed_stw_raw' => 3.1,
            'heading_raw' => 4.78,
            'cog_raw' => 4.78,
            'cabin_pressure_forepeak' => 1013.2,
            'nav_wp_distance' => 8.2,
            'nav_wp_ttg' => 10800.0,
            'house_battery_soc' => 87.0,
            'water_level' => 62.0,
            'fuel_level' => 95.0,
        ];

        $merged = array_merge($defaults, $values);

        $mock = $this->mock(MetricRegistry::class);
        $mock->shouldReceive('groupKeys')
            ->with('log')
            ->andReturn(config('scarlet.metrics.groups.log'));
        $mock->shouldReceive('fetchInstant')
            ->andReturnUsing(function (array $keys) use ($merged) {
                $result = [];
                foreach ($keys as $key) {
                    $result[$key] = $merged[$key] ?? null;
                }

                return $result;
            });
    }

    public function test_generate_creates_ship_log_entry(): void
    {
        $this->mockRegistry();

        $this->artisan('ship-log:generate')
            ->assertSuccessful();

        $this->assertDatabaseCount('ship_logs', 1);

        $log = ShipLog::first();
        $this->assertEquals(1013.2, (float) $log->pressure);
        $this->assertEquals(50.75, (float) $log->latitude);
        $this->assertNotNull($log->wind_speed);
        $this->assertNotNull($log->wind_direction);
        $this->assertEqualsWithDelta(rad2deg(4.78), (float) $log->course, 0.1);
    }

    public function test_generate_is_idempotent(): void
    {
        $this->mockRegistry();

        $this->artisan('ship-log:generate')->assertSuccessful();
        $this->artisan('ship-log:generate')->assertSuccessful();

        $this->assertDatabaseCount('ship_logs', 1);
    }

    public function test_generate_associates_active_journey(): void
    {
        $journey = Journey::factory()->active()->create();
        $this->mockRegistry();

        $this->artisan('ship-log:generate')->assertSuccessful();

        $log = ShipLog::first();
        $this->assertEquals($journey->id, $log->journey_id);
    }

    public function test_generate_handles_null_metrics(): void
    {
        $mock = $this->mock(MetricRegistry::class);
        $mock->shouldReceive('groupKeys')
            ->with('log')
            ->andReturn(config('scarlet.metrics.groups.log'));
        $mock->shouldReceive('fetchInstant')
            ->andReturnUsing(fn (array $keys) => array_fill_keys($keys, null));

        $this->artisan('ship-log:generate')->assertSuccessful();

        $log = ShipLog::first();
        $this->assertNull($log->latitude);
        $this->assertNull($log->wind_speed);
        $this->assertNull($log->course);
    }

    public function test_generate_falls_back_to_heading_when_cog_null(): void
    {
        $this->mockRegistry(['cog_raw' => null, 'heading_raw' => 4.78]);

        $this->artisan('ship-log:generate')->assertSuccessful();

        $log = ShipLog::first();
        $this->assertEqualsWithDelta(rad2deg(4.78), (float) $log->course, 0.1);
    }

    public function test_generate_filters_null_island_position(): void
    {
        $this->mockRegistry([
            'track_latitude' => 0.0,
            'track_longitude' => 0.0,
        ]);

        $this->artisan('ship-log:generate')->assertSuccessful();

        $log = ShipLog::first();
        $this->assertNull($log->latitude);
        $this->assertNull($log->longitude);
    }

    public function test_generate_stores_valid_position(): void
    {
        $this->mockRegistry([
            'track_latitude' => 43.54,
            'track_longitude' => 3.89,
        ]);

        $this->artisan('ship-log:generate')->assertSuccessful();

        $log = ShipLog::first();
        $this->assertEqualsWithDelta(43.54, (float) $log->latitude, 0.01);
        $this->assertEqualsWithDelta(3.89, (float) $log->longitude, 0.01);
    }
}
