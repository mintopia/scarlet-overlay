<?php

namespace App\Console\Commands;

trait ResolvesShipLogData
{
    private function buildLogData(array $values): array
    {
        $latitude = $this->nonZero($values['latitude']) ?? $this->nonZero($values['signalk_latitude'] ?? null);
        $longitude = $this->nonZero($values['longitude']) ?? $this->nonZero($values['signalk_longitude'] ?? null);

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

        return [
            'latitude' => $latitude,
            'longitude' => $longitude,
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
        ];
    }

    private function nonZero(?float $value): ?float
    {
        return ($value !== null && $value != 0) ? $value : null;
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
