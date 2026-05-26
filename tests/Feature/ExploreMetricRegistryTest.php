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
}
