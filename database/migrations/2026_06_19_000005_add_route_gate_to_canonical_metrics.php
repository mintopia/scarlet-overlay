<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('canonical_metrics', function (Blueprint $table) {
            // Optional "gate" series: the metric only resolves while this companion
            // series is fresh (e.g. waypoint metrics gated on an active-route signal).
            $table->string('gate_metric_name')->nullable()->after('reject_null_island');
            $table->json('gate_label_matchers')->nullable()->after('gate_metric_name');
            // Gate stays OPEN only while the gate series resolves to a value <= this
            // (SignalK course series emit fresh sentinels when no route is active, so a
            // freshness check alone is insufficient — the value sanity bound is the real gate).
            $table->double('gate_max_value')->nullable()->after('gate_label_matchers');
        });
    }

    public function down(): void
    {
        Schema::table('canonical_metrics', function (Blueprint $table) {
            $table->dropColumn(['gate_metric_name', 'gate_label_matchers', 'gate_max_value']);
        });
    }
};
