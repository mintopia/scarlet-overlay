<?php

namespace Tests\Feature;

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
}
