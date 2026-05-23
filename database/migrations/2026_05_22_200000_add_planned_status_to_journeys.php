<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journeys', function (Blueprint $table) {
            $table->timestamp('started_at')->nullable()->change();
        });

        if (DB::getDriverName() === 'mysql' || DB::getDriverName() === 'mariadb') {
            DB::statement("ALTER TABLE journeys MODIFY COLUMN status ENUM('planned', 'active', 'completed', 'abandoned') NOT NULL DEFAULT 'active'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql' || DB::getDriverName() === 'mariadb') {
            DB::statement("ALTER TABLE journeys MODIFY COLUMN status ENUM('active', 'completed', 'abandoned') NOT NULL DEFAULT 'active'");
        }

        Schema::table('journeys', function (Blueprint $table) {
            $table->timestamp('started_at')->nullable(false)->change();
        });
    }
};
