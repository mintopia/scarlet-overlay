<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journey_track_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journey_id')->constrained()->cascadeOnDelete();
            $table->timestamp('recorded_at');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->float('speed_sog')->nullable();
            $table->float('speed_stw')->nullable();
            $table->float('heading')->nullable();
            $table->float('cog')->nullable();
            $table->float('depth')->nullable();
            $table->float('wind_speed_apparent')->nullable();
            $table->float('wind_angle_apparent')->nullable();
            $table->float('wind_speed_true')->nullable();
            $table->float('wind_direction_true')->nullable();
            $table->float('house_battery_voltage')->nullable();
            $table->float('house_battery_current')->nullable();
            $table->float('heel')->nullable();

            $table->index(['journey_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journey_track_points');
    }
};
