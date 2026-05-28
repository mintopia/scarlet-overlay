<?php

namespace App\Console\Commands;

trait ResolvesShipLogData
{
    private function buildLogData(array $values): array
    {
        $latitude = $this->nonZero($values['track_latitude']);
        $longitude = $this->nonZero($values['track_longitude']);

        if ($latitude !== null && $longitude !== null && abs($latitude) < 0.1 && abs($longitude) < 0.1) {
            $latitude = null;
            $longitude = null;
        }

        $trueWind = $this->calculateTrueWind(
            $values['wind_speed_apparent_raw'],
            $values['wind_angle_apparent_raw'],
            $values['speed_stw_raw'],
            $values['heading_raw'],
        );

        $cog = $values['cog_raw'];
        $heading = $values['heading_raw'];
        $course = $cog !== null ? rad2deg($cog) : ($heading !== null ? rad2deg($heading) : null);

        return [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'course' => $course,
            'trip_log' => $values['trip_log'],
            'wind_speed' => $trueWind['speed'],
            'wind_direction' => $trueWind['direction'],
            'pressure' => $values['cabin_pressure_forepeak'],
            'wp_distance' => $values['nav_wp_distance'],
            'wp_ttg' => $values['nav_wp_ttg'],
            'battery_soc' => $values['house_battery_soc'],
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
