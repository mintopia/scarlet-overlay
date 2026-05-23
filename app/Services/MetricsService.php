<?php

namespace App\Services;

class MetricsService
{
    public function __construct(
        protected PrometheusService $prometheus,
        protected WeatherService $weather,
    ) {}

    public function getSettings(): array
    {
        $journey = \App\Models\Journey::current();

        return [
            'boat_name' => \App\Models\BoatSetting::getValue('boat_name', config('scarlet.name')),
            'passage_from' => $journey?->from_port ?? '',
            'passage_to' => $journey?->to_port ?? '',
            'port_name' => \App\Models\BoatSetting::getValue('port_name', ''),
        ];
    }

    public function getBoatMetrics(): array
    {
        $metrics = $this->prometheus->queryMultiple(
            config('scarlet.metrics.mappings.boat')
        );

        if ($metrics['speed_sog'] === null) {
            $metrics['speed_sog'] = $this->prometheus->query(
                config('scarlet.metrics.mappings.gps.speed')
            );
        }

        $tw = $this->calculateTrueWind($metrics['_aws'], $metrics['_awa'], $metrics['_stw'], $metrics['_heading']);
        $metrics['wind_speed_true'] = $tw['speed'];
        $metrics['wind_direction_true'] = $tw['direction'];
        unset($metrics['_aws'], $metrics['_awa'], $metrics['_stw'], $metrics['_heading']);

        return $metrics;
    }

    public function getTrackerMetrics(): array
    {
        $metrics = $this->prometheus->queryMultiple(
            config('scarlet.metrics.mappings.tracker')
        );

        $metrics['battery_percent'] = $this->voltageToPct($metrics['battery_voltage'] ?? null);

        return $metrics;
    }

    public function getGpsMetrics(): array
    {
        $gps = $this->prometheus->queryMultiple(
            config('scarlet.metrics.mappings.gps')
        );

        $signalk = $this->prometheus->queryMultiple(
            config('scarlet.metrics.mappings.signalk_position')
        );

        if ($signalk['latitude'] !== null && $signalk['longitude'] !== null) {
            $gps['latitude'] = $signalk['latitude'];
            $gps['longitude'] = $signalk['longitude'];
        }

        if ($this->isNullIsland($gps['latitude'], $gps['longitude'])) {
            $gps['latitude'] = null;
            $gps['longitude'] = null;
        }

        return $gps;
    }

    public function getWeatherData(): ?array
    {
        try {
            $weather = $this->weather->getWeather();
            return (new \App\Http\Resources\V1\WeatherResource($weather))
                ->toArray(request());
        } catch (\Throwable) {
            return null;
        }
    }

    public function getAllMetrics(): array
    {
        return [
            'boat' => $this->getBoatMetrics(),
            'tracker' => $this->getTrackerMetrics(),
            'gps' => $this->getGpsMetrics(),
            'weather' => $this->getWeatherData(),
            'settings' => $this->getSettings(),
            'timestamp' => now()->toIso8601String(),
        ];
    }

    public function getGpsTrack(string $duration = '12h', string $step = '30s'): array
    {
        $history = config('scarlet.metrics.mappings.history');
        $signalk = config('scarlet.metrics.mappings.signalk_position');

        $latData = $this->prometheus->queryRange($signalk['latitude'], $duration, $step);
        $lngData = $this->prometheus->queryRange($signalk['longitude'], $duration, $step);

        if (empty($latData) || empty($lngData)) {
            $latData = $this->prometheus->queryRange($history['track_latitude'], $duration, $step);
            $lngData = $this->prometheus->queryRange($history['track_longitude'], $duration, $step);
        }
        $sogData = $this->prometheus->queryRange($history['track_sog'], $duration, $step);

        $lngByTs = collect($lngData)->keyBy('timestamp');
        $sogByTs = collect($sogData)->keyBy('timestamp');

        $track = [];
        foreach ($latData as $point) {
            $ts = $point['timestamp'];
            $lng = $lngByTs->get($ts);
            if (!$lng) continue;

            if ($this->isNullIsland($point['value'], $lng['value'])) continue;

            $sog = $sogByTs->get($ts);

            $track[] = [
                $point['value'],
                $lng['value'],
                $sog['value'] ?? 0,
            ];
        }

        return $track;
    }

