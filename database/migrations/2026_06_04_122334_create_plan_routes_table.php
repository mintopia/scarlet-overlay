<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_group_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('gpx_path');
            $table->decimal('distance_nm', 8, 1)->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->unsignedTinyInteger('color_index')->default(0);
            $table->json('track_points')->nullable();
            $table->json('waypoints')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_routes');
    }
};
