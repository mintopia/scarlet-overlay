<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class DashboardPropsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Prevent CanonicalReader and MetricsService from making real network calls.
        Http::fake(['*' => Http::response(['status' => 'success', 'data' => ['result' => []]], 200)]);
    }

    public function test_main_dashboard_renders_with_gps_prop(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.dash.main'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Dash/Main')
                ->has('gps'));
    }

    public function test_ops_dashboard_renders_with_gps_prop(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.dash.ops'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Dash/Ops')
                ->has('gps'));
    }

    public function test_skipper_dashboard_renders_with_gps_prop(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.dash.skipper'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Dash/Skipper')
                ->has('gps'));
    }

    public function test_skipper_dashboard_renders_with_fuel_history_prop(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.dash.skipper'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Dash/Skipper')
                ->has('fuelHistory'));
    }
}