    public function getLogData(?string $duration = '24h', string $step = '3600', ?int $start = null, ?int $end = null): array
    {
        $stepSeconds = (int) $step;

        if ($start !== null && $end !== null) {
            $alignedStart = (int) ceil($start / $stepSeconds) * $stepSeconds;
            $alignedEnd = (int) floor($end / $stepSeconds) * $stepSeconds;
        } else {
            $alignedEnd = (int) floor(now()->timestamp / $stepSeconds) * $stepSeconds;
            $seconds = \Carbon\CarbonInterval::fromString($duration ?? '24h')->totalSeconds;
            $alignedStart = $alignedEnd - (int) $seconds;
            $alignedStart = (int) ceil($alignedStart / $stepSeconds) * $stepSeconds;
        }

        $queries = config('scarlet.metrics.mappings.log');
        $seriesByKey = [];

        foreach ($queries as $key => $promql) {
            $data = $this->prometheus->queryRange($promql, null, $step . 's', $alignedStart, $alignedEnd);
            $seriesByKey[$key] = collect($data)->keyBy('timestamp');
        }

        $rows = [];
        for ($ts = $alignedStart; $ts <= $alignedEnd; $ts += $stepSeconds) {
            $row = ['timestamp' => $ts];
            foreach ($queries as $key => $promql) {
                $point = $seriesByKey[$key]->get($ts);
                $row[$key] = $point ? $point['value'] : null;
            }

            $tw = $this->calculateTrueWind($row['aws'], $row['awa'], $row['stw'], $row['heading']);
            $row['wind_speed'] = $tw['speed'];
            $row['wind_direction'] = $tw['direction'];
            $row['course'] = $row['cog'] !== null ? rad2deg($row['cog']) : ($row['heading'] !== null ? rad2deg($row['heading']) : null);
            unset($row['aws'], $row['awa'], $row['stw'], $row['heading'], $row['cog']);

            $rows[] = $row;
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

            $dist = ($curr['trip_log'] !== null && $prev['trip_log'] !== null)
                ? $curr['trip_log'] - $prev['trip_log']
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

    public function getTrueWindSeries(string $field, ?string $duration, string $step, ?int $start = null, ?int $end = null): array
    {
        $aws = $this->prometheus->queryRange('scarlet_signalk_environment_wind_speedApparent', $duration, $step, $start, $end);
        $awa = $this->prometheus->queryRange('scarlet_signalk_environment_wind_angleApparent', $duration, $step, $start, $end);
        $stw = $this->prometheus->queryRange('scarlet_signalk_navigation_speedThroughWater', $duration, $step, $start, $end);
        $hdg = $this->prometheus->queryRange('scarlet_signalk_navigation_headingTrue', $duration, $step, $start, $end);

        $awaByTs = collect($awa)->keyBy('timestamp');
        $stwByTs = collect($stw)->keyBy('timestamp');
        $hdgByTs = collect($hdg)->keyBy('timestamp');

        $result = [];
        foreach ($aws as $point) {
            $ts = $point['timestamp'];
            $awaPoint = $awaByTs->get($ts);
            $stwPoint = $stwByTs->get($ts);
            $hdgPoint = $hdgByTs->get($ts);

            if (!$awaPoint || !$stwPoint || !$hdgPoint) continue;

            $tw = $this->calculateTrueWind($point['value'], $awaPoint['value'], $stwPoint['value'], $hdgPoint['value']);

            if ($tw[$field] !== null) {
                $result[] = ['timestamp' => $ts, 'value' => $tw[$field]];
            }
        }

        return $result;
    }

    private function isNullIsland(?float $lat, ?float $lng): bool
    {
        return $lat === null || $lng === null
            || (abs($lat) < 0.1 && abs($lng) < 0.1);
    }

    private function voltageToPct(?float $voltage): ?float
    {
        if ($voltage === null) {
            return null;
        }

        $min = config('scarlet.metrics.battery.min_voltage');
        $max = config('scarlet.metrics.battery.max_voltage');

        if ($max <= $min) {
            return null;
        }

        return round(max(0, min(100, ($voltage - $min) / ($max - $min) * 100)), 1);
    }
}
