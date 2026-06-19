<?php

namespace App\Jobs;

use App\Models\Journey;
use App\Models\JourneyTrackPoint;
use App\Services\CanonicalReader;
use App\Support\GeoUtils;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ImportJourneyFromPrometheus implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public int $journeyId,
        public string $startTime,
        public string $endTime,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->journeyId;
    }

    public function handle(CanonicalReader $canonical): void
    {
        $journey = Journey::findOrFail($this->journeyId);
        $journey->trackPoints()->delete();
        $start = Carbon::parse($this->startTime)->timestamp;
        $end = Carbon::parse($this->endTime)->timestamp;
        $step = '30s';

        $latData = $canonical->readRange('position_latitude', null, $step, $start, $end);
        $lngData = $canonical->readRange('position_longitude', null, $step, $start, $end);
        $lngByTs = collect($lngData)->keyBy('t');

        // Track-point DB column => canonical catalog key.
        $metricKeys = [
            'depth' => 'depth_below_surface',
            'wind_speed_apparent' => 'wind_speed_apparent',
            'wind_angle_apparent' => 'wind_angle_apparent',
            'speed_stw' => 'speed_stw',
            'cog' => 'cog',
            'house_battery_voltage' => 'house_battery_voltage',
            'house_battery_current' => 'house_battery_current',
            'heel' => 'heel',
        ];

        $data = [];
        foreach ($latData as $point) {
            $ts = $point['t'];
            $lng = $lngByTs->get($ts);
            if (! $lng) {
                continue;
            }
            if (GeoUtils::isNullIsland($point['v'], $lng['v'])) {
                continue;
            }
            $data[$ts] = [
                'recorded_at' => date('Y-m-d H:i:s', $ts),
                'latitude' => $point['v'],
                'longitude' => $lng['v'],
            ];
        }

        $headingData = $canonical->readRange('gps_heading', null, $step, $start, $end);
        foreach ($headingData as $point) {
            $ts = $point['t'];
            if (isset($data[$ts])) {
                $data[$ts]['heading'] = $point['v'];
            }
        }

        $speedData = $canonical->readRange('gps_speed', null, $step, $start, $end);
        foreach ($speedData as $point) {
            $ts = $point['t'];
            if (isset($data[$ts])) {
                $data[$ts]['speed_sog'] = $point['v'];
            }
        }

        foreach ($metricKeys as $column => $canonicalKey) {
            $results = $canonical->readRange($canonicalKey, null, $step, $start, $end);
            foreach ($results as $point) {
                $ts = $point['t'];
                if (isset($data[$ts])) {
                    $data[$ts][$column] = $point['v'];
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
