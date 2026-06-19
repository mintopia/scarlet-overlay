<?php

namespace Tests\Feature;

use App\Jobs\ImportJourneyFromPrometheus;
use App\Models\Journey;
use App\Services\CanonicalReader;
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

        $points = [
            ['t' => now()->subHours(3)->timestamp, 'v' => 50.75],
            ['t' => now()->subHours(2)->timestamp, 'v' => 50.71],
        ];

        $mock = Mockery::mock(CanonicalReader::class);
        $mock->shouldReceive('readRange')->andReturn($points);
        $this->app->instance(CanonicalReader::class, $mock);

        $job = new ImportJourneyFromPrometheus(
            $journey->id,
            now()->subHours(3)->toIso8601String(),
            now()->toIso8601String(),
        );
        $job->handle(app(CanonicalReader::class));

        $this->assertGreaterThan(0, $journey->trackPoints()->count());
    }
}
