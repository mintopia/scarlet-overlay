<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\PlanGroup;
use App\Models\PlanRoute;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PlanRouteControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Plan $plan;

    private PlanGroup $group;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->owner()->create();
        $this->plan = Plan::factory()->create(['user_id' => $this->user->id]);
        $this->group = PlanGroup::factory()->create(['plan_id' => $this->plan->id]);
    }

    public function test_can_upload_gpx_route(): void
    {
        Storage::fake();

        $gpx = UploadedFile::fake()->createWithContent(
            'cowes-cherbourg.gpx',
            '<?xml version="1.0"?><gpx version="1.1" xmlns="http://www.topografix.com/GPX/1/1"><rte><name>Cowes to Cherbourg</name><rtept lat="50.76" lon="-1.30"><name>Cowes</name></rtept><rtept lat="49.64" lon="-1.62"><name>Cherbourg</name></rtept></rte></gpx>'
        );

        $response = $this->actingAs($this->user)->post("/admin/planner/groups/{$this->group->id}/routes", [
            'gpx_files' => [$gpx],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('plan_routes', [
            'plan_group_id' => $this->group->id,
            'name' => 'Cowes to Cherbourg',
        ]);

        $route = PlanRoute::first();
        $this->assertNotNull($route->waypoints);
        $this->assertCount(2, $route->waypoints);
        $this->assertGreaterThan(0, (float) $route->distance_nm);
    }

    public function test_can_upload_multiple_gpx_files(): void
    {
        Storage::fake();

        $gpx1 = UploadedFile::fake()->createWithContent('route1.gpx', '<?xml version="1.0"?><gpx version="1.1" xmlns="http://www.topografix.com/GPX/1/1"><rte><rtept lat="50.76" lon="-1.30"><name>A</name></rtept><rtept lat="49.64" lon="-1.62"><name>B</name></rtept></rte></gpx>');
        $gpx2 = UploadedFile::fake()->createWithContent('route2.gpx', '<?xml version="1.0"?><gpx version="1.1" xmlns="http://www.topografix.com/GPX/1/1"><rte><rtept lat="50.70" lon="-1.98"><name>C</name></rtept><rtept lat="49.45" lon="-2.54"><name>D</name></rtept></rte></gpx>');

        $response = $this->actingAs($this->user)->post("/admin/planner/groups/{$this->group->id}/routes", [
            'gpx_files' => [$gpx1, $gpx2],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('plan_routes', 2);
    }

    public function test_can_toggle_route(): void
    {
        $route = PlanRoute::factory()->create(['plan_group_id' => $this->group->id, 'is_enabled' => true]);

        $response = $this->actingAs($this->user)->put("/admin/planner/routes/{$route->id}", [
            'is_enabled' => false,
        ]);

        $response->assertRedirect();
        $route->refresh();
        $this->assertFalse($route->is_enabled);
    }

    public function test_can_delete_route(): void
    {
        Storage::fake();
        $route = PlanRoute::factory()->create(['plan_group_id' => $this->group->id]);

        $response = $this->actingAs($this->user)->delete("/admin/planner/routes/{$route->id}");
        $response->assertRedirect();

        $this->assertDatabaseMissing('plan_routes', ['id' => $route->id]);
    }

    public function test_multi_route_gpx_creates_multiple_routes(): void
    {
        Storage::fake();

        $gpx = UploadedFile::fake()->createWithContent(
            'multi.gpx',
            file_get_contents(base_path('tests/fixtures/multi-route.gpx'))
        );

        $response = $this->actingAs($this->user)->post("/admin/planner/groups/{$this->group->id}/routes", [
            'gpx_files' => [$gpx],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('plan_routes', 3);

        $this->assertDatabaseHas('plan_routes', ['name' => 'Leg 1 - Lymington to Yarmouth']);
        $this->assertDatabaseHas('plan_routes', ['name' => 'Leg 2 - Yarmouth to Cowes']);
        $this->assertDatabaseHas('plan_routes', ['name' => 'Track Log']);
    }
}
