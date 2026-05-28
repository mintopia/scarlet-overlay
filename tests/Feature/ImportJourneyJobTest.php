<?php

namespace Tests\Feature;

use App\Jobs\ImportJourneyFromPrometheus;
use App\Models\Journey;
use App\Services\MetricRegistry;
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

        $mock = Mockery::mock(MetricRegistry::class);
        $mock->shouldReceive('fetchRange')->andReturn($timestamps);
        $this->app->instance(MetricRegistry::class, $mock);

        $job = new ImportJourneyFromPrometheus(
            $journey->id,
            now()->subHours(3)->toIso8601String(),
            now()->toIso8601String(),
        );
        $job->handle(app(MetricRegistry::class));

        $this->assertGreaterThan(0, $journey->trackPoints()->count());
    }
}
