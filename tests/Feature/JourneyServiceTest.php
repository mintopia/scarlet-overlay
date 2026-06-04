<?php

namespace Tests\Feature;

use App\Enums\JourneyStatus;
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

        Cache::put('journey.stationary_count', 479);

        $metrics = [
            'boat' => ['speed_sog' => 0.1],
            'gps' => ['latitude' => 50.75, 'longitude' => -1.54],
        ];

        $journey->trackPoints()->create([
            'recorded_at' => now()->subHours(3),
            'latitude' => 50.75,
            'longitude' => -1.54,
            'speed_sog' => 4.0,
        ]);

        $this->service->recordTrackPoint($journey, $metrics);

        $journey->refresh();
        $this->assertEquals(JourneyStatus::Completed, $journey->status);
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
        $this->assertEquals(JourneyStatus::Completed, $journey->status);
        $this->assertNotNull($journey->ended_at);
    }
}
