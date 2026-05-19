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

    public function test_owner_can_invite_member(): void
    {
        Mail::fake();
        $owner = User::factory()->owner()->create();

        $response = $this->actingAs($owner)->post('/admin/team/invite', [
            'email' => 'newcrew@example.com',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('invites', ['email' => 'newcrew@example.com']);
        Mail::assertSent(TeamInviteMail::class);
    }

    public function test_crew_cannot_invite(): void
    {
        $crew = User::factory()->create(['role' => 'crew']);

        $response = $this->actingAs($crew)->post('/admin/team/invite', [
            'email' => 'another@example.com',
        ]);

        $response->assertForbidden();
    }

    public function test_can_register_with_valid_invite(): void
    {
        $invite = Invite::create([
            'email' => 'newcrew@example.com',
            'token' => 'valid-token',
            'invited_by' => User::factory()->owner()->create()->id,
        ]);

        $response = $this->post('/register', [
            'token' => 'valid-token',
            'name' => 'New Crew',
            'email' => 'newcrew@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect('/admin/settings');
        $this->assertDatabaseHas('users', ['email' => 'newcrew@example.com', 'role' => 'crew']);
        $this->assertDatabaseMissing('invites', ['token' => 'valid-token']);
    }

    public function test_owner_can_remove_crew(): void
    {
        $owner = User::factory()->owner()->create();
        $crew = User::factory()->create(['role' => 'crew']);

        $response = $this->actingAs($owner)->delete("/admin/team/{$crew->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('users', ['id' => $crew->id]);
    }
}
