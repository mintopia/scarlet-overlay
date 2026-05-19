<?php

namespace App\Services;

use App\Models\Gps;
use Illuminate\Support\Facades\Cache;

class GpsService
{
    public function __construct(protected PrometheusService $prometheus) {}

    public function getLocation(bool $force = false): Gps
    {
        if (!$force && $cached = Cache::get('gps.location')) {
            return $cached;
        }

        $data = $this->prometheus->queryMultiple([
            'latitude' => 'scarlet_gps_latitude',
            'longitude' => 'scarlet_gps_longitude',
            'speed' => 'scarlet_boat_speed_kn',
            'course' => 'scarlet_boat_heading_deg',
            'satellites' => 'scarlet_gps_satellites',
            'hdop' => 'scarlet_gps_hdop',
        ]);

        $gps = new Gps();
        $gps->latitude = $data['latitude'] ?? 0;
        $gps->longitude = $data['longitude'] ?? 0;
        $gps->speed = $data['speed'] ?? 0;
        $gps->course = $data['course'] ?? 0;
        $gps->satellites = (int) ($data['satellites'] ?? 0);
        $gps->hdop = (int) ($data['hdop'] ?? 9999);
        $gps->valid = $gps->latitude !== 0.0 && $gps->longitude !== 0.0;
        $gps->timestamp = now();

        Cache::put('gps.location', $gps, 10);
        return $gps;
    }
}
