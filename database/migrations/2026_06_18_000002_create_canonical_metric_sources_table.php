<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('canonical_metric_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('canonical_metric_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('priority');
            $table->string('source_metric_name');
            $table->json('label_matchers')->nullable();
            $table->string('source_class')->default('both');
            $table->string('source_kind')->nullable();
            $table->string('select_fn')->default('last');
            $table->json('unit_transform')->nullable();
            $table->unsignedInteger('staleness_threshold_s')->nullable();
            $table->timestamps();

            $table->unique(['canonical_metric_id', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('canonical_metric_sources');
    }
};
