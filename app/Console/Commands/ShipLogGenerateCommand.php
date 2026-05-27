<?php

namespace App\Console\Commands;

use App\Models\Journey;
use App\Models\ShipLog;
use App\Services\PrometheusService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

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
        $values = [];

        try {
            foreach ($queries as $key => $promql) {
                $data = $prometheus->queryRange($promql, null, $stepSeconds.'s', $timestamp, $timestamp);
                $values[$key] = ! empty($data) ? $data[0]['value'] : null;
            }
        } catch (\Throwable $e) {
            Log::error('Ship log: Prometheus query failed', ['error' => $e->getMessage()]);
            $this->error('Prometheus query failed: '.$e->getMessage());
            $values = array_fill_keys(array_keys($queries), null);
        }

        $logData = $this->buildLogData($values);
        $journey = Journey::current();

        try {
            ShipLog::create(array_merge($logData, [
                'journey_id' => $journey?->id,
                'recorded_at' => date('Y-m-d H:i:s', $timestamp),
            ]));
        } catch (\Throwable $e) {
            Log::error('Ship log: failed to create entry', [
                'timestamp' => date('Y-m-d H:i', $timestamp),
                'error' => $e->getMessage(),
                'data' => $logData,
            ]);
            $this->error('Failed to create log entry: '.$e->getMessage());

            return self::FAILURE;
        }

        $filled = collect($logData)->filter(fn ($v) => $v !== null)->count();
        $this->info('Ship log entry created for '.date('Y-m-d H:i', $timestamp)." ({$filled}/".count($logData).' fields)');

        return self::SUCCESS;
    }
}
