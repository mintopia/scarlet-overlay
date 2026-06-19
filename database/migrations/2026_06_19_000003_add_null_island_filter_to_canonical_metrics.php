<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('canonical_metrics', function (Blueprint $table) {
            // Reject Null Island (lat/long ~ 0,0 = no GPS fix) readings at the read layer.
            $table->boolean('reject_null_island')->default(false)->after('valid_max');
        });
    }

    public function down(): void
    {
        Schema::table('canonical_metrics', function (Blueprint $table) {
            $table->dropColumn('reject_null_island');
        });
    }
};
