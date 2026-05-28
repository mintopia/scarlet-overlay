<?php

namespace App\Services;

use App\Models\Gps;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

class GpsService
{
    public function __construct(
        protected PrometheusService $prometheus,
        protected MetricRegistry $registry,
    ) {}

    public function getLocation(bool $force = false): Gps
    {
        if (! $force && $cached = Cache::get('gps.location')) {
            return $cached;
        }

        $data = $this->registry->fetchInstant([
            'gps_latitude', 'gps_longitude', 'gps_speed', 'gps_heading', 'gps_satellites', 'gps_hdop',
        ]);

        $gps = new Gps;
        $lat = $data['gps_latitude'] ?? null;
        $lng = $data['gps_longitude'] ?? null;
        $nullIsland = $lat === null || $lng === null
            || (abs($lat) < 0.1 && abs($lng) < 0.1);
        $gps->latitude = $nullIsland ? null : $lat;
        $gps->longitude = $nullIsland ? null : $lng;
        $gps->speed = $data['gps_speed'] ?? 0;
        $gps->course = $data['gps_heading'] ?? 0;
        $gps->satellites = (int) ($data['gps_satellites'] ?? 0);
        $gps->hdop = (int) ($data['gps_hdop'] ?? 9999);
        $gps->valid = ! $nullIsland;
        $gps->timestamp = CarbonImmutable::now();

        Cache::put('gps.location', $gps, 10);

        return $gps;
    }
}
