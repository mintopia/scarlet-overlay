<?php

namespace App\Jobs;

use App\Models\Journey;
use App\Models\JourneyTrackPoint;
use App\Services\MetricRegistry;
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

    public function handle(MetricRegistry $registry): void
    {
        $journey = Journey::findOrFail($this->journeyId);
        $start = Carbon::parse($this->startTime)->timestamp;
        $end = Carbon::parse($this->endTime)->timestamp;
        $step = '30s';

        $latData = $registry->fetchRange('track_latitude', $step, $start, $end, fillGaps: false);
        $lngData = $registry->fetchRange('track_longitude', $step, $start, $end, fillGaps: false);
        $lngByTs = collect($lngData)->keyBy('timestamp');

        $metricKeys = [
            'depth',
            'wind_speed_apparent', 'wind_angle_apparent',
            'speed_stw', 'cog',
            'house_battery_voltage', 'house_battery_current',
            'heel',
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

        $headingData = $registry->fetchRange('gps_heading', $step, $start, $end, fillGaps: false);
        foreach ($headingData as $point) {
            $ts = $point['timestamp'];
            if (isset($data[$ts])) {
                $data[$ts]['heading'] = $point['value'];
            }
        }

        $speedData = $registry->fetchRange('gps_speed', $step, $start, $end, fillGaps: false);
        foreach ($speedData as $point) {
            $ts = $point['timestamp'];
            if (isset($data[$ts])) {
                $data[$ts]['speed_sog'] = $point['value'];
            }
        }

        foreach ($metricKeys as $registryKey) {
            $results = $registry->fetchRange($registryKey, $step, $start, $end, fillGaps: false);
            foreach ($results as $point) {
                $ts = $point['timestamp'];
                if (isset($data[$ts])) {
                    $data[$ts][$registryKey] = $point['value'];
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
