<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('canonical_metrics', function (Blueprint $table) {
            $table->double('valid_min')->nullable()->after('coverage_min');
            $table->double('valid_max')->nullable()->after('valid_min');
        });
    }

    public function down(): void
    {
        Schema::table('canonical_metrics', function (Blueprint $table) {
            $table->dropColumn(['valid_min', 'valid_max']);
        });
    }
};
