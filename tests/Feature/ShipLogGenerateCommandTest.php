<?php

namespace Tests\Feature;

use App\Models\Journey;
use App\Models\ShipLog;
use App\Services\PrometheusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipLogGenerateCommandTest extends TestCase
{
    use RefreshDatabase;

    private function mockPrometheus(array $values = []): void
    {
        $defaults = [
            'latitude' => 50.75,
            'longitude' => -1.54,
            'gps_heading' => 274.0,
            'trip_log' => 12.3,
            'aws' => 8.5,
            'awa' => 0.78,
            'stw' => 3.1,
            'heading' => 4.78,
            'cog' => 4.78,
            'pressure' => 1013.2,
            'wp_distance' => 8.2,
            'wp_ttg' => 10800.0,
            'battery_soc' => 87.0,
            'water_level' => 62.0,
            'fuel_level' => 95.0,
        ];

        $merged = array_merge($defaults, $values);

        $mock = $this->mock(PrometheusService::class);
        $mock->shouldReceive('queryMultipleAt')
            ->andReturnUsing(function (array $queries) use ($merged) {
                $logQueries = config('scarlet.metrics.mappings.log');
                $results = [];
                foreach ($queries as $key => $promql) {
                    $logKey = array_search($promql, $logQueries);
                    if ($logKey === false) {
                        foreach ($logQueries as $lk => $lq) {
                            $stripped = preg_replace('/\bmax\(/', '(', $lq);
                            $stripped = str_replace('keep_last_value(', '(', $stripped);
                            if ($stripped === $promql) {
                                $logKey = $lk;
                                break;
                            }
                        }
                    }
                    $results[$key] = ($logKey !== false) ? $merged[$logKey] : null;
                }

                return $results;
            });
    }

    public function test_generate_creates_ship_log_entry(): void
    {
        $this->mockPrometheus();

        $this->artisan('ship-log:generate')
            ->assertSuccessful();

        $this->assertDatabaseCount('ship_logs', 1);

        $log = ShipLog::first();
        $this->assertEquals(1013.2, (float) $log->pressure);
        $this->assertEquals(50.75, (float) $log->latitude);
        $this->assertNotNull($log->wind_speed);
        $this->assertNotNull($log->wind_direction);
        $this->assertEquals(274.0, (float) $log->course);
    }

    public function test_generate_is_idempotent(): void
    {
        $this->mockPrometheus();

        $this->artisan('ship-log:generate')->assertSuccessful();
        $this->artisan('ship-log:generate')->assertSuccessful();

        $this->assertDatabaseCount('ship_logs', 1);
    }

    public function test_generate_associates_active_journey(): void
    {
        $journey = Journey::factory()->active()->create();
        $this->mockPrometheus();

        $this->artisan('ship-log:generate')->assertSuccessful();

        $log = ShipLog::first();
        $this->assertEquals($journey->id, $log->journey_id);
    }

    public function test_generate_handles_null_metrics(): void
    {
        $mock = $this->mock(PrometheusService::class);
        $mock->shouldReceive('queryMultipleAt')
            ->andReturnUsing(fn (array $queries) => array_fill_keys(array_keys($queries), null));

        $this->artisan('ship-log:generate')->assertSuccessful();

        $log = ShipLog::first();
        $this->assertNull($log->latitude);
        $this->assertNull($log->wind_speed);
        $this->assertNull($log->course);
    }

    public function test_generate_falls_back_to_signalk_cog_when_gps_heading_null(): void
    {
        $this->mockPrometheus(['gps_heading' => null, 'cog' => 4.78]);

        $this->artisan('ship-log:generate')->assertSuccessful();

        $log = ShipLog::first();
        $this->assertEqualsWithDelta(rad2deg(4.78), (float) $log->course, 0.1);
    }

    public function test_generate_filters_null_island_position(): void
    {
        $this->mockPrometheus([
            'latitude' => 0.0,
            'longitude' => 0.0,
        ]);

        $this->artisan('ship-log:generate')->assertSuccessful();

        $log = ShipLog::first();
        $this->assertNull($log->latitude);
        $this->assertNull($log->longitude);
    }

    public function test_generate_stores_valid_position(): void
    {
        $this->mockPrometheus([
            'latitude' => 43.54,
            'longitude' => 3.89,
        ]);

        $this->artisan('ship-log:generate')->assertSuccessful();

        $log = ShipLog::first();
        $this->assertEqualsWithDelta(43.54, (float) $log->latitude, 0.01);
        $this->assertEqualsWithDelta(3.89, (float) $log->longitude, 0.01);
    }
}
