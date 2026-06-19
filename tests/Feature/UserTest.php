<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_user(): void
    {
        $user = User::factory()->create([
            'name' => 'Jessica Smith',
            'email' => 'jess@mintopia.net',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'jess@mintopia.net',
        ]);
        $this->assertEquals('JS', $user->initials);
    }
}
