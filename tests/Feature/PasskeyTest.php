<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laragear\WebAuthn\Models\WebAuthnCredential;
use Tests\TestCase;

class PasskeyTest extends TestCase
{
    use RefreshDatabase;

    public function test_passkey_list_requires_auth(): void
    {
        $this->getJson('/passkey/list')->assertUnauthorized();
    }

    public function test_passkey_list_returns_user_credentials(): void
    {
        $user = User::factory()->create();

        WebAuthnCredential::forceCreate([
            'id' => 'test-credential-id-abc123',
            'authenticatable_type' => User::class,
            'authenticatable_id' => $user->id,
            'user_id' => fake()->uuid(),
            'alias' => 'My iPhone',
            'counter' => 0,
            'rp_id' => 'localhost',
            'origin' => 'http://localhost',
            'public_key' => encrypt('test-public-key'),
            'attestation_format' => 'none',
        ]);

        $response = $this->actingAs($user)->getJson('/passkey/list');

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment([
                'id' => 'test-credential-id-abc123',
                'alias' => 'My iPhone',
            ])
            ->assertJsonStructure([['id', 'alias', 'created_at', 'updated_at']]);
    }

    public function test_passkey_list_only_shows_own_credentials(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        WebAuthnCredential::forceCreate([
            'id' => 'other-user-credential',
            'authenticatable_type' => User::class,
            'authenticatable_id' => $other->id,
            'user_id' => fake()->uuid(),
            'alias' => 'Other Device',
            'counter' => 0,
            'rp_id' => 'localhost',
            'origin' => 'http://localhost',
            'public_key' => encrypt('test-key'),
            'attestation_format' => 'none',
        ]);

        $this->actingAs($user)->getJson('/passkey/list')
            ->assertOk()
            ->assertJsonCount(0);
    }

    public function test_passkey_destroy_removes_credential(): void
    {
        $user = User::factory()->create();

        WebAuthnCredential::forceCreate([
            'id' => 'credential-to-delete-xyz',
            'authenticatable_type' => User::class,
            'authenticatable_id' => $user->id,
            'user_id' => fake()->uuid(),
            'alias' => 'Old Device',
            'counter' => 0,
            'rp_id' => 'localhost',
            'origin' => 'http://localhost',
            'public_key' => encrypt('test-key'),
            'attestation_format' => 'none',
        ]);

        $response = $this->actingAs($user)->deleteJson('/passkey/credential-to-delete-xyz', [
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJson(['message' => 'Passkey removed.']);

        $this->assertDatabaseMissing('webauthn_credentials', ['id' => 'credential-to-delete-xyz']);
    }

    public function test_passkey_destroy_requires_password(): void
    {
        $user = User::factory()->create();

        WebAuthnCredential::forceCreate([
            'id' => 'credential-needs-password',
            'authenticatable_type' => User::class,
            'authenticatable_id' => $user->id,
            'user_id' => fake()->uuid(),
            'alias' => 'Protected',
            'counter' => 0,
            'rp_id' => 'localhost',
            'origin' => 'http://localhost',
            'public_key' => encrypt('test-key'),
            'attestation_format' => 'none',
        ]);

        $this->actingAs($user)->deleteJson('/passkey/credential-needs-password', [
            'password' => 'wrong-password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $this->assertDatabaseHas('webauthn_credentials', ['id' => 'credential-needs-password']);
    }

    public function test_passkey_destroy_rejects_missing_password(): void
    {
        $user = User::factory()->create();

        WebAuthnCredential::forceCreate([
            'id' => 'credential-no-pass',
            'authenticatable_type' => User::class,
            'authenticatable_id' => $user->id,
            'user_id' => fake()->uuid(),
            'alias' => 'No Pass',
            'counter' => 0,
            'rp_id' => 'localhost',
            'origin' => 'http://localhost',
            'public_key' => encrypt('test-key'),
            'attestation_format' => 'none',
        ]);

        $this->actingAs($user)->deleteJson('/passkey/credential-no-pass')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $this->assertDatabaseHas('webauthn_credentials', ['id' => 'credential-no-pass']);
    }

    public function test_passkey_destroy_cannot_remove_other_users_credential(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        WebAuthnCredential::forceCreate([
            'id' => 'other-user-credential-xyz',
            'authenticatable_type' => User::class,
            'authenticatable_id' => $other->id,
            'user_id' => fake()->uuid(),
            'alias' => 'Not Mine',
            'counter' => 0,
            'rp_id' => 'localhost',
            'origin' => 'http://localhost',
            'public_key' => encrypt('test-key'),
            'attestation_format' => 'none',
        ]);

        $this->actingAs($user)->deleteJson('/passkey/other-user-credential-xyz', [
            'password' => 'password',
        ]);

        $this->assertDatabaseHas('webauthn_credentials', ['id' => 'other-user-credential-xyz']);
    }

    public function test_passkey_destroy_requires_auth(): void
    {
        $this->deleteJson('/passkey/some-id')->assertUnauthorized();
    }

    public function test_passkey_register_options_requires_auth(): void
    {
        $this->postJson('/passkey/register/options')->assertUnauthorized();
    }

    public function test_passkey_register_requires_auth(): void
    {
        $this->postJson('/passkey/register')->assertUnauthorized();
    }

    public function test_passkey_login_options_available_to_guests(): void
    {
        $response = $this->postJson('/passkey/login/options');

        $response->assertOk()
            ->assertJsonStructure(['challenge', 'timeout']);
    }

    public function test_passkey_login_options_with_email(): void
    {
        $user = User::factory()->create(['email' => 'passkey@example.com']);

        WebAuthnCredential::forceCreate([
            'id' => 'user-credential-for-login',
            'authenticatable_type' => User::class,
            'authenticatable_id' => $user->id,
            'user_id' => fake()->uuid(),
            'alias' => 'Test Device',
            'counter' => 0,
            'rp_id' => 'localhost',
            'origin' => 'http://localhost',
            'public_key' => encrypt('test-key'),
            'attestation_format' => 'none',
        ]);

        $response = $this->postJson('/passkey/login/options', [
            'email' => 'passkey@example.com',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['challenge', 'timeout', 'allowCredentials']);
    }

    public function test_passkey_register_options_returns_discoverable_credential_challenge(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/passkey/register/options');

        $response->assertOk()
            ->assertJsonStructure(['challenge', 'rp', 'user', 'timeout'])
            ->assertJsonPath('authenticatorSelection.residentKey', 'required')
            ->assertJsonPath('authenticatorSelection.requireResidentKey', true);
    }
}
