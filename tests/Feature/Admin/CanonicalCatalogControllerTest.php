<?php

namespace Tests\Feature\Admin;

use App\Models\CanonicalMetric;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CanonicalCatalogControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_auth(): void
    {
        $this->get(route('admin.catalog'))->assertRedirect(route('login'));
    }

    public function test_index_lists_metrics_with_sources_and_versions(): void
    {
        $m = CanonicalMetric::create(['key' => 'fuel_level', 'label' => 'Diesel', 'storage_unit' => 'pct', 'display_unit' => '%', 'staleness_threshold_s' => 3600]);
        $m->sources()->create(['priority' => 1, 'source_metric_name' => 'scarlet_signalk_tanks_fuel_0_currentLevel']);

        $this->actingAs(User::factory()->create())
            ->get(route('admin.catalog'))
            ->assertInertia(fn (Assert $p) => $p
                ->component('Admin/Catalog')
                ->has('metrics', 1)
                ->where('metrics.0.key', 'fuel_level')
                ->has('metrics.0.sources', 1)
                ->has('version'));
    }

    public function test_store_creates_metric_with_sources_and_bumps_version(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('admin.catalog.store'), [
                'key' => 'water_fresh_level', 'label' => 'Fresh Water', 'group' => 'tank',
                'storage_unit' => 'pct', 'display_unit' => '%', 'staleness_threshold_s' => 3600,
                'volatile' => true, 'trend_fn' => 'median', 'trend_window' => '10m',
                'coverage_window_s' => 3600, 'coverage_min' => 0.5, 'enabled' => true,
                'sources' => [[
                    'priority' => 1, 'source_metric_name' => 'scarlet_signalk_tanks_freshWater_0_currentLevel',
                    'unit_transform' => [['op' => 'multiply', 'value' => 100]],
                ]],
            ])->assertRedirect();

        $this->assertDatabaseHas('canonical_metrics', ['key' => 'water_fresh_level']);
        $this->assertDatabaseHas('canonical_metric_sources', ['source_metric_name' => 'scarlet_signalk_tanks_freshWater_0_currentLevel']);
        $this->assertDatabaseHas('canonical_catalog_versions', ['action' => 'edit']);
    }

    public function test_test_endpoint_returns_value(): void
    {
        Http::fake(['*' => Http::response([
            'status' => 'success',
            'data' => ['resultType' => 'vector', 'result' => [['metric' => [], 'value' => [now()->timestamp, '12.8']]]],
        ], 200)]);

        $this->actingAs(User::factory()->create())
            ->postJson(route('admin.catalog.test'), [
                'source_metric_name' => 'scarlet_signalk_electrical_batteries_house_voltage',
            ])->assertOk()->assertJson(['ok' => true, 'value' => 12.8]);
    }
}
