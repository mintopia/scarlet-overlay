<?php

namespace App\Services;

class MetricsService
{
    public function __construct(protected PrometheusService $prometheus) {}

    public function getBoatMetrics(): array
    {
        return $this->prometheus->queryMultiple(
            config('scarlet.metrics.mappings.boat')
        );
    }

    public function getTrackerMetrics(): array
    {
        $metrics = $this->prometheus->queryMultiple(
            config('scarlet.metrics.mappings.tracker')
        );

        $metrics['battery_percent'] = $this->voltageToPct($metrics['battery_voltage'] ?? null);

        return $metrics;
    }

    public function getGpsMetrics(): array
    {
        return $this->prometheus->queryMultiple(
            config('scarlet.metrics.mappings.gps')
        );
    }

    public function getAllMetrics(): array
    {
        return [
            'boat' => $this->getBoatMetrics(),
            'tracker' => $this->getTrackerMetrics(),
            'gps' => $this->getGpsMetrics(),
            'timestamp' => now()->toIso8601String(),
        ];
    }

    private function voltageToPct(?float $voltage): ?float
    {
        if ($voltage === null) {
            return null;
        }

        $min = config('scarlet.metrics.battery.min_voltage');
        $max = config('scarlet.metrics.battery.max_voltage');

        if ($max <= $min) {
            return null;
        }

        return round(max(0, min(100, ($voltage - $min) / ($max - $min) * 100)), 1);
    }
}
