<?php

namespace Tests\Unit;

use App\Services\CanonicalCatalog;
use App\Services\CanonicalReader;
use App\Services\PrometheusService;
use App\Support\DerivedMetrics;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class DerivedMetricsReaderTest extends TestCase
{
    /** Source-mapped input def in the compiled shape the reader consumes. */
    private function inputDef(string $unit, string $selector): array
    {
        return [
            'label' => $selector, 'unit' => $unit, 'volatile' => false,
            'trend_fn' => 'last', 'trend_window' => '2m',
            'staleness' => 120, 'coverage_window_seconds' => 300, 'coverage_min' => 0.5,
            'sources' => [['selector' => $selector, 'transforms' => [], 'staleness' => 120]],
        ];
    }

    private function derivedDef(string $fn, string $unit): array
    {
        return [
            'label' => $fn, 'unit' => $unit, 'volatile' => false,
            'trend_fn' => 'last', 'trend_window' => '2m',
            'staleness' => 120, 'coverage_window_seconds' => 300, 'coverage_min' => 0.5,
            'derived_fn' => $fn,
            'derived_inputs' => ['aws' => 'wind_speed_apparent', 'awa' => 'wind_angle_apparent', 'stw' => 'speed_stw', 'heading' => 'heading_true'],
            'sources' => [],
        ];
    }

    private function reader(PrometheusService $prometheus): CanonicalReader
    {
        $catalog = $this->createMock(CanonicalCatalog::class);
        $catalog->method('definition')->willReturnMap([
            ['wind_direction_true', $this->derivedDef('true_wind_direction', '°')],
            ['wind_speed_true', $this->derivedDef('true_wind_speed', 'kn')],
            ['wind_speed_apparent', $this->inputDef('kn', 'AWS')],
            ['wind_angle_apparent', $this->inputDef('°', 'AWA')],
            ['speed_stw', $this->inputDef('kn', 'STW')],
            ['heading_true', $this->inputDef('°', 'HDG')],
        ]);

        return new CanonicalReader($prometheus, $catalog);
    }

    /** aws=10kn, awa=45°, stw=5kn, heading=90° → TWS≈7.368kn, TWD≈163.73°. */
    private function freshInputs(): PrometheusService&MockObject
    {
        /** @var PrometheusService&MockObject $p */
        $p = $this->createMock(PrometheusService::class);
        $p->method('queryWithTimestamp')->willReturnMap([
            ['AWS', null, ['value' => 10.0, 'timestamp' => 1716000000, 'age' => 30]],
            ['AWA', null, ['value' => 45.0, 'timestamp' => 1716000000, 'age' => 30]],
            ['STW', null, ['value' => 5.0, 'timestamp' => 1716000000, 'age' => 30]],
            ['HDG', null, ['value' => 90.0, 'timestamp' => 1716000000, 'age' => 30]],
        ]);
        $p->method('coverageRatio')->willReturn(0.9);

        return $p;
    }

    public function test_pure_compute_matches_navigation_math(): void
    {
        $inputs = ['aws' => 10.0, 'awa' => 45.0, 'stw' => 5.0, 'heading' => 90.0];
        $this->assertEqualsWithDelta(7.368, DerivedMetrics::compute('true_wind_speed', $inputs), 0.01);
        $this->assertEqualsWithDelta(163.73, DerivedMetrics::compute('true_wind_direction', $inputs), 0.1);
    }

    public function test_reads_derived_true_wind_from_canonical_inputs(): void
    {
        $reader = $this->reader($this->freshInputs());

        $dir = $reader->read('wind_direction_true');
        $this->assertEqualsWithDelta(163.73, $dir['value'], 0.1);
        $this->assertSame('°', $dir['unit']);
        $this->assertSame('derived:true_wind_direction', $dir['resolved_source']);
        $this->assertFalse($dir['stale']);

        $spd = $reader->read('wind_speed_true');
        $this->assertEqualsWithDelta(7.368, $spd['value'], 0.01);
        $this->assertFalse($spd['stale']);
    }

    public function test_derived_is_stale_when_any_input_is_stale(): void
    {
        /** @var PrometheusService&MockObject $p */
        $p = $this->createMock(PrometheusService::class);
        // heading is old (age 9000 > 120s) — last-known but stale.
        $p->method('queryWithTimestamp')->willReturnMap([
            ['AWS', null, ['value' => 10.0, 'timestamp' => 1716000000, 'age' => 30]],
            ['AWA', null, ['value' => 45.0, 'timestamp' => 1716000000, 'age' => 30]],
            ['STW', null, ['value' => 5.0, 'timestamp' => 1716000000, 'age' => 30]],
            ['HDG', null, ['value' => 90.0, 'timestamp' => 1715990000, 'age' => 9000]],
        ]);
        $p->method('coverageRatio')->willReturn(0.9);

        $dir = $this->reader($p)->read('wind_direction_true');
        $this->assertEqualsWithDelta(163.73, $dir['value'], 0.1);
        $this->assertTrue($dir['stale'], 'derived value must be stale when any input is stale');
        $this->assertSame(9000, $dir['age'], 'age must be the oldest input age');
    }

    public function test_derived_is_null_when_any_input_is_unavailable(): void
    {
        /** @var PrometheusService&MockObject $p */
        $p = $this->createMock(PrometheusService::class);
        // STW entirely absent (null in both passes).
        $p->method('queryWithTimestamp')->willReturnMap([
            ['AWS', null, ['value' => 10.0, 'timestamp' => 1716000000, 'age' => 30]],
            ['AWA', null, ['value' => 45.0, 'timestamp' => 1716000000, 'age' => 30]],
            ['STW', null, null],
            ['HDG', null, ['value' => 90.0, 'timestamp' => 1716000000, 'age' => 30]],
        ]);
        $p->method('coverageRatio')->willReturn(0.9);

        $this->assertNull($this->reader($p)->read('wind_direction_true'));
    }
}
