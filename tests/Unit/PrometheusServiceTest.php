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

        $service = new PrometheusService();
        $result = $service->query('boat_speed_kn');
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

        $service = new PrometheusService();
        $this->assertNull($service->query('nonexistent_metric'));
    }

    public function test_range_query_returns_series(): void
    {
        Http::fake([
            '*/api/v1/query_range*' => Http::response([
                'status' => 'success',
                'data' => [
                    'resultType' => 'matrix',
                    'result' => [[
                        'values' => [[1716000000, '4.6'], [1716000015, '4.8']],
                    ]],
                ],
            ]),
        ]);

        $service = new PrometheusService();
        $result = $service->queryRange('boat_speed_kn', '1h');
        $this->assertCount(2, $result);
        $this->assertEquals(4.6, $result[0]['value']);
    }
}
