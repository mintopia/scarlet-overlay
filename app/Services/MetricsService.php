<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetricsService
{
    public function getMetrics(): array
    {
        $prometheusUrl = config('scarlet.metrics.prometheus_url');
        $queries = config('scarlet.metrics.queries');
        $metrics = [];

        foreach ($queries as $key => $query) {
            try {
                $response = Http::timeout(5)->get("{$prometheusUrl}/api/v1/query", [
                    'query' => $query['query'],
                ]);

                if (!$response->ok()) {
                    continue;
                }

                $result = $response->json('data.result');
                if (empty($result)) {
                    continue;
                }

                $metrics[$key] = [
                    'value' => (float) $result[0]['value'][1],
                    'label' => $query['label'],
                    'unit' => $query['unit'],
                    'precision' => $query['precision'] ?? 1,
                ];
            } catch (\Throwable $e) {
                Log::warning("Failed to query metric {$key}: {$e->getMessage()}");
            }
        }

        return $metrics;
    }
}
