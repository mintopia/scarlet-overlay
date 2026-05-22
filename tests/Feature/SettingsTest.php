<?php

namespace Tests\Feature;

use App\Models\BoatSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_page_renders(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get('/admin/settings');
        $response->assertStatus(200);
    }

    public function test_can_save_boat_identity(): void
    {
        $user = User::factory()->owner()->create();

        $response = $this->actingAs($user)->put('/admin/settings/identity', [
            'boat_name' => 'Scarlet',
            'mmsi' => '235117890',
        ]);

        $response->assertRedirect();
        $this->assertEquals('Scarlet', BoatSetting::getValue('boat_name'));
        $this->assertEquals('235117890', BoatSetting::getValue('mmsi'));
    }

    public function test_can_save_port(): void
    {
        $user = User::factory()->owner()->create();

        $response = $this->actingAs($user)->put('/admin/settings/port', [
            'port_name' => 'Lymington Marina',
            'trip_offset' => 5.2,
        ]);

        $response->assertRedirect();
        $this->assertEquals('Lymington Marina', BoatSetting::getValue('port_name'));
        $this->assertEquals(5.2, BoatSetting::getValue('trip_offset'));
    }
}
