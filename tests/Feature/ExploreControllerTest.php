<?php

namespace Tests\Feature;

use App\Models\Journey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExploreControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_explore_page_requires_auth(): void
    {
        $response = $this->get('/admin/explore?metric=battery_voltage');
        $response->assertRedirect('/login');
    }

    public function test_explore_page_shows_dashboard_without_metric(): void
    {
        $response = $this->actingAs($this->user)->get('/admin/explore');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/ExploreDashboard')
        );
    }

    public function test_explore_page_shows_dashboard_for_invalid_metric(): void
    {
        $response = $this->actingAs($this->user)->get('/admin/explore?metric=nonexistent');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/ExploreDashboard')
        );
    }

    public function test_explore_page_renders_with_valid_metric(): void
    {
        $response = $this->actingAs($this->user)->get('/admin/explore?metric=battery_voltage');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Explore')
            ->has('metric')
            ->has('metrics')
            ->has('data')
            ->has('range')
            ->has('start')
            ->has('end')
            ->has('step')
            ->has('refresh')
            ->has('passage')
        );
    }

    public function test_explore_page_accepts_range_param(): void
    {
        $response = $this->actingAs($this->user)->get('/admin/explore?metric=battery_voltage&range=1h');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('range', '1h')
        );
    }

    public function test_series_endpoint_requires_auth(): void
    {
        $response = $this->getJson('/admin/explore/series?metric=battery_voltage&start=1716400000&end=1716486400&step=300s');
        $response->assertUnauthorized();
    }

    public function test_series_endpoint_returns_json(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/admin/explore/series?metric=battery_voltage&start=1716400000&end=1716486400&step=300s');

        $response->assertOk();
        $response->assertJsonStructure([
            ['metric', 'data', 'stats' => ['current', 'min', 'max', 'avg']],
        ]);
    }

    public function test_series_endpoint_supports_batch_metrics(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/admin/explore/series?metrics=battery_voltage,battery_current&start=1716400000&end=1716486400&step=300s');

        $response->assertOk();
        $response->assertJsonCount(2);
    }

    public function test_series_endpoint_rejects_invalid_metric(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/admin/explore/series?metric=nonexistent&start=1716400000&end=1716486400&step=300s');

        $response->assertUnprocessable();
    }

    public function test_passage_data_included_when_journey_exists(): void
    {
        Journey::create([
            'from_port' => 'Lymington',
            'to_port' => 'Yarmouth',
            'status' => 'active',
            'started_at' => now()->subHours(3),
        ]);

        $response = $this->actingAs($this->user)->get('/admin/explore?metric=battery_voltage');
        $response->assertInertia(fn ($page) => $page
            ->where('passage.available', true)
            ->has('passage.start')
            ->has('passage.end')
        );
    }

    public function test_passage_unavailable_when_no_journey(): void
    {
        $response = $this->actingAs($this->user)->get('/admin/explore?metric=battery_voltage');
        $response->assertInertia(fn ($page) => $page
            ->where('passage.available', false)
        );
    }
}
