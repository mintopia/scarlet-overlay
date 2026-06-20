<?php

namespace Tests\Feature\Admin;

use App\Models\CanonicalCatalogVersion;
use App\Models\CanonicalMetric;
use App\Models\CanonicalMetricSource;
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
        $this->get(route('admin.data.index'))->assertRedirect(route('login'));
    }

    public function test_index_lists_metrics_with_sources_and_versions(): void
    {
        $m = CanonicalMetric::create(['key' => 'fuel_level', 'label' => 'Diesel', 'storage_unit' => 'pct', 'display_unit' => '%', 'staleness_threshold_s' => 3600]);
        $m->sources()->create(['priority' => 1, 'source_metric_name' => 'scarlet_signalk_tanks_fuel_0_currentLevel']);

        CanonicalCatalogVersion::create(['version' => 1, 'action' => 'edit', 'actor' => 'alice@example.com', 'snapshot' => []]);
        CanonicalCatalogVersion::create(['version' => 2, 'action' => 'edit', 'actor' => 'bob@example.com', 'snapshot' => []]);

        $this->actingAs(User::factory()->create())
            ->get(route('admin.data.index'))
            ->assertInertia(fn (Assert $p) => $p
                ->component('Admin/Data')
                ->has('metrics', 1)
                ->where('metrics.0.key', 'fuel_level')
                ->has('metrics.0.sources', 1)
                ->has('version')
                ->has('versions', 2)
                ->has('versions.0', fn (Assert $v) => $v
                    ->has('version')
                    ->has('action')
                    ->has('actor')
                    ->etc()));
    }

    public function test_update_replaces_sources_and_records_version(): void
    {
        $metric = CanonicalMetric::create(['key' => 'fuel_level', 'label' => 'Diesel', 'storage_unit' => 'pct', 'display_unit' => '%', 'staleness_threshold_s' => 3600]);
        $metric->sources()->create(['priority' => 1, 'source_metric_name' => 'old_metric_name']);

        $this->actingAs(User::factory()->create())
            ->put(route('admin.data.update', $metric), [
                'key' => 'fuel_level', 'label' => 'Diesel Updated', 'group' => 'tank',
                'storage_unit' => 'pct', 'display_unit' => '%', 'staleness_threshold_s' => 3600,
                'sources' => [[
                    'priority' => 1, 'source_metric_name' => 'new_metric_name',
                ]],
            ])->assertRedirect();

        $this->assertDatabaseMissing('canonical_metric_sources', ['source_metric_name' => 'old_metric_name']);
        $this->assertDatabaseHas('canonical_metric_sources', ['source_metric_name' => 'new_metric_name']);
        $this->assertDatabaseHas('canonical_catalog_versions', ['action' => 'edit']);
    }

    public function test_destroy_removes_metric_and_records_delete_version(): void
    {
        $metric = CanonicalMetric::create(['key' => 'fuel_level', 'label' => 'Diesel', 'storage_unit' => 'pct', 'display_unit' => '%', 'staleness_threshold_s' => 3600]);

        $this->actingAs(User::factory()->create())
            ->delete(route('admin.data.destroy', $metric))
            ->assertRedirect();

        $this->assertDatabaseMissing('canonical_metrics', ['key' => 'fuel_level']);
        $this->assertDatabaseHas('canonical_catalog_versions', ['action' => 'delete']);
    }

    public function test_store_creates_metric_with_sources_and_bumps_version(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('admin.data.store'), [
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

    public function test_store_persists_validity_bounds_and_null_island_flag(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('admin.data.store'), [
                'key' => 'position_lat', 'label' => 'Latitude', 'group' => 'nav',
                'storage_unit' => 'deg', 'display_unit' => '°', 'staleness_threshold_s' => 120,
                'valid_min' => -90, 'valid_max' => 90, 'reject_null_island' => true,
                'sources' => [['priority' => 1, 'source_metric_name' => 'scarlet_gps_latitude_deg']],
            ])->assertRedirect();

        $this->assertDatabaseHas('canonical_metrics', [
            'key' => 'position_lat', 'valid_min' => -90, 'valid_max' => 90, 'reject_null_island' => true,
        ]);
    }

    public function test_store_persists_structured_matchers_and_transforms(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('admin.data.store'), [
                'key' => 'water_fresh_level', 'label' => 'Fresh Water', 'group' => 'tank',
                'storage_unit' => 'ratio', 'display_unit' => '%', 'staleness_threshold_s' => 3600,
                'sources' => [[
                    'priority' => 1, 'source_metric_name' => 'scarlet_mqtt_percent',
                    'label_matchers' => [['label' => 'topic', 'op' => 'equals', 'value' => 'watertank']],
                    'unit_transform' => [['op' => 'multiply', 'value' => 100]],
                ]],
            ])->assertRedirect();

        $source = CanonicalMetricSource::firstWhere('source_metric_name', 'scarlet_mqtt_percent');
        $this->assertSame([['label' => 'topic', 'op' => 'equals', 'value' => 'watertank']], $source->label_matchers);
        $this->assertSame([['op' => 'multiply', 'value' => 100]], $source->unit_transform);
    }

    public function test_store_rejects_invalid_transform_op(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson(route('admin.data.store'), [
                'key' => 'bad', 'label' => 'Bad', 'storage_unit' => 'x', 'display_unit' => 'x',
                'staleness_threshold_s' => 60,
                'sources' => [[
                    'priority' => 1, 'source_metric_name' => 'scarlet_x',
                    'unit_transform' => [['op' => 'exponentiate', 'value' => 2]],
                ]],
            ])->assertStatus(422);
    }

    public function test_inventory_returns_scarlet_series_names(): void
    {
        Http::fake(['*' => Http::response([
            'status' => 'success',
            'data' => ['scarlet_gps_latitude_deg', 'scarlet_signalk_navigation_speedOverGround', 'go_gc_duration_seconds'],
        ], 200)]);

        $this->actingAs(User::factory()->create())
            ->getJson(route('admin.data.inventory'))
            ->assertOk()
            ->assertJson(['names' => ['scarlet_gps_latitude_deg', 'scarlet_signalk_navigation_speedOverGround']]);
    }

    public function test_inventory_queries_a_wide_window_so_offline_series_are_not_mislabeled_drift(): void
    {
        // Without a time range, VictoriaMetrics only returns recently-seen
        // series, so a metric that is merely offline/stale looks absent (drift).
        // The inventory must query a wide retention window via start/end.
        Http::fake(['*' => Http::response([
            'status' => 'success',
            'data' => ['scarlet_gps_latitude_deg'],
        ], 200)]);

        $this->actingAs(User::factory()->create())
            ->getJson(route('admin.data.inventory'))
            ->assertOk();

        Http::assertSent(fn ($request) => str_contains($request->url(), '/api/v1/label/__name__/values')
            && str_contains($request->url(), 'start=')
            && str_contains($request->url(), 'end='));
    }

    public function test_test_endpoint_returns_value(): void
    {
        Http::fake(['*' => Http::response([
            'status' => 'success',
            'data' => ['resultType' => 'vector', 'result' => [['metric' => [], 'value' => [now()->timestamp, '12.8']]]],
        ], 200)]);

        $this->actingAs(User::factory()->create())
            ->postJson(route('admin.data.test'), [
                'source_metric_name' => 'scarlet_signalk_electrical_batteries_house_voltage',
            ])->assertOk()->assertJson(['ok' => true, 'value' => 12.8]);
    }
}
