<?php

namespace Tests\Unit;

use App\Services\PrometheusService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PrometheusServiceTest extends TestCase
{
    public function test_query_returns_value(): void
    {
        Http::fake([
            '*/api/v1/query*' => Http::response([
                'status' => 'success',
                'data' => [
                    'resultType' => 'vector',
                    'result' => [['value' => [1716000000, '4.6']]],
                ],
            ]),
        ]);

        $service = new PrometheusService;
        $result = $service->query('scarlet_speed_kn');
        $this->assertEquals(4.6, $result);
    }

    public function test_query_returns_null_when_no_data(): void
    {
        Http::fake([
            '*/api/v1/query*' => Http::response([
                'status' => 'success',
                'data' => ['resultType' => 'vector', 'result' => []],
            ]),
        ]);

        $service = new PrometheusService;
        $this->assertNull($service->query('nonexistent_metric'));
    }

    public function test_range_query_returns_full_grid_with_nulls_for_gaps(): void
    {
        $start = 1716000000;
        $end = $start + 60;

        Http::fake([
            '*/api/v1/query_range*' => Http::response([
                'status' => 'success',
                'data' => [
                    'resultType' => 'matrix',
                    'result' => [[
                        'values' => [[$start, '4.6'], [$start + 30, '4.8']],
                    ]],
                ],
            ]),
        ]);

        $service = new PrometheusService;
        $result = $service->queryRange('boat_speed_kn', null, '15s', $start, $end);

        $this->assertCount(5, $result);
        $this->assertEquals(4.6, $result[0]['value']);
        $this->assertNull($result[1]['value']);
        $this->assertEquals(4.8, $result[2]['value']);
        $this->assertNull($result[3]['value']);
        $this->assertNull($result[4]['value']);
    }

    public function test_range_query_returns_empty_on_no_data(): void
    {
        Http::fake([
            '*/api/v1/query_range*' => Http::response([
                'status' => 'success',
                'data' => ['resultType' => 'matrix', 'result' => []],
            ]),
        ]);

        $service = new PrometheusService;
        $result = $service->queryRange('nonexistent', null, '15s', 1716000000, 1716000060);
        $this->assertEmpty($result);
    }

    public function test_query_multiple_returns_values_for_all_keys(): void
    {
        Http::fake([
            '*/api/v1/query*' => Http::response([
                'status' => 'success',
                'data' => [
                    'resultType' => 'vector',
                    'result' => [['value' => [1716000000, '42.0']]],
                ],
            ]),
        ]);

        $service = new PrometheusService;
        $result = $service->queryMultiple([
            'speed' => 'scarlet_speed',
            'depth' => 'scarlet_depth',
        ]);

        $this->assertArrayHasKey('speed', $result);
        $this->assertArrayHasKey('depth', $result);
        $this->assertEquals(42.0, $result['speed']);
        $this->assertEquals(42.0, $result['depth']);
    }

    public function test_query_multiple_returns_null_for_missing_metrics(): void
    {
        Http::fake([
            '*/api/v1/query*' => Http::response([
                'status' => 'success',
                'data' => ['resultType' => 'vector', 'result' => []],
            ]),
        ]);

        $service = new PrometheusService;
        $result = $service->queryMultiple([
            'speed' => 'scarlet_speed',
        ]);

        $this->assertArrayHasKey('speed', $result);
        $this->assertNull($result['speed']);
    }

    public function test_query_fresh_returns_value_when_recent(): void
    {
        $now = now()->timestamp;
        Http::fake([
            '*/api/v1/query*' => Http::response([
                'status' => 'success',
                'data' => [
                    'resultType' => 'vector',
                    'result' => [['value' => [$now, '5.0']]],
                ],
            ]),
        ]);

        $service = new PrometheusService;
        $result = $service->queryFresh('scarlet_metric', 120);
        $this->assertEquals(5.0, $result);
    }

    public function test_query_fresh_returns_null_when_stale(): void
    {
        $staleTs = now()->timestamp - 300;
        Http::fake([
            '*/api/v1/query*' => Http::response([
                'status' => 'success',
                'data' => [
                    'resultType' => 'vector',
                    'result' => [['value' => [$staleTs, '5.0']]],
                ],
            ]),
        ]);

        $service = new PrometheusService;
        $result = $service->queryFresh('scarlet_metric', 120);
        $this->assertNull($result);
    }

    public function test_query_timestamp_returns_last_seen_time(): void
    {
        $dataTs = now()->timestamp - 30;
        Http::fake([
            '*/api/v1/query*' => Http::response([
                'status' => 'success',
                'data' => [
                    'resultType' => 'vector',
                    'result' => [['value' => [now()->timestamp, (string) $dataTs]]],
                ],
            ]),
        ]);

        $service = new PrometheusService;
        $result = $service->queryTimestamp('scarlet_metric');
        $this->assertEquals($dataTs, $result);
    }
}
