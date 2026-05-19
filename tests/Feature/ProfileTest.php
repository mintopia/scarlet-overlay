<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_update_profile(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->put('/admin/profile', [
            'name' => 'New Name',
            'email' => 'new@example.com',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'New Name', 'email' => 'new@example.com']);
    }

    public function test_can_change_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('oldpassword')]);

        $response = $this->actingAs($user)->put('/admin/profile/password', [
            'current_password' => 'oldpassword',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertRedirect();
        $this->assertTrue(Hash::check('newpassword123', $user->fresh()->password));
    }

    public function test_wrong_current_password_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->put('/admin/profile/password', [
            'current_password' => 'wrong',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertSessionHasErrors('current_password');
    }
}
