<?php

namespace Tests\Unit;

use Tests\TestCase;

class CanonicalReaderTest extends TestCase
{
    public function test_baseline_defines_fuel_and_water_chains(): void
    {
        $metrics = config('scarlet.canonical.metrics');

        $this->assertArrayHasKey('fuel_level', $metrics);
        $this->assertArrayHasKey('water_fresh_level', $metrics);

        $fuelSources = array_column($metrics['fuel_level']['sources'], 'selector');
        $this->assertSame('scarlet_signalk_tanks_fuel_0_currentLevel', $fuelSources[0]);
        $this->assertSame('scarlet_mqtt_percent{topic="tanklevel"}', $fuelSources[1]);

        $this->assertTrue($metrics['fuel_level']['volatile']);
        $this->assertFalse(config('scarlet.canonical.enabled'));
    }
}
