<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ship_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journey_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('recorded_at');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('course', 5, 1)->nullable();
            $table->decimal('trip_log', 8, 1)->nullable();
            $table->decimal('wind_speed', 5, 1)->nullable();
            $table->decimal('wind_direction', 5, 1)->nullable();
            $table->decimal('pressure', 6, 1)->nullable();
            $table->decimal('wp_distance', 8, 1)->nullable();
            $table->decimal('wp_ttg', 10, 0)->nullable();
            $table->decimal('battery_soc', 5, 1)->nullable();
            $table->decimal('water_level', 5, 1)->nullable();
            $table->decimal('fuel_level', 5, 1)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('recorded_at');
            $table->index('journey_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ship_logs');
    }
};
