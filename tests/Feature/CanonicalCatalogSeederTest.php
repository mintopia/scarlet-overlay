<?php

namespace Tests\Feature;

use App\Services\CanonicalCatalog;
use Database\Seeders\CanonicalCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CanonicalCatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_populates_catalog_idempotently(): void
    {
        $this->seed(CanonicalCatalogSeeder::class);

        $catalog = app(CanonicalCatalog::class);
        $this->assertNotNull($catalog->definition('fuel_level'));
        $this->assertNotNull($catalog->definition('ecoflow_soc'));
        $this->assertSame(1, $catalog->version());

        $this->seed(CanonicalCatalogSeeder::class);
        $this->assertSame(1, $catalog->version());
    }
}
