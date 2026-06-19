<?php

namespace Tests\Feature\Canonical;

use App\Services\CanonicalCatalog;
use Tests\TestCase;

class SelectorCompileTest extends TestCase
{
    public function test_compiles_selector_with_equals_and_absent_matchers(): void
    {
        $catalog = app(CanonicalCatalog::class);

        $selector = $catalog->compileSelector([
            'source_metric_name' => 'scarlet_mqtt_percent',
            'label_matchers' => [
                ['label' => 'topic', 'op' => 'equals', 'value' => 'tanklevel'],
                ['label' => 'carrier', 'op' => 'absent', 'value' => null],
            ],
        ]);

        $this->assertSame('scarlet_mqtt_percent{topic="tanklevel",carrier=""}', $selector);
    }

    public function test_bare_metric_name_when_no_matchers(): void
    {
        $catalog = app(CanonicalCatalog::class);

        $this->assertSame(
            'scarlet_signalk_tanks_fuel_0_currentLevel',
            $catalog->compileSelector(['source_metric_name' => 'scarlet_signalk_tanks_fuel_0_currentLevel'])
        );
    }
}
