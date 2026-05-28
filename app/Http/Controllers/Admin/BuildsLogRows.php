<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Support\Collection;

trait BuildsLogRows
{
    private function buildLogRows(Collection $logs): array
    {
        $rows = [];
        $firstLog = $logs->isNotEmpty() ? (float) ($logs->first()->trip_log ?? 0) : 0;

        foreach ($logs as $log) {
            $nullIsland = $log->latitude === null || $log->longitude === null
                || (abs($log->latitude) < 0.1 && abs($log->longitude) < 0.1);

            $rows[] = [
                'id' => $log->id,
                'timestamp' => $log->recorded_at->timestamp,
                'course' => $log->course !== null ? (float) $log->course : null,
                'total_log' => $log->trip_log !== null
                    ? (float) $log->trip_log - $firstLog
                    : null,
                'trip_log' => $log->trip_log !== null ? (float) $log->trip_log : null,
                'wind_direction' => $log->wind_direction !== null ? (float) $log->wind_direction : null,
                'wind_speed' => $log->wind_speed !== null ? (float) $log->wind_speed : null,
                'pressure' => $log->pressure !== null ? (float) $log->pressure : null,
                'latitude' => $nullIsland ? null : (float) $log->latitude,
                'longitude' => $nullIsland ? null : (float) $log->longitude,
                'wp_distance' => $log->wp_distance !== null ? (float) $log->wp_distance : null,
                'wp_ttg' => $log->wp_ttg !== null ? (float) $log->wp_ttg : null,
                'battery_soc' => $log->battery_soc !== null ? (float) $log->battery_soc : null,
                'water_level' => $log->water_level !== null ? (float) $log->water_level : null,
                'fuel_level' => $log->fuel_level !== null ? (float) $log->fuel_level : null,
                'notes' => $log->notes,
            ];
        }

        $cumDist = 0;
        $cumDmg = 0;
        for ($i = 0; $i < count($rows); $i++) {
            if ($i === 0) {
                $rows[$i]['dist'] = null;
                $rows[$i]['dmg'] = null;
                $rows[$i]['diff'] = null;
                $rows[$i]['cum_diff'] = 0;

                continue;
            }

            $prev = $rows[$i - 1];
            $curr = $rows[$i];

            $dist = ($curr['total_log'] !== null && $prev['total_log'] !== null)
                ? $curr['total_log'] - $prev['total_log']
                : null;

            $dmg = ($curr['wp_distance'] !== null && $prev['wp_distance'] !== null)
                ? $prev['wp_distance'] - $curr['wp_distance']
                : null;

            if ($dist !== null && $dmg !== null) {
                $cumDist += $dist;
                $cumDmg += $dmg;
            }

            $rows[$i]['dist'] = $dist;
            $rows[$i]['dmg'] = $dmg;
            $rows[$i]['diff'] = ($dist !== null && $dmg !== null) ? $dmg - $dist : null;
            $rows[$i]['cum_diff'] = round($cumDmg - $cumDist, 1);
        }

        return $rows;
    }
}
