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

    public function test_pure_compute_multiply_and_subtract(): void
    {
        // House battery power = voltage × current (positive current = charging).
        $this->assertEqualsWithDelta(74.4, DerivedMetrics::compute('multiply', ['a' => 12.4, 'b' => 6.0]), 0.001);
        // EcoFlow net = input − output (positive = net charging).
        $this->assertEqualsWithDelta(-150.0, DerivedMetrics::compute('subtract', ['a' => 50.0, 'b' => 200.0]), 0.001);
        // A missing or null input yields null, never a silent 0.
        $this->assertNull(DerivedMetrics::compute('multiply', ['a' => null, 'b' => 6.0]));
        $this->assertNull(DerivedMetrics::compute('subtract', ['a' => 1.0]));
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

    public function test_derived_range_combines_inputs_point_by_point(): void
    {
        // ADR 0007: derived history is computed point-by-point over the inputs' series.
        $catalog = $this->createMock(CanonicalCatalog::class);
        $catalog->method('definition')->willReturnMap([
            ['house_battery_power', [
                'label' => 'House Battery Power', 'unit' => 'W', 'volatile' => false,
                'trend_fn' => 'last', 'trend_window' => '2m',
                'staleness' => 300, 'coverage_window_seconds' => 600, 'coverage_min' => 0.5,
                'derived_fn' => 'multiply',
                'derived_inputs' => ['a' => 'house_battery_voltage', 'b' => 'house_battery_current'],
                'sources' => [],
            ]],
            ['house_battery_voltage', $this->inputDef('V', 'VOLT')],
            ['house_battery_current', $this->inputDef('A', 'CURR')],
        ]);

        /** @var PrometheusService&MockObject $p */
        $p = $this->createMock(PrometheusService::class);
        $p->method('queryRange')->willReturnMap([
            ['VOLT', null, '300s', null, null, false, [['timestamp' => 1000, 'value' => 12.0], ['timestamp' => 1300, 'value' => 13.0]]],
            ['CURR', null, '300s', null, null, false, [['timestamp' => 1000, 'value' => 5.0], ['timestamp' => 1300, 'value' => -2.0]]],
        ]);

        $series = (new CanonicalReader($p, $catalog))->readRange('house_battery_power', null, '300s');

        $this->assertCount(2, $series);
        $this->assertSame(1000, $series[0]['t']);
        $this->assertEqualsWithDelta(60.0, $series[0]['v'], 0.001);   // 12 × 5 (charging)
        $this->assertSame(1300, $series[1]['t']);
        $this->assertEqualsWithDelta(-26.0, $series[1]['v'], 0.001);  // 13 × −2 (discharging)
    }

    public function test_derived_range_skips_timestamps_missing_an_input(): void
    {
        $catalog = $this->createMock(CanonicalCatalog::class);
        $catalog->method('definition')->willReturnMap([
            ['ecoflow_net_watts', [
                'label' => 'EcoFlow Net', 'unit' => 'W', 'volatile' => false,
                'trend_fn' => 'last', 'trend_window' => '2m',
                'staleness' => 3600, 'coverage_window_seconds' => 3600, 'coverage_min' => 0.3,
                'derived_fn' => 'subtract',
                'derived_inputs' => ['a' => 'ecoflow_input_watts', 'b' => 'ecoflow_output_watts'],
                'sources' => [],
            ]],
            ['ecoflow_input_watts', $this->inputDef('W', 'IN')],
            ['ecoflow_output_watts', $this->inputDef('W', 'OUT')],
        ]);

        /** @var PrometheusService&MockObject $p */
        $p = $this->createMock(PrometheusService::class);
        $p->method('queryRange')->willReturnMap([
            ['IN', null, '300s', null, null, false, [['timestamp' => 1000, 'value' => 200.0], ['timestamp' => 1300, 'value' => 100.0]]],
            // output has no sample at 1300 → that point is dropped, not treated as 0.
            ['OUT', null, '300s', null, null, false, [['timestamp' => 1000, 'value' => 50.0]]],
        ]);

        $series = (new CanonicalReader($p, $catalog))->readRange('ecoflow_net_watts', null, '300s');

        $this->assertCount(1, $series);
        $this->assertSame(1000, $series[0]['t']);
        $this->assertEqualsWithDelta(150.0, $series[0]['v'], 0.001); // 200 − 50
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
