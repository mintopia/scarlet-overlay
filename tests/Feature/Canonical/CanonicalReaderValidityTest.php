<?php

namespace Tests\Feature\Canonical;

use App\Services\CanonicalCatalog;
use App\Services\CanonicalReader;
use App\Services\PrometheusService;
use Mockery;
use Tests\TestCase;

class CanonicalReaderValidityTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function xteDefinition(array $overrides = []): array
    {
        return array_merge([
            'label' => 'Cross-Track Error', 'unit' => 'm', 'volatile' => false,
            'trend_fn' => 'last', 'trend_window' => '2m', 'staleness' => 120,
            'coverage_window_seconds' => 300, 'coverage_min' => 0.5,
            'valid_min' => -185200.0, 'valid_max' => 185200.0,
            'sources' => [
                ['selector' => 'scarlet_signalk_navigation_course_calcValues_crossTrackError', 'transforms' => []],
            ],
        ], $overrides);
    }

    public function test_out_of_range_sentinel_is_rejected_and_reads_null(): void
    {
        $catalog = Mockery::mock(CanonicalCatalog::class);
        $catalog->shouldReceive('definition')->with('xte')->andReturn($this->xteDefinition());

        $prom = Mockery::mock(PrometheusService::class);
        // SignalK "no active route" sentinel — well outside ±100 nm.
        $prom->shouldReceive('queryWithTimestamp')
            ->andReturn(['value' => -3_790_772.0, 'timestamp' => 1_700_000_000, 'age' => 5]);
        // Coverage / median must never be consulted for an invalid reading.
        $prom->shouldNotReceive('coverageRatio');
        $prom->shouldNotReceive('aggregateOverTime');

        $reader = new CanonicalReader($prom, $catalog);

        $this->assertNull($reader->read('xte'));
    }

    public function test_in_range_value_passes_the_bound(): void
    {
        $catalog = Mockery::mock(CanonicalCatalog::class);
        $catalog->shouldReceive('definition')->with('xte')->andReturn($this->xteDefinition());

        $prom = Mockery::mock(PrometheusService::class);
        $prom->shouldReceive('queryWithTimestamp')
            ->andReturn(['value' => 120.0, 'timestamp' => 1_700_000_000, 'age' => 5]);
        $prom->shouldReceive('coverageRatio')->andReturn(0.9);

        $reader = new CanonicalReader($prom, $catalog);

        $result = $reader->read('xte');

        $this->assertNotNull($result);
        $this->assertEqualsWithDelta(120.0, $result['value'], 0.001);
        $this->assertFalse($result['stale']);
    }

    public function test_unbounded_metric_accepts_any_value(): void
    {
        $catalog = Mockery::mock(CanonicalCatalog::class);
        $catalog->shouldReceive('definition')->with('xte')
            ->andReturn($this->xteDefinition(['valid_min' => null, 'valid_max' => null]));

        $prom = Mockery::mock(PrometheusService::class);
        $prom->shouldReceive('queryWithTimestamp')
            ->andReturn(['value' => -3_790_772.0, 'timestamp' => 1_700_000_000, 'age' => 5]);
        $prom->shouldReceive('coverageRatio')->andReturn(0.9);

        $reader = new CanonicalReader($prom, $catalog);

        $this->assertNotNull($reader->read('xte'));
    }

    /**
     * @return array<string, mixed>
     */
    private function latDefinition(): array
    {
        return [
            'label' => 'Latitude', 'unit' => '°', 'volatile' => false,
            'trend_fn' => 'last', 'trend_window' => '2m', 'staleness' => 120,
            'coverage_window_seconds' => 300, 'coverage_min' => 0.5,
            'valid_min' => -90.0, 'valid_max' => 90.0, 'reject_null_island' => true,
            'sources' => [['selector' => 'scarlet_gps_latitude_deg', 'transforms' => []]],
        ];
    }

    public function test_null_island_reading_is_rejected_when_enabled(): void
    {
        $catalog = Mockery::mock(CanonicalCatalog::class);
        $catalog->shouldReceive('definition')->with('position_lat')->andReturn($this->latDefinition());

        $prom = Mockery::mock(PrometheusService::class);
        $prom->shouldReceive('queryWithTimestamp')
            ->andReturn(['value' => 0.0001, 'timestamp' => 1_700_000_000, 'age' => 5]);
        $prom->shouldNotReceive('coverageRatio');

        $reader = new CanonicalReader($prom, $catalog);

        $this->assertNull($reader->read('position_lat'));
    }

    public function test_real_position_passes_when_null_island_enabled(): void
    {
        $catalog = Mockery::mock(CanonicalCatalog::class);
        $catalog->shouldReceive('definition')->with('position_lat')->andReturn($this->latDefinition());

        $prom = Mockery::mock(PrometheusService::class);
        $prom->shouldReceive('queryWithTimestamp')
            ->andReturn(['value' => -50.42, 'timestamp' => 1_700_000_000, 'age' => 5]);
        $prom->shouldReceive('coverageRatio')->andReturn(0.9);

        $reader = new CanonicalReader($prom, $catalog);

        $result = $reader->read('position_lat');

        $this->assertNotNull($result);
        $this->assertEqualsWithDelta(-50.42, $result['value'], 0.001);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
