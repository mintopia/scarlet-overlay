<?php

namespace Tests\Feature;

use App\Services\MetricsService;
use Tests\TestCase;

class ApiMetricsTest extends TestCase
{
    public function test_metrics_endpoint_returns_json(): void
    {
        $this->mock(MetricsService::class, function ($mock) {
            $mock->shouldReceive('getAllMetrics')->once()->andReturn([
                'boat' => [],
                'tracker' => [],
                'gps' => [],
                'weather' => null,
                'settings' => [],
                'sun' => null,
                'timestamp' => now()->toIso8601String(),
            ]);
        });

        $response = $this->getJson('/api/v1/metrics');
        $response->assertOk()->assertJsonStructure(['boat', 'tracker', 'gps']);
    }
}
