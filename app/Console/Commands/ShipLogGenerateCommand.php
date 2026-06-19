<?php

namespace App\Console\Commands;

use App\Models\Journey;
use App\Models\ShipLog;
use App\Services\CanonicalReader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ShipLogGenerateCommand extends Command
{
    use ResolvesShipLogData;

    protected $signature = 'ship-log:generate';

    protected $description = 'Generate an hourly ship log entry from current Prometheus metrics';

    public function handle(CanonicalReader $canonical): int
    {
        $stepSeconds = 3600;
        $timestamp = (int) floor(now()->timestamp / $stepSeconds) * $stepSeconds;

        if (ShipLog::where('recorded_at', date('Y-m-d H:i:s', $timestamp))->exists()) {
            $this->line('Entry already exists for '.date('Y-m-d H:i', $timestamp).' — skipping.');

            return self::SUCCESS;
        }

        $values = [];
        try {
            foreach ($this->shipLogCanonicalKeys as $key) {
                $values[$key] = $canonical->readAt($key, $timestamp)['value'] ?? null;
            }
        } catch (\Throwable $e) {
            Log::error('Ship log: Prometheus query failed', ['error' => $e->getMessage()]);
            $this->error('Prometheus query failed: '.$e->getMessage());
            $values = array_fill_keys($this->shipLogCanonicalKeys, null);
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
