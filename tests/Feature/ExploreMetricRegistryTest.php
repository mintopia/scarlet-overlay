<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExploreMetricRegistryTest extends TestCase
{
    public function test_explore_config_has_all_required_fields(): void
    {
        $metrics = config('scarlet.metrics.mappings.explore');

        $this->assertNotEmpty($metrics);

        foreach ($metrics as $slug => $entry) {
            $this->assertArrayHasKey('label', $entry, "Metric '{$slug}' missing label");
            $this->assertArrayHasKey('unit', $entry, "Metric '{$slug}' missing unit");
            $this->assertArrayHasKey('color', $entry, "Metric '{$slug}' missing color");
            $this->assertTrue(
                isset($entry['query']) || isset($entry['computed']),
                "Metric '{$slug}' missing query or computed",
            );
            $this->assertArrayHasKey('group', $entry, "Metric '{$slug}' missing group");
        }
    }

    public function test_explore_config_has_battery_power_as_signed(): void
    {
        $metrics = config('scarlet.metrics.mappings.explore');

        $this->assertTrue($metrics['battery_power']['signed'] ?? false);
    }

    public function test_explore_config_groups_are_valid(): void
    {
        $validGroups = ['navigation', 'wind', 'power', 'cabin', 'tanks', 'tracker'];
        $metrics = config('scarlet.metrics.mappings.explore');

        foreach ($metrics as $slug => $entry) {
            $this->assertContains($entry['group'], $validGroups, "Metric '{$slug}' has invalid group '{$entry['group']}'");
        }
    }
}
