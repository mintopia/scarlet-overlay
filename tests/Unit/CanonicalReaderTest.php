<?php

namespace Tests\Unit;

use App\Services\CanonicalCatalog;
use App\Services\CanonicalReader;
use App\Services\PrometheusService;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class CanonicalReaderTest extends TestCase
{
    private function reader(PrometheusService $prometheus): CanonicalReader
    {
        $catalog = $this->createMock(CanonicalCatalog::class);
        $catalog->method('definition')->willReturnMap([
            ['fuel_level', [
                'label' => 'Diesel', 'unit' => '%', 'volatile' => false,
                'trend_fn' => 'median', 'trend_window' => '10m',
                'staleness' => 3600, 'coverage_window_seconds' => 3600, 'coverage_min' => 0.5,
                'sources' => [
                    ['selector' => 'scarlet_signalk_tanks_fuel_0_currentLevel', 'transforms' => [['op' => 'multiply', 'value' => 100]], 'staleness' => 1800],
                    ['selector' => 'scarlet_mqtt_percent{topic="tanklevel"}'],
                ],
            ]],
        ]);

        return new CanonicalReader($prometheus, $catalog);
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

        $catalog = $this->createMock(CanonicalCatalog::class);
        $catalog->method('definition')->willReturn(null);

        $this->assertNull((new CanonicalReader($p, $catalog))->read('does_not_exist'));
    }

    public function test_volatile_value_is_median_but_age_is_from_raw_sample(): void
    {
        /** @var PrometheusService&MockObject $p */
        $p = $this->createMock(PrometheusService::class);
        // Latest raw sample is a slosh spike (95) at age 12; median over window is the real level (61).
        $p->method('queryWithTimestamp')->willReturn(['value' => 95.0, 'timestamp' => 1716000000, 'age' => 12]);
        $p->method('coverageRatio')->willReturn(0.9);
        $p->method('aggregateOverTime')->willReturn(61.0);

        $catalog = $this->createMock(CanonicalCatalog::class);
        $catalog->method('definition')->willReturnMap([
            ['water_fresh_level', [
                'label' => 'Fresh Water', 'unit' => '%', 'volatile' => true,
                'trend_fn' => 'median', 'trend_window' => '10m',
                'staleness' => 3600, 'coverage_window_seconds' => 3600, 'coverage_min' => 0.5,
                'sources' => [
                    ['selector' => 'scarlet_mqtt_percent{topic="watertank"}'],
                ],
            ]],
        ]);

        $result = (new CanonicalReader($p, $catalog))->read('water_fresh_level');

        $this->assertEqualsWithDelta(61.0, $result['value'], 0.001); // smoothed
        $this->assertEqualsWithDelta(95.0, $result['raw'], 0.001);   // raw last sample
        $this->assertSame(12, $result['age']);                       // age from raw sample
        $this->assertFalse($result['stale']);
    }

    public function test_applies_ordered_transforms_in_sequence(): void
    {
        $reader = new CanonicalReader(
            $this->createMock(PrometheusService::class),
            $this->createMock(CanonicalCatalog::class),
        );

        // (300 - 273.15) * 2 = 53.70
        $value = $this->invokeApplyArithmetic($reader, 300.0, [
            'transforms' => [['op' => 'subtract', 'value' => 273.15], ['op' => 'multiply', 'value' => 2]],
        ]);
        $this->assertEqualsWithDelta(53.70, $value, 0.001);
    }

    public function test_legacy_scalar_transform_keys_still_work(): void
    {
        $reader = new CanonicalReader(
            $this->createMock(PrometheusService::class),
            $this->createMock(CanonicalCatalog::class),
        );

        $value = $this->invokeApplyArithmetic($reader, 0.42, ['multiply' => 100]);
        $this->assertEqualsWithDelta(42.0, $value, 0.001);
    }

    public function test_ordered_transforms_add_and_divide_by_zero_guard(): void
    {
        $reader = new CanonicalReader(
            $this->createMock(PrometheusService::class),
            $this->createMock(CanonicalCatalog::class),
        );

        // add: 50 + 10 = 60
        $this->assertEqualsWithDelta(60.0, $this->invokeApplyArithmetic($reader, 50.0, [
            'transforms' => [['op' => 'add', 'value' => 10]],
        ]), 0.001);

        // divide by zero is guarded -> identity (value unchanged)
        $this->assertEqualsWithDelta(50.0, $this->invokeApplyArithmetic($reader, 50.0, [
            'transforms' => [['op' => 'divide', 'value' => 0]],
        ]), 0.001);
    }

    private function invokeApplyArithmetic(CanonicalReader $reader, float $value, array $source): float
    {
        $ref = new \ReflectionMethod($reader, 'applyArithmetic');
        $ref->setAccessible(true);

        return $ref->invoke($reader, $value, $source);
    }
}
