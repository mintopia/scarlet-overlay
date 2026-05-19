<?php

namespace App\Console\Commands;

use App\Events\MetricsUpdated;
use App\Services\MetricsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MetricsPushCommand extends Command
{
    protected $signature = 'metrics:push';

    protected $description = 'Query Prometheus and broadcast metrics via Reverb every 15 seconds';

    public function handle(MetricsService $metricsService): int
    {
        $interval = config('scarlet.metrics.push_interval');
        $this->info("Starting metrics push loop (every {$interval}s)");

        while (true) {
            try {
                $metrics = $metricsService->getMetrics();
                if (!empty($metrics)) {
                    MetricsUpdated::dispatch($metrics);
                    $this->line('Pushed ' . count($metrics) . ' metrics');
                }
            } catch (\Throwable $e) {
                Log::error("Metrics push failed: {$e->getMessage()}");
                $this->error("Error: {$e->getMessage()}");
            }

            sleep($interval);
        }

        return self::SUCCESS;
    }
}
