<?php

namespace App\Console\Commands;

use App\Models\Journey;
use App\Models\ShipLog;
use App\Services\CanonicalReader;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ShipLogBackfillCommand extends Command
{
    use ResolvesShipLogData;

    protected $signature = 'ship-log:backfill
        {--from= : Start date (Y-m-d or Y-m-d H:i)}
        {--to= : End date (Y-m-d or Y-m-d H:i)}
        {--truncate : Delete all existing entries before backfilling}';

    protected $description = 'Backfill ship log entries from Prometheus history';

    public function handle(CanonicalReader $canonical): int
    {
        if ($this->option('truncate')) {
            $count = ShipLog::count();
            ShipLog::truncate();
            $this->warn("Truncated {$count} existing entries.");
        }

        $stepSeconds = 3600;

        $from = $this->option('from')
            ? Carbon::parse($this->option('from'))
            : now()->subDays(7);
        $to = $this->option('to')
            ? Carbon::parse($this->option('to'))
            : now();

        $alignedStart = (int) ceil($from->timestamp / $stepSeconds) * $stepSeconds;
        $alignedEnd = (int) floor($to->timestamp / $stepSeconds) * $stepSeconds;

        $totalHours = ($alignedEnd - $alignedStart) / $stepSeconds;
        $this->info('Backfilling from '.date('Y-m-d H:i', $alignedStart).' to '.date('Y-m-d H:i', $alignedEnd)." ({$totalHours} hours)");

        $keys = $this->shipLogCanonicalKeys;
        $seriesByKey = [];

        foreach ($keys as $canonicalKey) {
            $this->line("Fetching {$canonicalKey}");
            $data = $canonical->readRange($canonicalKey, null, $stepSeconds.'s', $alignedStart, $alignedEnd);
            $this->line("  → {$canonicalKey}: ".count($data).' data points');
            $seriesByKey[$canonicalKey] = collect($data)->keyBy('t');
        }

        $journeys = Journey::whereNotNull('started_at')
            ->get()
            ->map(fn (Journey $j) => [
                'id' => $j->id,
                'start' => $j->started_at->timestamp,
                'end' => $j->ended_at?->timestamp ?? now()->timestamp,
            ]);

        $created = 0;
        $updated = 0;

        for ($ts = $alignedStart; $ts <= $alignedEnd; $ts += $stepSeconds) {
            $recordedAt = date('Y-m-d H:i:s', $ts);

            $values = [];
            foreach ($keys as $canonicalKey) {
                $point = $seriesByKey[$canonicalKey]->get($ts);
                $values[$canonicalKey] = $point ? $point['v'] : null;
            }

            $logData = $this->buildLogData($values);
            $journeyId = $journeys->first(fn ($j) => $ts >= $j['start'] && $ts <= $j['end'])['id'] ?? null;

            $existing = ShipLog::where('recorded_at', $recordedAt)->first();

            if ($existing) {
                $existing->update(array_merge($logData, ['journey_id' => $journeyId]));
                $updated++;
            } else {
                ShipLog::create(array_merge($logData, [
                    'journey_id' => $journeyId,
                    'recorded_at' => $recordedAt,
                ]));
                $created++;
            }
        }

        $this->info("Done. Created {$created} entries, updated {$updated} existing.");

        return self::SUCCESS;
    }
}
