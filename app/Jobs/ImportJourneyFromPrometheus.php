<?php

namespace App\Jobs;

use App\Models\Journey;
use App\Models\JourneyTrackPoint;
use App\Services\PrometheusService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ImportJourneyFromPrometheus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $journeyId,
        public string $startTime,
        public string $endTime,
    ) {}

    public function handle(PrometheusService $prometheus): void
    {
        $journey = Journey::findOrFail($this->journeyId);
        $start = \Carbon\Carbon::parse($this->startTime)->timestamp;
        $end = \Carbon\Carbon::parse($this->endTime)->timestamp;
        $step = '30s';

        $metrics = [
            'latitude' => 'scarlet_signalk_navigation_position_latitude',
            'longitude' => 'scarlet_signalk_navigation_position_longitude',
            'speed_sog' => 'scarlet_signalk_navigation_speedOverGround * 1.94384',
            'speed_stw' => 'scarlet_signalk_navigation_speedThroughWater * 1.94384',
            'heading' => 'scarlet_signalk_navigation_headingMagnetic * 180 / 3.14159265359',
            'cog' => 'scarlet_signalk_navigation_courseOverGroundTrue * 180 / 3.14159265359',
            'depth' => 'scarlet_signalk_environment_depth_belowSurface',
            'wind_speed_apparent' => 'scarlet_signalk_environment_wind_speedApparent * 1.94384',
            'wind_angle_apparent' => 'scarlet_signalk_environment_wind_angleApparent * 180 / 3.14159265359',
            'wind_speed_true' => 'scarlet_boat_wind_speed_kn',
            'wind_direction_true' => 'scarlet_boat_wind_direction_deg',
            'house_battery_voltage' => 'scarlet_signalk_electrical_batteries_0_voltage',
            'house_battery_current' => 'scarlet_signalk_electrical_batteries_0_current',
            'heel' => 'scarlet_signalk_navigation_attitude_roll * 180 / 3.14159265359',
        ];

        $data = [];
        foreach ($metrics as $key => $query) {
            $results = $prometheus->queryRange($query, '', $step, $start, $end);
            foreach ($results as $point) {
                $ts = $point['timestamp'];
                if (!isset($data[$ts])) {
                    $data[$ts] = ['recorded_at' => date('Y-m-d H:i:s', $ts)];
                }
                $data[$ts][$key] = $point['value'];
            }
        }

        ksort($data);

        $batch = [];
        foreach ($data as $row) {
            if (!isset($row['latitude']) || !isset($row['longitude'])) {
                continue;
            }

            $batch[] = array_merge(['journey_id' => $journey->id], $row);

            if (count($batch) >= 500) {
                JourneyTrackPoint::insert($batch);
                $batch = [];
            }
        }

        if (!empty($batch)) {
            JourneyTrackPoint::insert($batch);
        }

        $pointCount = $journey->trackPoints()->count();
        Log::info("Imported {$pointCount} track points for journey #{$journey->id}");
    }

}
