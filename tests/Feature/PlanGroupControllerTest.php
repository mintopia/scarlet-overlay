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

    public function test_can_reorder_routes_within_group(): void
    {
        $group = PlanGroup::factory()->create(['plan_id' => $this->plan->id]);
        $routeA = PlanRoute::factory()->create(['plan_group_id' => $group->id, 'sort_order' => 0]);
        $routeB = PlanRoute::factory()->create(['plan_group_id' => $group->id, 'sort_order' => 1]);
        $routeC = PlanRoute::factory()->create(['plan_group_id' => $group->id, 'sort_order' => 2]);

        $response = $this->actingAs($this->user)->put("/admin/planner/groups/{$group->id}/reorder", [
            'route_ids' => [$routeC->id, $routeA->id, $routeB->id],
        ]);

        $response->assertRedirect();
        $this->assertEquals(0, $routeC->fresh()->sort_order);
        $this->assertEquals(1, $routeA->fresh()->sort_order);
        $this->assertEquals(2, $routeB->fresh()->sort_order);
    }

    public function test_reorder_rejects_mismatched_route_ids(): void
    {
        $group = PlanGroup::factory()->create(['plan_id' => $this->plan->id]);
        $route = PlanRoute::factory()->create(['plan_group_id' => $group->id]);

        $response = $this->actingAs($this->user)->put("/admin/planner/groups/{$group->id}/reorder", [
            'route_ids' => [$route->id, 99999],
        ]);

        $response->assertStatus(422);
    }

    public function test_reorder_rejects_incomplete_route_ids(): void
    {
        $group = PlanGroup::factory()->create(['plan_id' => $this->plan->id]);
        PlanRoute::factory()->create(['plan_group_id' => $group->id, 'sort_order' => 0]);
        $routeB = PlanRoute::factory()->create(['plan_group_id' => $group->id, 'sort_order' => 1]);

        $response = $this->actingAs($this->user)->put("/admin/planner/groups/{$group->id}/reorder", [
            'route_ids' => [$routeB->id],
        ]);

        $response->assertStatus(422);
    }
}
