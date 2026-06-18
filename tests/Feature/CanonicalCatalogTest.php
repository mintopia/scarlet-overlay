<?php

namespace Tests\Feature;

use App\Models\CanonicalCatalogVersion;
use App\Models\CanonicalMetric;
use App\Services\CanonicalCatalog;
use App\Support\CanonicalBaseline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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

    public function test_apply_baseline_seeds_and_compiles_definition(): void
    {
        $catalog = app(CanonicalCatalog::class);
        $version = $catalog->applyBaseline(CanonicalBaseline::definitions(), 'seed', 'test');

        $this->assertSame(1, $version);

        $def = $catalog->definition('fuel_level');
        $this->assertSame('%', $def['unit']);
        $this->assertTrue($def['volatile']);
        $this->assertSame(3600, $def['staleness']);
        $this->assertSame('scarlet_signalk_tanks_fuel_0_currentLevel', $def['sources'][0]['selector']);
        $this->assertSame(1800, $def['sources'][0]['staleness']);
        $this->assertSame([['op' => 'multiply', 'value' => 100]], $def['sources'][0]['transforms']);
        $this->assertSame('scarlet_mqtt_percent{topic="tanklevel"}', $def['sources'][1]['selector']);
    }

    public function test_definition_null_for_unknown_or_disabled(): void
    {
        $catalog = app(CanonicalCatalog::class);
        $catalog->applyBaseline(CanonicalBaseline::definitions(), 'seed', 'test'); // v1, cache warm

        $this->assertNull($catalog->definition('does_not_exist'));

        CanonicalMetric::create([
            'key' => 'disabled_demo', 'label' => 'Disabled', 'storage_unit' => 'x', 'display_unit' => 'x',
            'staleness_threshold_s' => 3600, 'coverage_window_s' => 3600, 'coverage_min' => 0.5, 'enabled' => false,
        ]);
        Cache::flush(); // force recompute of all()

        $this->assertNull(app(CanonicalCatalog::class)->definition('disabled_demo'));
        $this->assertNotNull(app(CanonicalCatalog::class)->definition('fuel_level'));
    }

    public function test_apply_baseline_is_idempotent_no_version_bump_when_unchanged(): void
    {
        $catalog = app(CanonicalCatalog::class);
        $v1 = $catalog->applyBaseline(CanonicalBaseline::definitions(), 'seed', 'test');
        $v2 = $catalog->applyBaseline(CanonicalBaseline::definitions(), 'seed', 'test');

        $this->assertSame($v1, $v2);
        $this->assertSame(1, CanonicalCatalogVersion::count());
    }

    public function test_mutation_bumps_version_and_busts_cache(): void
    {
        $catalog = app(CanonicalCatalog::class);
        $catalog->applyBaseline(CanonicalBaseline::definitions(), 'seed', 'test');
        $this->assertSame(3600, $catalog->definition('fuel_level')['staleness']);

        $defs = CanonicalBaseline::definitions();
        foreach ($defs as &$d) {
            if ($d['key'] === 'fuel_level') {
                $d['staleness_threshold_s'] = 1200;
            }
        }
        unset($d);
        $v2 = $catalog->applyBaseline($defs, 'edit', 'test');

        $this->assertSame(2, $v2);
        $this->assertSame(1200, $catalog->definition('fuel_level')['staleness']);
    }

    public function test_rollback_restores_prior_snapshot_as_new_version(): void
    {
        $catalog = app(CanonicalCatalog::class);
        $catalog->applyBaseline(CanonicalBaseline::definitions(), 'seed', 'test');

        $defs = CanonicalBaseline::definitions();
        foreach ($defs as &$d) {
            if ($d['key'] === 'fuel_level') {
                $d['staleness_threshold_s'] = 1200;
            }
        }
        unset($d);
        $catalog->applyBaseline($defs, 'edit', 'test');

        $v3 = $catalog->rollback(1, 'test');

        $this->assertSame(3, $v3);
        $this->assertSame(3600, $catalog->definition('fuel_level')['staleness']);
    }
}
