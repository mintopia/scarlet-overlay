<?php

namespace Tests\Feature;

use App\Models\Journey;
use App\Models\ShipLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipLogControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->owner()->create();
    }

    public function test_log_page_renders_with_db_entries(): void
    {
        ShipLog::factory()->create(['recorded_at' => now()->subHour()->startOfHour()]);
        ShipLog::factory()->create(['recorded_at' => now()->startOfHour()]);

        $response = $this->actingAs($this->user)->get('/admin/log');
        $response->assertStatus(200);
    }

    public function test_log_page_renders_empty(): void
    {
        $response = $this->actingAs($this->user)->get('/admin/log');
        $response->assertStatus(200);
    }

    public function test_log_page_filters_by_period(): void
    {
        ShipLog::factory()->create(['recorded_at' => now()->subHours(2)->startOfHour()]);
        ShipLog::factory()->create(['recorded_at' => now()->subHours(8)->startOfHour()]);

        $response = $this->actingAs($this->user)->get('/admin/log?period=6h');
        $response->assertStatus(200);
    }

    public function test_log_page_filters_by_journey(): void
    {
        $journey = Journey::factory()->active()->create();
        ShipLog::factory()->withJourney($journey->id)->create([
            'recorded_at' => now()->subHour()->startOfHour(),
        ]);
        ShipLog::factory()->create(['recorded_at' => now()->subHours(2)->startOfHour()]);

        $response = $this->actingAs($this->user)->get('/admin/log?period=journey');
        $response->assertStatus(200);
    }

    public function test_can_update_notes(): void
    {
        $log = ShipLog::factory()->create(['recorded_at' => now()->startOfHour()]);

        $response = $this->actingAs($this->user)->patch("/admin/ship-log/{$log->id}", [
            'notes' => 'Motored through Needles Channel',
        ]);

        $response->assertRedirect();
        $log->refresh();
        $this->assertEquals('Motored through Needles Channel', $log->notes);
    }

    public function test_can_clear_notes(): void
    {
        $log = ShipLog::factory()->withNotes()->create(['recorded_at' => now()->startOfHour()]);

        $response = $this->actingAs($this->user)->patch("/admin/ship-log/{$log->id}", [
            'notes' => '',
        ]);

        $response->assertRedirect();
        $log->refresh();
        $this->assertNull($log->notes);
    }

    public function test_update_notes_requires_auth(): void
    {
        $log = ShipLog::factory()->create(['recorded_at' => now()->startOfHour()]);

        $response = $this->patch("/admin/ship-log/{$log->id}", [
            'notes' => 'Unauthorized',
        ]);

        $response->assertRedirect('/login');
    }
}
