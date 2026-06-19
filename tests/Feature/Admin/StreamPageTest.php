<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StreamPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_stream_page_renders(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.stream'))
            ->assertInertia(fn (Assert $p) => $p->component('Admin/Stream'));
    }

    public function test_stream_page_requires_auth(): void
    {
        $this->get('/admin/stream')->assertRedirect(route('login'));
    }
}
