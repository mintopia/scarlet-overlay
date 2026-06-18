<?php

namespace Tests\Unit;

use App\Services\CanonicalReader;
use App\Services\PrometheusService;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\MockObject\MockObject;
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

    private function reader(PrometheusService $prometheus): CanonicalReader
    {
        Config::set('scarlet.canonical.metrics.fuel_level', [
            'label' => 'Diesel', 'unit' => '%', 'volatile' => false,
            'trend_fn' => 'median', 'trend_window' => '10m',
            'staleness' => 3600, 'coverage_window_seconds' => 3600, 'coverage_min' => 0.5,
            'sources' => [
                ['selector' => 'scarlet_signalk_tanks_fuel_0_currentLevel', 'multiply' => 100, 'staleness' => 1800],
                ['selector' => 'scarlet_mqtt_percent{topic="tanklevel"}'],
            ],
        ]);

        return new CanonicalReader($prometheus);
    }

    public function test_uses_preferred_source_when_fresh_and_healthy(): void
    {
        /** @var PrometheusService&MockObject $p */
        $p = $this->createMock(PrometheusService::class);
        $p->method('queryWithTimestamp')->willReturnMap([
            ['scarlet_signalk_tanks_fuel_0_currentLevel', null, ['value' => 0.42, 'timestamp' => 1716000000, 'age' => 30]],
        ]);
        $p->method('coverageRatio')->willReturn(0.9);

        $result = $this->reader($p)->read('fuel_level');

        $this->assertEqualsWithDelta(42.0, $result['value'], 0.001);
        $this->assertSame('scarlet_signalk_tanks_fuel_0_currentLevel', $result['resolved_source']);
        $this->assertFalse($result['stale']);
    }

    public function test_falls_through_to_mqtt_when_signalk_absent(): void
    {
        /** @var PrometheusService&MockObject $p */
        $p = $this->createMock(PrometheusService::class);
        $p->method('queryWithTimestamp')->willReturnMap([
            ['scarlet_signalk_tanks_fuel_0_currentLevel', null, null],
            ['scarlet_mqtt_percent{topic="tanklevel"}', null, ['value' => 63.0, 'timestamp' => 1716000000, 'age' => 20]],
        ]);
        $p->method('coverageRatio')->willReturn(0.9);

        $result = $this->reader($p)->read('fuel_level');

        $this->assertEqualsWithDelta(63.0, $result['value'], 0.001);
        $this->assertSame('scarlet_mqtt_percent{topic="tanklevel"}', $result['resolved_source']);
        $this->assertFalse($result['stale']);
    }

    public function test_does_not_hold_stale_preferred_over_live_fallback(): void
    {
        /** @var PrometheusService&MockObject $p */
        $p = $this->createMock(PrometheusService::class);
        $p->method('queryWithTimestamp')->willReturnMap([
            ['scarlet_signalk_tanks_fuel_0_currentLevel', null, ['value' => 0.99, 'timestamp' => 1715990000, 'age' => 9000]],
            ['scarlet_mqtt_percent{topic="tanklevel"}', null, ['value' => 63.0, 'timestamp' => 1716000000, 'age' => 20]],
        ]);
        $p->method('coverageRatio')->willReturn(0.9);

        $result = $this->reader($p)->read('fuel_level');

        $this->assertSame('scarlet_mqtt_percent{topic="tanklevel"}', $result['resolved_source']);
        $this->assertEqualsWithDelta(63.0, $result['value'], 0.001);
    }

    public function test_stale_fallback_when_no_source_fresh(): void
    {
        /** @var PrometheusService&MockObject $p */
        $p = $this->createMock(PrometheusService::class);
        $p->method('queryWithTimestamp')->willReturnMap([
            ['scarlet_signalk_tanks_fuel_0_currentLevel', null, ['value' => 0.50, 'timestamp' => 1715000000, 'age' => 99999]],
            ['scarlet_mqtt_percent{topic="tanklevel"}', null, ['value' => 60.0, 'timestamp' => 1715000050, 'age' => 99000]],
        ]);
        $p->method('coverageRatio')->willReturn(0.9);

        $result = $this->reader($p)->read('fuel_level');

        $this->assertTrue($result['stale']);
        $this->assertSame('scarlet_signalk_tanks_fuel_0_currentLevel', $result['resolved_source']);
        $this->assertEqualsWithDelta(50.0, $result['value'], 0.001);
    }

    public function test_returns_null_when_no_source_has_data(): void
    {
        /** @var PrometheusService&MockObject $p */
        $p = $this->createMock(PrometheusService::class);
        $p->method('queryWithTimestamp')->willReturn(null);
        $p->method('coverageRatio')->willReturn(null);

        $this->assertNull($this->reader($p)->read('fuel_level'));
    }

    public function test_returns_null_for_unknown_key(): void
    {
        /** @var PrometheusService&MockObject $p */
        $p = $this->createMock(PrometheusService::class);

        $this->assertNull((new CanonicalReader($p))->read('does_not_exist'));
    }
}
