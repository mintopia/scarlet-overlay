<?php

namespace App\Console\Commands;

use App\Support\NavigationMath;

trait ResolvesShipLogData
{
    /**
     * Canonical catalog keys this ship-log row reads. Each value is resolved through the
     * canonical reader (at the hour timestamp for generation, via range for backfill).
     *
     * @var array<int, string>
     */
    private array $shipLogCanonicalKeys = [
        'position_latitude',
        'position_longitude',
        'trip_log',
        'wind_speed_apparent',
        'wind_angle_apparent',
        'speed_stw',
        'heading_true',
        'cog',
        'cabin_pressure_forepeak',
        'wp_distance',
        'wp_ttg',
        'house_battery_soc',
        'water_fresh_level',
        'fuel_level',
    ];

    /**
     * Build a ship-log row from canonical reader values (display units).
     *
     * @param  array<string, ?float>  $values  canonical key => value
     */
    private function buildLogData(array $values): array
    {
        $latitude = $this->nonZero($values['position_latitude'] ?? null);
        $longitude = $this->nonZero($values['position_longitude'] ?? null);

        if ($latitude !== null && $longitude !== null && abs($latitude) < 0.1 && abs($longitude) < 0.1) {
            $latitude = null;
            $longitude = null;
        }

        $trueWind = $this->trueWindFromCanonical(
            $values['wind_speed_apparent'] ?? null,
            $values['wind_angle_apparent'] ?? null,
            $values['speed_stw'] ?? null,
            $values['heading_true'] ?? null,
        );

        // Canonical cog / heading_true are already in degrees.
        $course = $values['cog'] ?? $values['heading_true'] ?? null;

        return [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'course' => $course,
            'trip_log' => $values['trip_log'] ?? null,
            'wind_speed' => $trueWind['speed'],
            'wind_direction' => $trueWind['direction'],
            'pressure' => $values['cabin_pressure_forepeak'] ?? null,
            'wp_distance' => $values['wp_distance'] ?? null,
            'wp_ttg' => $values['wp_ttg'] ?? null,
            'battery_soc' => $values['house_battery_soc'] ?? null,
            'water_level' => $values['water_fresh_level'] ?? null,
            'fuel_level' => $values['fuel_level'] ?? null,
        ];
    }

    private function nonZero(?float $value): ?float
    {
        return ($value !== null && $value != 0) ? $value : null;
    }

    /**
     * True wind from canonical display-unit values (aws kn, awa deg, stw kn, heading deg).
     * Converts back to raw SI units for the cosine-rule calculation.
     *
     * @return array{speed: ?float, direction: ?float}
     */
    private function trueWindFromCanonical(?float $awsKn, ?float $awaDeg, ?float $stwKn, ?float $headingDeg): array
    {
        $aws = $awsKn !== null ? $awsKn / 1.94384 : null;
        $awa = $awaDeg !== null ? deg2rad($awaDeg) : null;
        $stw = $stwKn !== null ? $stwKn / 1.94384 : null;
        $heading = $headingDeg !== null ? deg2rad($headingDeg) : null;

        $tw = NavigationMath::calculateTrueWind($aws, $awa, $stw, $heading);

        return [
            'speed' => $tw['speed'] !== null ? $tw['speed'] * 1.94384 : null,
            'direction' => $tw['direction'] !== null ? rad2deg($tw['direction']) : null,
        ];
    }
}
