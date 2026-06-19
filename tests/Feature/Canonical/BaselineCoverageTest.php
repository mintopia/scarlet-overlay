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

    public function test_nav_waypoint_keys_map_to_live_calcvalues_cluster(): void
    {
        $defs = collect(CanonicalBaseline::definitions())->keyBy('key');

        // The live SignalK series is the resolved-course cluster
        // scarlet_signalk_navigation_course_calcValues_*, NOT courseGreatCircle_*.
        $expected = [
            'vmg' => 'scarlet_signalk_navigation_course_calcValues_velocityMadeGood',
            'xte' => 'scarlet_signalk_navigation_course_calcValues_crossTrackError',
            'bearing_to_wp_true' => 'scarlet_signalk_navigation_course_calcValues_bearingTrue',
            'track_bearing_true' => 'scarlet_signalk_navigation_course_calcValues_bearingTrackTrue',
            'wp_distance' => 'scarlet_signalk_navigation_course_calcValues_distance',
            'wp_ttg' => 'scarlet_signalk_navigation_course_calcValues_timeToGo',
        ];

        foreach ($expected as $key => $metric) {
            $this->assertSame(
                $metric,
                $defs[$key]['sources'][0]['source_metric_name'],
                "Canonical key {$key} must map to the live calcValues series",
            );
        }
    }

    public function test_waypoint_keys_declare_validity_bounds(): void
    {
        $defs = collect(CanonicalBaseline::definitions())->keyBy('key');

        // These SignalK course/waypoint metrics emit fixed sentinels when no route
        // is active; bounds let the reader reject them rather than serve garbage.
        foreach (['xte', 'wp_distance', 'wp_ttg'] as $key) {
            $this->assertArrayHasKey('valid_max', $defs[$key], "Key {$key} missing valid_max");
            $this->assertNotNull($defs[$key]['valid_max'], "Key {$key} must declare a valid_max bound");
        }
    }

    public function test_waypoint_keys_are_gated_on_an_active_route(): void
    {
        $defs = collect(CanonicalBaseline::definitions())->keyBy('key');

        foreach (['vmg', 'xte', 'bearing_to_wp_true', 'track_bearing_true', 'wp_distance', 'wp_ttg'] as $key) {
            $this->assertNotEmpty(
                $defs[$key]['gate_metric_name'] ?? null,
                "Waypoint key {$key} must declare a route-active gate",
            );
        }
    }

    public function test_position_keys_present(): void
    {
        $defs = collect(CanonicalBaseline::definitions())->keyBy('key');

        $required = [
            'position_latitude', 'position_longitude', 'gps_altitude',
            'gps_satellites', 'gps_hdop', 'gps_speed', 'gps_heading',
        ];

        foreach ($required as $key) {
            $this->assertTrue($defs->has($key), "Baseline missing canonical key: {$key}");
            $this->assertNotEmpty($defs[$key]['sources'], "Key {$key} has no sources");
        }

        $this->assertTrue($defs['position_latitude']['reject_null_island'] === true, 'position_latitude must reject null island');
        $this->assertCount(3, $defs['position_latitude']['sources'], 'position_latitude must declare 3 sources');
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
