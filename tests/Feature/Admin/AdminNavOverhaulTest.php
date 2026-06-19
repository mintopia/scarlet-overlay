<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AdminNavOverhaulTest extends TestCase
{
    use RefreshDatabase;

    public function test_tracker_route_is_removed(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin/tracker')
            ->assertNotFound();
    }

    public function test_broadcast_page_route_is_removed(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin/broadcast')
            ->assertNotFound();
    }

    public function test_broadcast_pull_endpoint_still_exists(): void
    {
        // Pull is POST-only; a GET should be 405 (route exists), not 404.
        $this->actingAs(User::factory()->create())
            ->get('/admin/broadcast/pull')
            ->assertStatus(405);
    }

    public function test_environment_and_explore_routes_are_removed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin/environment')->assertNotFound();
        $this->actingAs($user)->get('/admin/weather')->assertNotFound();
        $this->actingAs($user)->get('/admin/explore')->assertNotFound();
    }

    public function test_old_catalog_path_is_removed(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin/metrics/catalog')
            ->assertNotFound();
    }

    public function test_ops_dashboard_still_renders(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.dash.ops'))
            ->assertInertia(fn (AssertableInertia $p) => $p->component('Admin/Dash/Ops'));
    }
}
