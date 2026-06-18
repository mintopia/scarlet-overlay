<?php

namespace Tests\Feature;

use App\Models\CanonicalMetric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CanonicalCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_tables_exist_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('canonical_metrics'));
        $this->assertTrue(Schema::hasTable('canonical_metric_sources'));
        $this->assertTrue(Schema::hasTable('canonical_catalog_versions'));

        $this->assertTrue(Schema::hasColumns('canonical_metrics', [
            'key', 'label', 'group', 'storage_unit', 'display_unit', 'volatile',
            'trend_fn', 'trend_window', 'staleness_threshold_s', 'coverage_window_s',
            'coverage_min', 'enabled', 'description',
        ]));
        $this->assertTrue(Schema::hasColumns('canonical_metric_sources', [
            'canonical_metric_id', 'priority', 'source_metric_name', 'label_matchers',
            'source_class', 'source_kind', 'select_fn', 'unit_transform', 'staleness_threshold_s',
        ]));
        $this->assertTrue(Schema::hasColumns('canonical_catalog_versions', [
            'version', 'action', 'actor', 'note', 'snapshot',
        ]));
    }

    public function test_metric_has_sources_and_casts_json_and_bool(): void
    {
        $metric = CanonicalMetric::create([
            'key' => 'fuel_level', 'label' => 'Diesel', 'group' => 'tank',
            'storage_unit' => 'pct', 'display_unit' => '%', 'volatile' => true,
            'trend_fn' => 'median', 'trend_window' => '10m',
            'staleness_threshold_s' => 3600, 'coverage_window_s' => 3600, 'coverage_min' => 0.5,
            'enabled' => true,
        ]);

        $metric->sources()->create([
            'priority' => 1,
            'source_metric_name' => 'scarlet_mqtt_percent',
            'label_matchers' => [['label' => 'topic', 'op' => 'equals', 'value' => 'tanklevel']],
            'source_class' => 'both',
            'select_fn' => 'last',
            'unit_transform' => [['op' => 'multiply', 'value' => 100]],
        ]);

        $fresh = CanonicalMetric::with('sources')->where('key', 'fuel_level')->first();

        $this->assertTrue($fresh->volatile);
        $this->assertSame(0.5, (float) $fresh->coverage_min);
        $this->assertCount(1, $fresh->sources);
        $this->assertSame('topic', $fresh->sources[0]->label_matchers[0]['label']);
        $this->assertSame('multiply', $fresh->sources[0]->unit_transform[0]['op']);
    }
}
