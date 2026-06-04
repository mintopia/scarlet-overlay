<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\PlanGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlannerControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->owner()->create();
    }

    public function test_planner_index_renders(): void
    {
        $response = $this->actingAs($this->user)->get('/admin/planner');
        $response->assertStatus(200);
    }

    public function test_can_create_plan(): void
    {
        $response = $this->actingAs($this->user)->post('/admin/planner', [
            'title' => 'Summer Crossing',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('plans', [
            'title' => 'Summer Crossing',
        ]);
    }

    public function test_plan_generates_unique_slug(): void
    {
        Plan::factory()->create(['title' => 'Summer Crossing', 'slug' => 'summer-crossing']);

        $this->actingAs($this->user)->post('/admin/planner', [
            'title' => 'Summer Crossing',
        ]);

        $this->assertDatabaseHas('plans', ['slug' => 'summer-crossing-2']);
    }

    public function test_can_view_plan(): void
    {
        $plan = Plan::factory()->create();
        PlanGroup::factory()->create(['plan_id' => $plan->id]);

        $response = $this->actingAs($this->user)->get("/admin/planner/{$plan->slug}");
        $response->assertStatus(200);
    }

    public function test_can_update_plan_title(): void
    {
        $plan = Plan::factory()->create();

        $response = $this->actingAs($this->user)->put("/admin/planner/{$plan->slug}", [
            'title' => 'Winter Passage',
        ]);

        $response->assertRedirect();
        $plan->refresh();
        $this->assertEquals('Winter Passage', $plan->title);
    }

    public function test_can_delete_plan(): void
    {
        $plan = Plan::factory()->create();

        $response = $this->actingAs($this->user)->delete("/admin/planner/{$plan->slug}");
        $response->assertRedirect();

        $this->assertDatabaseMissing('plans', ['id' => $plan->id]);
    }

    public function test_can_generate_share_token(): void
    {
        $plan = Plan::factory()->create();

        $response = $this->actingAs($this->user)->post("/admin/planner/{$plan->slug}/share");
        $response->assertRedirect();

        $plan->refresh();
        $this->assertNotNull($plan->share_token);
    }

    public function test_can_remove_share_token(): void
    {
        $plan = Plan::factory()->shared()->create();

        $response = $this->actingAs($this->user)->delete("/admin/planner/{$plan->slug}/share");
        $response->assertRedirect();

        $plan->refresh();
        $this->assertNull($plan->share_token);
    }

    public function test_requires_authentication(): void
    {
        $response = $this->get('/admin/planner');
        $response->assertRedirect('/login');
    }
}
