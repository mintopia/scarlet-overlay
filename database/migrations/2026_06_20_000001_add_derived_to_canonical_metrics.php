<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('canonical_metrics', function (Blueprint $table) {
            // A derived metric is computed at read time from other canonical
            // metrics (ADR 0007) instead of resolving a VM source series.
            $table->string('derived_fn')->nullable()->after('description');
            $table->json('derived_inputs')->nullable()->after('derived_fn');
        });
    }

    public function down(): void
    {
        Schema::table('canonical_metrics', function (Blueprint $table) {
            $table->dropColumn(['derived_fn', 'derived_inputs']);
        });
    }
};
