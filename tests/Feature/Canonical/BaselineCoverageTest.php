<?php

namespace Tests\Feature\Canonical;

use App\Services\CanonicalCatalog;
use App\Support\CanonicalBaseline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BaselineCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_baseline_covers_every_dashboard_key(): void
    {
        $defs = collect(CanonicalBaseline::definitions())->keyBy('key');

        $required = [
            'speed_sog', 'speed_stw', 'vmg', 'heading_true', 'cog', 'depth_below_surface',
            'xte', 'wp_ttg', 'current_set_true', 'rudder_angle', 'autopilot_state',
            'wind_speed_apparent',
            'house_battery_soc', 'engine_battery_voltage',
            'ecoflow_soc', 'ecoflow_input_watts', 'ecoflow_output_watts',
            'fuel_level', 'water_fresh_level',
            'cabin_temp_forepeak', 'cabin_humidity_main', 'water_temp',
            'wx_air_temp', 'sea_wave_height', 'sea_wave_period',
            'tracker_battery_voltage', 'tracker_lte_rssi', 'tracker_mode', 'tracker_uptime',
            'srt_up', 'srt_pub_bitrate', 'srt_pub_dropped', 'srt_con_rtt',
        ];

        foreach ($required as $key) {
            $this->assertTrue($defs->has($key), "Baseline missing canonical key: {$key}");
            $this->assertNotEmpty($defs[$key]['sources'], "Key {$key} has no sources");
        }
    }

    public function test_baseline_applies_cleanly(): void
    {
        $version = app(CanonicalCatalog::class)->applyBaseline(
            CanonicalBaseline::definitions(), 'reset', 'test'
        );
        $this->assertGreaterThan(0, $version);
        $this->assertDatabaseCount('canonical_metrics', count(CanonicalBaseline::definitions()));
    }
}
