<?php

namespace Tests\Feature\Admin;

use App\Models\CanonicalMetric;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class DataExplorerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Keep CanonicalReader from making real network calls; an empty
        // Prometheus result makes reads resolve to null/[] without error.
        Http::fake(['*' => Http::response(['status' => 'success', 'data' => ['result' => []]], 200)]);
    }

    public function test_current_endpoint_returns_value_age_shape_per_metric(): void
    {
        CanonicalMetric::create([
            'key' => 'wind_speed', 'label' => 'Wind Speed',
            'storage_unit' => 'm/s', 'display_unit' => 'kn',
            'staleness_threshold_s' => 60, 'enabled' => true,
        ]);

        $this->actingAs(User::factory()->create())
            ->getJson(route('admin.data.current'))
            ->assertOk()
            ->assertJsonStructure(['wind_speed']);
    }

    public function test_series_endpoint_returns_per_metric_shape(): void
    {
        CanonicalMetric::create([
            'key' => 'wind_speed', 'label' => 'Wind Speed',
            'storage_unit' => 'm/s', 'display_unit' => 'kn',
            'staleness_threshold_s' => 60, 'enabled' => true,
        ]);

        $this->actingAs(User::factory()->create())
            ->getJson(route('admin.data.series', ['metrics' => 'wind_speed', 'range' => '24h']))
            ->assertOk()
            ->assertJsonStructure(['wind_speed' => ['key', 'label', 'unit', 'data', 'current']])
            ->assertJsonPath('wind_speed.key', 'wind_speed');
    }

    public function test_explorer_page_renders_with_catalog(): void
    {
        CanonicalMetric::create([
            'key' => 'wind_speed', 'label' => 'Wind Speed',
            'storage_unit' => 'm/s', 'display_unit' => 'kn',
            'staleness_threshold_s' => 60, 'enabled' => true,
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('admin.data.show', ['metric' => 'wind_speed']))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Admin/MetricExplorer')
                ->where('metricKey', 'wind_speed')
                ->where('metricLabel', 'Wind Speed')
                ->has('catalog', 1));
    }

    public function test_explorer_unknown_metric_is_404(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.data.show', ['metric' => 'nope_not_here']))
            ->assertNotFound();
    }
}
