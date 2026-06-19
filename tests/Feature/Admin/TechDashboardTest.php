<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TechDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_auth(): void
    {
        $this->get(route('admin.dash.tech'))->assertRedirect(route('login'));
    }

    public function test_renders_with_contracts(): void
    {
        config()->set('scarlet.canonical.enabled', true);
        Http::fake(['*' => Http::response(['status' => 'success', 'data' => ['resultType' => 'vector', 'result' => [['metric' => [], 'value' => [now()->timestamp, '1']]]]], 200)]);

        $this->actingAs(User::factory()->create())
            ->get(route('admin.dash.tech'))
            ->assertInertia(fn (Assert $p) => $p->component('Admin/Dash/Tech')->has('contracts')->has('pullEnabled'));
    }
}
