<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('canonical_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->string('group')->nullable();
            $table->string('storage_unit');
            $table->string('display_unit');
            $table->boolean('volatile')->default(false);
            $table->string('trend_fn')->default('median');
            $table->string('trend_window')->default('10m');
            $table->unsignedInteger('staleness_threshold_s');
            $table->unsignedInteger('coverage_window_s')->default(3600);
            $table->decimal('coverage_min', 3, 2)->default(0.50);
            $table->boolean('enabled')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('canonical_metrics');
    }
};
