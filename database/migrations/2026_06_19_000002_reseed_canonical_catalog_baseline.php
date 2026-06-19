<?php

use App\Services\CanonicalCatalog;
use App\Support\CanonicalBaseline;
use Illuminate\Database\Migrations\Migration;

/**
 * Reseed the canonical catalog to the corrected code baseline on deploy:
 *  - 6 nav waypoint keys repointed from the non-existent courseGreatCircle_*
 *    series to the live navigation_course_calcValues_* cluster;
 *  - validity bounds added to the course/waypoint keys (sentinel rejection).
 *
 * applyBaseline() is idempotent — it no-ops (returns the current version) when the
 * snapshot already matches, and bumps the catalog version only when it differs.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Tests build catalog state explicitly via factories/applyBaseline; auto-seeding
        // here would collide with those fixtures. Production/staging reseed on migrate.
        if (app()->environment('testing')) {
            return;
        }

        app(CanonicalCatalog::class)->applyBaseline(
            CanonicalBaseline::definitions(),
            'reset',
            'migration',
            'Reseed: nav waypoint keys → course_calcValues_* + validity bounds',
        );
    }

    public function down(): void
    {
        // Catalog content is forward-only; revert via `metrics:catalog:rollback`.
    }
};
