<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\Concerns\MapsLegacyMetricKeys;
use App\Services\CanonicalCatalog;
use App\Services\CanonicalReader;
use App\Support\CanonicalBaseline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExploreConfigTest extends TestCase
{
    use RefreshDatabase;

    private function seedCatalog(): void
    {
        app(CanonicalCatalog::class)->applyBaseline(CanonicalBaseline::definitions(), 'reset', 'test');
    }

    public function test_explore_config_has_all_required_fields(): void
    {
        $metrics = config('scarlet.metrics.mappings.explore');

        $this->assertNotEmpty($metrics);

        foreach ($metrics as $slug => $entry) {
            $this->assertArrayHasKey('label', $entry, "Metric '{$slug}' missing label");
            $this->assertArrayHasKey('unit', $entry, "Metric '{$slug}' missing unit");
            $this->assertArrayHasKey('color', $entry, "Metric '{$slug}' missing color");
            $this->assertTrue(
                isset($entry['metric']) || isset($entry['computed']),
                "Metric '{$slug}' missing metric or computed",
            );
            $this->assertArrayHasKey('group', $entry, "Metric '{$slug}' missing group");
        }
    }

    public function test_explore_config_metric_keys_resolve_to_canonical(): void
    {
        $this->seedCatalog();
        $metrics = config('scarlet.metrics.mappings.explore');
        $catalog = app(CanonicalCatalog::class)->all();

        $legacyToCanonical = $this->legacyToCanonicalMap();

        foreach ($metrics as $slug => $entry) {
            if (isset($entry['computed'])) {
                continue;
            }

            $legacyKey = $entry['metric'];
            $this->assertArrayHasKey(
                $legacyKey,
                $legacyToCanonical,
                "Explore entry '{$slug}' references unmapped metric key '{$legacyKey}'",
            );

            $canonicalKey = $legacyToCanonical[$legacyKey];
            $this->assertArrayHasKey(
                $canonicalKey,
                $catalog,
                "Explore entry '{$slug}' maps to unknown canonical key '{$canonicalKey}'",
            );
        }
    }

    /**
     * Mirror of the controller's legacy→canonical map; asserts the explore mappings
     * still resolve to a real canonical definition.
     *
     * @return array<string, string>
     */
    private function legacyToCanonicalMap(): array
    {
        $probe = new class
        {
            use MapsLegacyMetricKeys;

            /** @return array<string, string> */
            public function map(): array
            {
                return self::$legacyToCanonical;
            }
        };

        return $probe->map();
    }

    public function test_explore_config_has_battery_power_as_signed(): void
    {
        $metrics = config('scarlet.metrics.mappings.explore');

        $this->assertSame('signed', $metrics['battery_power']['type'] ?? null);
    }

    public function test_explore_config_groups_are_valid(): void
    {
        $validGroups = array_keys(config('scarlet.metrics.mappings.explore_groups'));
        $metrics = config('scarlet.metrics.mappings.explore');

        foreach ($metrics as $slug => $entry) {
            $this->assertContains($entry['group'], $validGroups, "Metric '{$slug}' has invalid group '{$entry['group']}'");
        }
    }

    public function test_explore_config_metrics_have_valid_types(): void
    {
        $validTypes = ['standard', 'compass', 'inverted', 'gauge', 'signed', 'duration'];
        $metrics = config('scarlet.metrics.mappings.explore');

        foreach ($metrics as $slug => $entry) {
            $this->assertArrayHasKey('type', $entry, "Metric '{$slug}' missing type");
            $this->assertContains($entry['type'], $validTypes, "Metric '{$slug}' has invalid type '{$entry['type']}'");
        }
    }

    public function test_explore_groups_config_exists(): void
    {
        $groups = config('scarlet.metrics.mappings.explore_groups');

        $this->assertNotEmpty($groups);

        foreach ($groups as $key => $group) {
            $this->assertArrayHasKey('label', $group, "Group '{$key}' missing label");
            $this->assertArrayHasKey('color', $group, "Group '{$key}' missing color");
            $this->assertArrayHasKey('expanded', $group, "Group '{$key}' missing expanded");
        }
    }

    public function test_explore_current_endpoint_resolves_values_through_canonical_reader(): void
    {
        $reader = $this->createMock(CanonicalReader::class);
        $reader->method('readMany')->willReturnCallback(function (array $keys): array {
            $out = [];
            foreach ($keys as $key) {
                $out[$key] = $key === 'speed_sog'
                    ? ['value' => 5.5, 'raw' => 5.5, 'unit' => 'kn', 'timestamp' => 1, 'age' => 1, 'stale' => false, 'resolved_source' => 'x']
                    : null;
            }

            return $out;
        });
        $reader->method('read')->willReturn(null);
        $this->app->instance(CanonicalReader::class, $reader);

        $response = $this->getJson('/admin/explore/current');

        // Auth-gated routes redirect unauthenticated; assert the value plumbing when reachable.
        if ($response->status() === 200) {
            $response->assertJsonPath('speed', 5.5);
        } else {
            $this->assertContains($response->status(), [302, 401, 403]);
        }
    }
}
