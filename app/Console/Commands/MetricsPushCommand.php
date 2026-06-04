<?php

namespace App\Console\Commands;

use App\Events\MetricsUpdated;
use App\Models\Journey;
use App\Services\JourneyService;
use App\Services\MetricsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MetricsPushCommand extends Command
{
    protected $signature = 'metrics:push';

    protected $description = 'Query Prometheus and broadcast metrics via Reverb every 15 seconds';

    public function handle(MetricsService $metricsService, JourneyService $journeyService): int
    {
        $interval = config('scarlet.metrics.push_interval');
        $this->info("Starting metrics push loop (every {$interval}s)");

        $running = true;
        pcntl_async_signals(true);
        $stop = function () use (&$running) {
            $this->info('Stopping metrics push loop...');
            $running = false;
        };
        pcntl_signal(SIGTERM, $stop);
        pcntl_signal(SIGINT, $stop);

        while ($running) {
            try {
                $all = $metricsService->getAllMetrics();
                MetricsUpdated::dispatch(
                    $all['boat'],
                    $all['tracker'],
                    $all['gps'],
                    $all['weather'],
                    $all['settings'],
                    $all['sun'],
                    $all['timestamp'],
                );
                $this->line('Pushed metrics at '.$all['timestamp']);

                $journey = Journey::current();
                if ($journey) {
                    $journeyService->recordTrackPoint($journey, $all);
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
