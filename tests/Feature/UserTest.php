<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_owner(): void
    {
        $user = User::factory()->create([
            'name' => 'Jessica Smith',
            'email' => 'jess@mintopia.net',
            'role' => 'owner',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'jess@mintopia.net',
            'role' => 'owner',
        ]);
        $this->assertEquals('JS', $user->initials);
    }

    public function test_can_create_crew(): void
    {
        $user = User::factory()->create(['role' => 'crew']);
        $this->assertEquals('crew', $user->role);
    }

    public function test_role_defaults_to_crew(): void
    {
        $user = User::factory()->create();
        $this->assertEquals('crew', $user->role);
    }
}
