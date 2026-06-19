<?php

namespace Tests\Feature\Canonical;

use App\Services\CanonicalCatalog;
use App\Services\CanonicalReader;
use App\Support\CanonicalBaseline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CanonicalReaderRangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_read_range_returns_empty_for_unknown_key(): void
    {
        config()->set('scarlet.canonical.enabled', true);
        app(CanonicalCatalog::class)->applyBaseline(CanonicalBaseline::definitions(), 'reset', 'test');

        Http::fake();

        $points = app(CanonicalReader::class)->readRange('no_such_key', '6h');

        $this->assertSame([], $points);
    }

    public function test_read_range_returns_transformed_points(): void
    {
        config()->set('scarlet.canonical.enabled', true);
        app(CanonicalCatalog::class)->applyBaseline(CanonicalBaseline::definitions(), 'reset', 'test');

        $t1 = 1_700_000_000;
        $t2 = 1_700_000_300;

        // fuel_level source has unit_transform multiply×100 (ratio → percent).
        // queryRange calls /api/v1/query_range and parses data.result[0].values.
        Http::fake([
            '*/api/v1/query_range*' => Http::response([
                'status' => 'success',
                'data' => [
                    'resultType' => 'matrix',
                    'result' => [
                        [
                            'metric' => [],
                            'values' => [
                                [$t1, '0.40'],
                                [$t2, '0.42'],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $points = app(CanonicalReader::class)->readRange('fuel_level', '6h');

        $this->assertNotEmpty($points);

        foreach ($points as $point) {
            $this->assertArrayHasKey('t', $point);
            $this->assertArrayHasKey('v', $point);
            $this->assertIsInt($point['t']);
            $this->assertIsFloat($point['v']);
        }

        // Find our two injected timestamps (fill-gaps may add nulls between them).
        $byTs = collect($points)->keyBy('t');

        $this->assertTrue($byTs->has($t1), "Expected point at t1={$t1}");
        $this->assertTrue($byTs->has($t2), "Expected point at t2={$t2}");

        // Unit transform: ratio × 100 → percent.
        $this->assertEqualsWithDelta(40.0, $byTs->get($t1)['v'], 0.001);
        $this->assertEqualsWithDelta(42.0, $byTs->get($t2)['v'], 0.001);
    }

    public function test_read_range_returns_empty_when_prometheus_has_no_data(): void
    {
        config()->set('scarlet.canonical.enabled', true);
        app(CanonicalCatalog::class)->applyBaseline(CanonicalBaseline::definitions(), 'reset', 'test');

        Http::fake([
            '*/api/v1/query_range*' => Http::response([
                'status' => 'success',
                'data' => ['resultType' => 'matrix', 'result' => []],
            ]),
        ]);

        $points = app(CanonicalReader::class)->readRange('fuel_level', '6h');

        $this->assertSame([], $points);
    }
}
