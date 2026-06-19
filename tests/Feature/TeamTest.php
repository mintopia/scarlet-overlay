<?php

namespace Tests\Feature;

use App\Mail\TeamInviteMail;
use App\Models\Invite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TeamTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_invite_member(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/admin/team/invite', [
            'email' => 'newcrew@example.com',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('invites', ['email' => 'newcrew@example.com']);
        Mail::assertSent(TeamInviteMail::class);
    }

    public function test_guest_cannot_invite(): void
    {
        $response = $this->post('/admin/team/invite', [
            'email' => 'another@example.com',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseMissing('invites', ['email' => 'another@example.com']);
    }

    public function test_can_register_with_valid_invite(): void
    {
        $invite = Invite::create([
            'email' => 'newcrew@example.com',
            'token' => 'valid-token',
            'invited_by' => User::factory()->create()->id,
        ]);

        $response = $this->post('/register', [
            'token' => 'valid-token',
            'name' => 'New Crew',
            'email' => 'newcrew@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect('/admin/settings');
        $this->assertDatabaseHas('users', ['email' => 'newcrew@example.com']);
        $this->assertDatabaseMissing('invites', ['token' => 'valid-token']);
    }

    public function test_authenticated_user_can_remove_member(): void
    {
        $user = User::factory()->create();
        $member = User::factory()->create();

        $response = $this->actingAs($user)->delete("/admin/team/{$member->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('users', ['id' => $member->id]);
    }
}
