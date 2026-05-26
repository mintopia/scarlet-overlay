<?php

namespace App\Console\Commands;

use App\Models\Journey;
use App\Models\ShipLog;
use App\Services\PrometheusService;
use Illuminate\Console\Command;

class ShipLogGenerateCommand extends Command
{
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

        $journey = Journey::current();

        ShipLog::create([
            'journey_id' => $journey?->id,
            'recorded_at' => date('Y-m-d H:i:s', $timestamp),
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

        $this->info('Ship log entry created for '.date('Y-m-d H:i', $timestamp));

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
