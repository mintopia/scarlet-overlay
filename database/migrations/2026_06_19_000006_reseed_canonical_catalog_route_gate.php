<?php

use App\Services\CanonicalCatalog;
use App\Support\CanonicalBaseline;
use Illuminate\Database\Migrations\Migration;

/**
 * Reseed the canonical catalog so the route-active gate (gate_metric_name) added to the
 * waypoint/course keys reaches already-migrated environments. Idempotent: applyBaseline
 * no-ops when the snapshot already matches and bumps the version only when it differs.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        app(CanonicalCatalog::class)->applyBaseline(
            CanonicalBaseline::definitions(),
            'reset',
            'migration',
            'Reseed: route-active gate on waypoint/course keys',
        );
    }

    public function down(): void
    {
        // Catalog content is forward-only; revert via `metrics:catalog:rollback`.
    }
};
