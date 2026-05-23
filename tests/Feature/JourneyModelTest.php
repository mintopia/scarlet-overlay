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

        Journey::createPlanned('Cowes', 'Portsmouth');
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
