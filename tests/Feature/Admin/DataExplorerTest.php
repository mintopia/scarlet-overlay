<?php

namespace Tests\Feature\Admin;

use App\Models\CanonicalMetric;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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
}
