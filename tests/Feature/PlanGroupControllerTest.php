<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\PlanGroup;
use App\Models\PlanRoute;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanGroupControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->owner()->create();
        $this->plan = Plan::factory()->create(['user_id' => $this->user->id]);
    }

    public function test_can_create_group(): void
    {
        $response = $this->actingAs($this->user)->post("/admin/planner/{$this->plan->slug}/groups", [
            'name' => "Sarah's Routes",
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('plan_groups', [
            'plan_id' => $this->plan->id,
            'name' => "Sarah's Routes",
            'color_index' => 0,
        ]);
    }

    public function test_color_index_auto_increments(): void
    {
        PlanGroup::factory()->create(['plan_id' => $this->plan->id, 'color_index' => 0]);

        $this->actingAs($this->user)->post("/admin/planner/{$this->plan->slug}/groups", [
            'name' => "Zoe's Routes",
        ]);

        $this->assertDatabaseHas('plan_groups', [
            'name' => "Zoe's Routes",
            'color_index' => 1,
        ]);
    }

    public function test_can_update_group_name(): void
    {
        $group = PlanGroup::factory()->create(['plan_id' => $this->plan->id]);

        $response = $this->actingAs($this->user)->put("/admin/planner/groups/{$group->id}", [
            'name' => 'Updated Name',
        ]);

        $response->assertRedirect();
        $group->refresh();
        $this->assertEquals('Updated Name', $group->name);
    }

    public function test_can_delete_group(): void
    {
        $group = PlanGroup::factory()->create(['plan_id' => $this->plan->id]);

        $response = $this->actingAs($this->user)->delete("/admin/planner/groups/{$group->id}");
        $response->assertRedirect();

        $this->assertDatabaseMissing('plan_groups', ['id' => $group->id]);
    }

    public function test_deleting_group_cascades_routes(): void
    {
        $group = PlanGroup::factory()->create(['plan_id' => $this->plan->id]);
        PlanRoute::factory()->create(['plan_group_id' => $group->id]);

        $this->actingAs($this->user)->delete("/admin/planner/groups/{$group->id}");

        $this->assertDatabaseCount('plan_routes', 0);
    }
}
