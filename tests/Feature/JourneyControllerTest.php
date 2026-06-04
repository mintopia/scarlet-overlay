<?php

namespace Tests\Feature;

use App\Enums\JourneyStatus;
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
            'status' => 'planned',
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
        $this->assertEquals(JourneyStatus::Completed, $journey->status);
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
