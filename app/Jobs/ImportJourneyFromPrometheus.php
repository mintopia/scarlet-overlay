<?php

namespace App\Jobs;

use App\Models\Journey;
use App\Models\JourneyTrackPoint;
use App\Services\PrometheusService;
use Carbon\Carbon;
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
        $start = Carbon::parse($this->startTime)->timestamp;
        $end = Carbon::parse($this->endTime)->timestamp;
        $step = '30s';

        $history = config('scarlet.metrics.mappings.history');
        $latData = $prometheus->queryRange($history['track_latitude'], '', $step, $start, $end, fillGaps: false);
        $lngData = $prometheus->queryRange($history['track_longitude'], '', $step, $start, $end, fillGaps: false);
        $lngByTs = collect($lngData)->keyBy('timestamp');

        $metrics = [
            'speed_sog' => 'scarlet_gps_speed_kn',
            'heading' => 'scarlet_gps_heading_deg',
            'depth' => 'scarlet_boat_depth_meters',
            'wind_speed_true' => 'scarlet_boat_wind_speed_kn',
            'wind_direction_true' => 'scarlet_boat_wind_direction_deg',
            'wind_speed_apparent' => 'scarlet_signalk_environment_wind_speedApparent * 1.94384',
            'wind_angle_apparent' => 'scarlet_signalk_environment_wind_angleApparent * 180 / 3.14159265359',
            'speed_stw' => 'scarlet_signalk_navigation_speedThroughWater * 1.94384',
            'cog' => 'scarlet_signalk_navigation_courseOverGroundTrue * 180 / 3.14159265359',
            'house_battery_voltage' => 'scarlet_signalk_electrical_batteries_0_voltage',
            'house_battery_current' => 'scarlet_signalk_electrical_batteries_0_current',
            'heel' => 'scarlet_signalk_navigation_attitude_roll * 180 / 3.14159265359',
        ];

        $data = [];
        foreach ($latData as $point) {
            $ts = $point['timestamp'];
            $lng = $lngByTs->get($ts);
            if (! $lng) {
                continue;
            }
            if (abs($point['value']) < 0.1 && abs($lng['value']) < 0.1) {
                continue;
            }
            $data[$ts] = [
                'recorded_at' => date('Y-m-d H:i:s', $ts),
                'latitude' => $point['value'],
                'longitude' => $lng['value'],
            ];
        }

        foreach ($metrics as $key => $query) {
            $results = $prometheus->queryRange($query, '', $step, $start, $end, fillGaps: false);
            foreach ($results as $point) {
                $ts = $point['timestamp'];
                if (isset($data[$ts])) {
                    $data[$ts][$key] = $point['value'];
                }
            }
        }

        ksort($data);

        $batch = [];
        foreach ($data as $row) {
            $batch[] = array_merge(['journey_id' => $journey->id], $row);

            if (count($batch) >= 500) {
                JourneyTrackPoint::insert($batch);
                $batch = [];
            }
        }

        if (! empty($batch)) {
            JourneyTrackPoint::insert($batch);
        }

        $pointCount = $journey->trackPoints()->count();
        Log::info("Imported {$pointCount} track points for journey #{$journey->id}");
    }
}
