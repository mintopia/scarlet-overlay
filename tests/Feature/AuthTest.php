<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
    }

    public function test_user_can_login(): void
    {
        $user = User::factory()->create([
            'email' => 'jess@mintopia.net',
        ]);

        $response = $this->post('/login', [
            'email' => 'jess@mintopia.net',
            'password' => 'password',
        ]);

        $response->assertRedirect('/admin/settings');
        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_credentials_rejected(): void
    {
        User::factory()->create(['email' => 'jess@mintopia.net']);

        $response = $this->post('/login', [
            'email' => 'jess@mintopia.net',
            'password' => 'wrong',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->post('/logout');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_admin_requires_auth(): void
    {
        $response = $this->get('/admin/settings');
        $response->assertRedirect('/login');
    }
}
