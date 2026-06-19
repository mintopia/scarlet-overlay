<?php

namespace Tests\Feature;

use App\Services\CanonicalCatalog;
use App\Support\CanonicalBaseline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MetricsInventoryCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_writes_json_and_drift_report(): void
    {
        Storage::fake('local');

        app(CanonicalCatalog::class)->applyBaseline(CanonicalBaseline::definitions(), 'reset', 'test');

        // Live VM exposes the renamed fuel series but NOT the canonical fuel source's
        // base metric (scarlet_signalk_tanks_fuel_0_currentLevel) → drift must flag it.
        Http::fake([
            '*/api/v1/label/__name__/values*' => Http::response([
                'status' => 'success',
                'data' => ['scarlet_signalk_tanks_fuel_renamed_currentLevel', 'scarlet_signalk_environment_water_temperature'],
            ]),
            '*/api/v1/series*' => Http::response([
                'status' => 'success',
                'data' => [
                    ['__name__' => 'scarlet_signalk_tanks_fuel_renamed_currentLevel', 'job' => 'boat-tracker'],
                    ['__name__' => 'scarlet_signalk_environment_water_temperature', 'job' => 'boat-tracker'],
                ],
            ]),
        ]);

        $this->artisan('metrics:inventory')->assertSuccessful();

        $files = Storage::disk('local')->allFiles('metrics');
        $json = collect($files)->first(fn ($f) => str_contains($f, 'inventory-') && str_ends_with($f, '.json'));
        $this->assertNotNull($json, 'inventory json should be written');

        $payload = json_decode(Storage::disk('local')->get($json), true);
        $this->assertContains('scarlet_signalk_tanks_fuel_renamed_currentLevel', $payload['metric_names']);

        $drift = collect($files)->first(fn ($f) => str_contains($f, 'drift-'));
        $this->assertNotNull($drift);
        // The canonical fuel_level source base metric is absent from live → reported as drift.
        $this->assertStringContainsString('scarlet_signalk_tanks_fuel_0_currentLevel', Storage::disk('local')->get($drift));
    }
}
