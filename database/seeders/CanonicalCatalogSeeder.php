<?php

namespace Database\Seeders;

use App\Services\CanonicalCatalog;
use App\Support\CanonicalBaseline;
use Illuminate\Database\Seeder;

class CanonicalCatalogSeeder extends Seeder
{
    public function run(): void
    {
        app(CanonicalCatalog::class)->applyBaseline(
            CanonicalBaseline::definitions(),
            action: 'seed',
            actor: 'seeder',
        );
    }
}
