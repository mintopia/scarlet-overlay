<?php

namespace App\Console\Commands;

use App\Models\Journey;
use App\Models\ShipLog;
use App\Services\PrometheusService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ShipLogBackfillCommand extends Command
{
    protected $signature = 'ship-log:backfill
        {--from= : Start date (Y-m-d or Y-m-d H:i)}
        {--to= : End date (Y-m-d or Y-m-d H:i)}
        {--truncate : Delete all existing entries before backfilling}';

    protected $description = 'Backfill ship log entries from Prometheus history';

    public function handle(PrometheusService $prometheus): int
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

        $queries = config('scarlet.metrics.mappings.log');

        $seriesByKey = [];
        foreach ($queries as $key => $promql) {
            $this->line("Fetching {$key}...");
            $wrapped = $prometheus->wrapLastOverTime($promql, $stepSeconds.'s') ?? $promql;
            $data = $prometheus->queryRange($wrapped, null, $stepSeconds.'s', $alignedStart, $alignedEnd);
            $seriesByKey[$key] = collect($data)->keyBy('timestamp');
        }

        $journeys = Journey::whereNotNull('started_at')
            ->get()
            ->map(fn (Journey $j) => [
                'id' => $j->id,
                'start' => $j->started_at->timestamp,
                'end' => $j->ended_at?->timestamp ?? now()->timestamp,
            ]);

        $created = 0;
        $skipped = 0;

        for ($ts = $alignedStart; $ts <= $alignedEnd; $ts += $stepSeconds) {
            $recordedAt = date('Y-m-d H:i:s', $ts);

            if (ShipLog::where('recorded_at', $recordedAt)->exists()) {
                $skipped++;

                continue;
            }

            $values = [];
            foreach ($queries as $key => $promql) {
                $point = $seriesByKey[$key]->get($ts);
                $values[$key] = $point ? $point['value'] : null;
            }

            $trueWind = $this->calculateTrueWind(
                $values['aws'],
                $values['awa'],
                $values['stw'],
                $values['heading'],
            );

            $gpsHeading = $values['gps_heading'];
            $cog = $values['cog'];
            $heading = $values['heading'];
            $course = $gpsHeading ?? ($cog !== null ? rad2deg($cog) : ($heading !== null ? rad2deg($heading) : null));

            $journeyId = $journeys->first(fn ($j) => $ts >= $j['start'] && $ts <= $j['end'])['id'] ?? null;

            ShipLog::create([
                'journey_id' => $journeyId,
                'recorded_at' => $recordedAt,
                'latitude' => $values['latitude'],
                'longitude' => $values['longitude'],
                'course' => $course,
                'trip_log' => $values['trip_log'],
                'wind_speed' => $trueWind['speed'],
                'wind_direction' => $trueWind['direction'],
                'pressure' => $values['pressure'],
                'wp_distance' => $values['wp_distance'],
                'wp_ttg' => $values['wp_ttg'],
                'battery_soc' => $values['battery_soc'],
                'water_level' => $values['water_level'],
                'fuel_level' => $values['fuel_level'],
            ]);

            $created++;
        }

        $this->info("Done. Created {$created} entries, skipped {$skipped} duplicates.");

        return self::SUCCESS;
    }

    private function calculateTrueWind(?float $aws, ?float $awa, ?float $sog, ?float $heading): array
    {
        if ($aws === null || $awa === null || $sog === null || $heading === null) {
            return ['speed' => null, 'direction' => null];
        }

        $twsMs = sqrt($aws ** 2 + $sog ** 2 - 2 * $aws * $sog * cos($awa));
        $twa = atan2($aws * sin($awa), $aws * cos($awa) - $sog);
        $twdRad = fmod($heading + $twa + 2 * M_PI, 2 * M_PI);

        return [
            'speed' => $twsMs * 1.94384,
            'direction' => rad2deg($twdRad),
        ];
    }
}
