<?php

namespace Tests\Feature\Canonical;

use App\Models\CanonicalMetric;
use App\Services\CanonicalCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CatalogEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_record_version_snapshots_and_bumps(): void
    {
        $catalog = app(CanonicalCatalog::class);
        $before = $catalog->version();

        CanonicalMetric::create([
            'key' => 'test_metric', 'label' => 'Test', 'storage_unit' => 'pct', 'display_unit' => '%',
            'staleness_threshold_s' => 600,
        ]);

        $new = $catalog->recordVersion('edit', 'admin', 'added test_metric');

        $this->assertSame($before + 1, $new);
        $this->assertDatabaseHas('canonical_catalog_versions', ['version' => $new, 'action' => 'edit']);
    }

    public function test_test_source_runs_compiled_selector(): void
    {
        Http::fake(['*' => Http::response([
            'status' => 'success',
            'data' => ['resultType' => 'vector', 'result' => [[
                'metric' => [], 'value' => [now()->timestamp, '0.42'],
            ]]],
        ], 200)]);

        $result = app(CanonicalCatalog::class)->testSource([
            'source_metric_name' => 'scarlet_mqtt_percent',
            'label_matchers' => [['label' => 'topic', 'op' => 'equals', 'value' => 'tanklevel']],
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame('scarlet_mqtt_percent{topic="tanklevel"}', $result['selector']);
        $this->assertEqualsWithDelta(0.42, $result['value'], 0.001);
    }
}
