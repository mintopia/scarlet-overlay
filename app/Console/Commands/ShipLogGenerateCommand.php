<?php

namespace App\Console\Commands;

use App\Models\Journey;
use App\Models\ShipLog;
use App\Services\PrometheusService;
use Illuminate\Console\Command;

class ShipLogGenerateCommand extends Command
{
    use ResolvesShipLogData;

    protected $signature = 'ship-log:generate';

    protected $description = 'Generate an hourly ship log entry from current Prometheus metrics';

    public function handle(PrometheusService $prometheus): int
    {
        $stepSeconds = 3600;
        $timestamp = (int) floor(now()->timestamp / $stepSeconds) * $stepSeconds;

        if (ShipLog::where('recorded_at', date('Y-m-d H:i:s', $timestamp))->exists()) {
            $this->line('Entry already exists for '.date('Y-m-d H:i', $timestamp).' — skipping.');

            return self::SUCCESS;
        }

        $queries = config('scarlet.metrics.mappings.log');
        $values = $prometheus->queryMultipleAt($queries, $timestamp);

        $logData = $this->buildLogData($values);
        $journey = Journey::current();

        ShipLog::create(array_merge($logData, [
            'journey_id' => $journey?->id,
            'recorded_at' => date('Y-m-d H:i:s', $timestamp),
        ]));

        $this->info('Ship log entry created for '.date('Y-m-d H:i', $timestamp));

        return self::SUCCESS;
    }
}
