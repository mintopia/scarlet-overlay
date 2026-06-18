<?php

namespace Tests\Unit;

use App\Support\CanonicalBaseline;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class CanonicalBaselineTest extends TestCase
{
    public function test_defines_fuel_water_and_ecoflow_with_correct_source_order(): void
    {
        Config::set('scarlet.canonical.ecoflow_serial', 'TESTSERIAL');

        $defs = collect(CanonicalBaseline::definitions())->keyBy('key');

        $this->assertTrue($defs->has('fuel_level'));
        $this->assertTrue($defs->has('water_fresh_level'));
        $this->assertTrue($defs->has('ecoflow_soc'));

        $fuel = $defs['fuel_level'];
        $this->assertSame('scarlet_signalk_tanks_fuel_0_currentLevel', $fuel['sources'][0]['source_metric_name']);
        $this->assertSame('scarlet_mqtt_percent', $fuel['sources'][1]['source_metric_name']);
        $this->assertSame('tanklevel', $fuel['sources'][1]['label_matchers'][0]['value']);
        $this->assertSame('multiply', $fuel['sources'][0]['unit_transform'][0]['op']);

        $soc = $defs['ecoflow_soc'];
        $this->assertSame('scarlet_mqtt_value', $soc['sources'][0]['source_metric_name']);
        $this->assertStringContainsString('ecoflow/TESTSERIAL_', $soc['sources'][0]['label_matchers'][0]['value']);
    }
}
