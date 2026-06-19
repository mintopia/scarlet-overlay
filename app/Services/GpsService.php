<?php

namespace App\Services;

use App\Models\Gps;
use App\Support\GeoUtils;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

class GpsService
{
    public function __construct(
        protected PrometheusService $prometheus,
        protected CanonicalReader $canonical,
    ) {}

    public function getLocation(bool $force = false): Gps
    {
        if (! $force && $cached = Cache::get('gps.location')) {
            return $cached;
        }

        $data = $this->canonical->readMany([
            'position_latitude', 'position_longitude', 'gps_speed', 'gps_heading', 'gps_satellites', 'gps_hdop',
        ]);

        $gps = new Gps;
        $lat = $data['position_latitude']['value'] ?? null;
        $lng = $data['position_longitude']['value'] ?? null;
        $nullIsland = GeoUtils::isNullIsland($lat, $lng);
        $gps->latitude = $nullIsland ? null : $lat;
        $gps->longitude = $nullIsland ? null : $lng;
        $gps->speed = $data['gps_speed']['value'] ?? 0;
        $gps->course = $data['gps_heading']['value'] ?? 0;
        $gps->satellites = (int) ($data['gps_satellites']['value'] ?? 0);
        $gps->hdop = (float) ($data['gps_hdop']['value'] ?? 9999);
        $gps->valid = ! $nullIsland;
        $gps->timestamp = CarbonImmutable::now();

        Cache::put('gps.location', $gps, 10);

        return $gps;
    }
}
