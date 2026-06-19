<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
