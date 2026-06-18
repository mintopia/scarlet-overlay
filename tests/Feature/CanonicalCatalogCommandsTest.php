<?php

namespace Tests\Feature;

use App\Models\CanonicalMetric;
use App\Services\CanonicalCatalog;
use App\Support\CanonicalBaseline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CanonicalCatalogCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_reapplies_baseline(): void
    {
        $catalog = app(CanonicalCatalog::class);
        $catalog->applyBaseline(CanonicalBaseline::definitions(), 'seed', 'test');

        CanonicalMetric::where('key', 'ecoflow_soc')->delete();
        $this->assertNull($catalog->definition('ecoflow_soc'));

        $this->artisan('metrics:catalog:reset --force')->assertSuccessful();

        $this->assertNotNull(app(CanonicalCatalog::class)->definition('ecoflow_soc'));
    }

    public function test_rollback_restores_prior_version(): void
    {
        $catalog = app(CanonicalCatalog::class);
        $catalog->applyBaseline(CanonicalBaseline::definitions(), 'seed', 'test'); // v1

        $defs = CanonicalBaseline::definitions();
        foreach ($defs as &$d) {
            if ($d['key'] === 'fuel_level') {
                $d['staleness_threshold_s'] = 1200;
            }
        }
        unset($d);
        $catalog->applyBaseline($defs, 'edit', 'test'); // v2

        $this->artisan('metrics:catalog:rollback 1 --force')->assertSuccessful();

        $this->assertSame(3600, app(CanonicalCatalog::class)->definition('fuel_level')['staleness']);
    }
}
