<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MetricsInventoryCommandTest extends TestCase
{
    public function test_inventory_writes_json_and_drift_report(): void
    {
        Storage::fake('local');

        Config::set('scarlet.metrics.registry', [
            'fuel_level' => ['query' => 'scarlet_signalk_tanks_fuel_currentLevel', 'multiply' => 100],
            'water_temp' => ['query' => 'scarlet_signalk_environment_water_temperature', 'subtract' => 273.15],
        ]);

        Http::fake([
            '*/api/v1/label/__name__/values*' => Http::response([
                'status' => 'success',
                'data' => ['scarlet_signalk_tanks_fuel_0_currentLevel', 'scarlet_signalk_environment_water_temperature'],
            ]),
            '*/api/v1/series*' => Http::response([
                'status' => 'success',
                'data' => [
                    ['__name__' => 'scarlet_signalk_tanks_fuel_0_currentLevel', 'job' => 'boat-tracker'],
                    ['__name__' => 'scarlet_signalk_environment_water_temperature', 'job' => 'boat-tracker'],
                ],
            ]),
        ]);

        $this->artisan('metrics:inventory')->assertSuccessful();

        $files = Storage::disk('local')->allFiles('metrics');
        $json = collect($files)->first(fn ($f) => str_contains($f, 'inventory-') && str_ends_with($f, '.json'));
        $this->assertNotNull($json, 'inventory json should be written');

        $payload = json_decode(Storage::disk('local')->get($json), true);
        $this->assertContains('scarlet_signalk_tanks_fuel_0_currentLevel', $payload['metric_names']);

        $drift = collect($files)->first(fn ($f) => str_contains($f, 'drift-'));
        $this->assertNotNull($drift);
        $this->assertStringContainsString('scarlet_signalk_tanks_fuel_currentLevel', Storage::disk('local')->get($drift));
    }
}
