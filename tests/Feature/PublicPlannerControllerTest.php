<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\PlanGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPlannerControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_plan_is_accessible_with_token(): void
    {
        $plan = Plan::factory()->shared()->create();
        PlanGroup::factory()->create(['plan_id' => $plan->id]);

        $response = $this->get("/planner/{$plan->slug}?token={$plan->share_token}");
        $response->assertStatus(200);
    }

    public function test_shared_plan_requires_correct_token(): void
    {
        $plan = Plan::factory()->shared()->create();

        $response = $this->get("/planner/{$plan->slug}?token=wrong-token");
        $response->assertStatus(404);
    }

    public function test_unshared_plan_returns_404(): void
    {
        $plan = Plan::factory()->create();

        $response = $this->get("/planner/{$plan->slug}");
        $response->assertStatus(404);
    }
}
