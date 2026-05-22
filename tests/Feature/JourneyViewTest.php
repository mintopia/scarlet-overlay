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
