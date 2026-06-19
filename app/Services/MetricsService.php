<?php

namespace App\Services;

use App\Http\Resources\V1\WeatherResource;
use App\Models\BoatSetting;
use App\Models\Journey;
use App\Support\GeoUtils;
use App\Support\NavigationMath;
use Carbon\CarbonInterval;

class MetricsService
{
    public function __construct(
        protected PrometheusService $prometheus,
        protected WeatherService $weather,
        protected MetricRegistry $registry,
        protected CanonicalReader $canonical,
        protected CanonicalCatalog $catalog,
    ) {}

    public function getSettings(): array
    {
        $journey = Journey::current();

        return [
            'boat_name' => BoatSetting::getValue('boat_name', config('scarlet.name')),
            'passage_from' => $journey?->from_port ?? '',
            'passage_to' => $journey?->to_port ?? '',
            'port_name' => Journey::lastPort() ?? '',
        ];
    }

    public function getBoatMetrics(): array
    {
        $metrics = $this->registry->fetchInstant($this->registry->groupKeys('boat'));

        $tw = $this->calculateTrueWind(
            $metrics['wind_speed_apparent_raw'],
            $metrics['wind_angle_apparent_raw'],
            $metrics['speed_stw_raw'],
            $metrics['heading_raw'],
        );
        $metrics['wind_speed_true'] = $tw['speed'];
        $metrics['wind_direction_true'] = $tw['direction'];
        unset($metrics['wind_speed_apparent_raw'], $metrics['wind_angle_apparent_raw'], $metrics['speed_stw_raw'], $metrics['heading_raw']);

        if (config('scarlet.canonical.enabled')) {
            foreach (config('scarlet.canonical.overrides', []) as $canonicalKey => $boatKey) {
                $resolved = $this->canonical->read($canonicalKey);
                if ($resolved !== null) {
                    $metrics[$boatKey] = $resolved['value'];
                }
            }
        }

        return $metrics;
    }

    public function getTrackerMetrics(): array
    {
        $metrics = $this->registry->fetchInstant($this->registry->groupKeys('tracker'));

        $metrics['battery_percent'] = $this->voltageToPct($metrics['tracker_battery'] ?? null);
        $metrics['last_seen'] = $this->prometheus->queryTimestamp(
            $this->registry->instantQuery('tracker_uptime')
        );

        return $metrics;
    }

    public function getGpsMetrics(): array
    {
        $gps = $this->registry->fetchInstant($this->registry->groupKeys('gps'));

        $result = [
            'latitude' => $gps['gps_latitude'],
            'longitude' => $gps['gps_longitude'],
            'altitude' => $gps['gps_altitude'],
            'satellites' => $gps['gps_satellites'],
            'hdop' => $gps['gps_hdop'],
            'speed' => $gps['gps_speed'],
            'heading' => $gps['gps_heading'],
        ];

        if (GeoUtils::isNullIsland($result['latitude'], $result['longitude'])) {
            $result['latitude'] = null;
            $result['longitude'] = null;
        }

        return $result;
    }

    public function getWeatherData(): ?array
    {
        try {
            $weather = $this->weather->getWeather();

            return (new WeatherResource($weather))
                ->toArray(request());
        } catch (\Throwable) {
            return null;
        }
    }

    public function getSunTimes(?float $latitude, ?float $longitude, string $timezone = 'UTC'): ?array
    {
        if ($latitude === null || $longitude === null) {
            return null;
        }

        $tz = new \DateTimeZone($timezone);
        $now = new \DateTimeImmutable('now', $tz);
        $info = date_sun_info($now->getTimestamp(), $latitude, $longitude);

        $format = fn (mixed $value): ?string => match (true) {
            $value === true => 'always',
            $value === false => 'never',
            is_int($value) => (new \DateTimeImmutable("@$value"))->setTimezone($tz)->format('H:i'),
            default => null,
        };

        return [
            'sunrise' => $format($info['sunrise']),
            'sunset' => $format($info['sunset']),
            'civilDawn' => $format($info['civil_twilight_begin']),
            'civilDusk' => $format($info['civil_twilight_end']),
            'isDay' => is_int($info['sunrise']) && is_int($info['sunset'])
                && $now->getTimestamp() >= $info['sunrise']
                && $now->getTimestamp() < $info['sunset'],
        ];
    }

    public function getAllMetrics(): array
    {
        $gps = $this->getGpsMetrics();
        $weather = $this->getWeatherData();
        $canonical = $this->getCanonicalContracts();

        return [
            'boat' => $this->getBoatMetrics(),
            'tracker' => $this->getTrackerMetrics(),
            'gps' => $gps,
            'weather' => $weather,
            'settings' => $this->getSettings(),
            'sun' => $this->getSunTimes($gps['latitude'] ?? null, $gps['longitude'] ?? null, $weather['timezone'] ?? 'UTC'),
            'timestamp' => now()->toIso8601String(),
            'canonical' => $canonical['contracts'],
            'catalog_version' => $canonical['version'],
        ];
    }

    /**
     * @return array{contracts: array<string, array<string, mixed>>, version: int}
     */
    public function getCanonicalContracts(): array
    {
        if (! config('scarlet.canonical.enabled')) {
            return ['contracts' => [], 'version' => 0];
        }

        $keys = array_keys($this->catalog->all());
        $contracts = array_filter($this->canonical->readMany($keys), fn ($c) => $c !== null);

        return ['contracts' => $contracts, 'version' => $this->canonical->catalogVersion()];
    }

    public function getAllMetricsAt(int $timestamp): array
    {
        $boat = $this->registry->fetchInstant($this->registry->groupKeys('boat'), $timestamp);

        $tw = $this->calculateTrueWind($boat['wind_speed_apparent_raw'], $boat['wind_angle_apparent_raw'], $boat['speed_stw_raw'], $boat['heading_raw']);
        $boat['wind_speed_true'] = $tw['speed'];
        $boat['wind_direction_true'] = $tw['direction'];
        unset($boat['wind_speed_apparent_raw'], $boat['wind_angle_apparent_raw'], $boat['speed_stw_raw'], $boat['heading_raw']);

        $tracker = $this->registry->fetchInstant($this->registry->groupKeys('tracker'), $timestamp);
        $tracker['battery_percent'] = $this->voltageToPct($tracker['tracker_battery'] ?? null);

        $gpsRaw = $this->registry->fetchInstant($this->registry->groupKeys('gps'), $timestamp);

        $gps = [
            'latitude' => $gpsRaw['gps_latitude'],
            'longitude' => $gpsRaw['gps_longitude'],
            'altitude' => $gpsRaw['gps_altitude'],
            'satellites' => $gpsRaw['gps_satellites'],
            'hdop' => $gpsRaw['gps_hdop'],
            'speed' => $gpsRaw['gps_speed'],
            'heading' => $gpsRaw['gps_heading'],
        ];

        if (GeoUtils::isNullIsland($gps['latitude'] ?? null, $gps['longitude'] ?? null)) {
            $gps['latitude'] = null;
            $gps['longitude'] = null;
        }

        $weatherMetrics = $this->prometheus->queryMultipleAt([
            'temperature' => 'scarlet_weather_temperature_celsius',
            'wind_speed' => 'scarlet_weather_wind_speed_kn',
            'wind_direction' => 'scarlet_weather_wind_direction_deg',
            'wave_height' => 'scarlet_weather_wave_height_m',
            'wave_period' => 'scarlet_weather_wave_period_s',
            'pressure' => 'scarlet_weather_pressure_hpa',
            'current_speed' => 'scarlet_weather_current_speed_kn',
            'current_direction' => 'scarlet_weather_current_direction_deg',
        ], $timestamp);

        $hasWeather = collect($weatherMetrics)->filter()->isNotEmpty();
        $weather = $hasWeather ? array_merge($weatherMetrics, [
            'condition' => 'Partly Cloudy',
            'summary' => 'cloud',
            'icon' => '⛅',
            'sea_temperature' => $boat['water_temp'] ?? null,
        ]) : $this->getWeatherData();

        return [
            'boat' => $boat,
            'tracker' => $tracker,
            'gps' => $gps,
            'weather' => $weather,
            'settings' => $this->getSettings(),
            'timestamp' => date('c', $timestamp),
        ];
    }

    public function getGpsTrack(?string $duration = '48h', string $step = '30s', ?int $start = null, ?int $end = null): array
    {
        if ($start === null && $duration) {
            $start = now()->sub(CarbonInterval::fromString($duration))->timestamp;
        }
        $end = $end ?? now()->timestamp;

        $latData = $this->registry->fetchRange('track_latitude', $step, $start, $end, fillGaps: false);
        $lngData = $this->registry->fetchRange('track_longitude', $step, $start, $end, fillGaps: false);
        $sogData = $this->registry->fetchRange('track_sog', $step, $start, $end, fillGaps: false);

        $lngByTs = collect($lngData)->keyBy('timestamp');
        $sogByTs = collect($sogData)->keyBy('timestamp');

        $raw = [];
        foreach ($latData as $point) {
            $ts = $point['timestamp'];
            $lng = $lngByTs->get($ts);
            if (! $lng) {
                continue;
            }

            if (GeoUtils::isNullIsland($point['value'], $lng['value'])) {
                continue;
            }

            $sog = $sogByTs->get($ts);
            $raw[] = [$point['value'], $lng['value'], $sog['value'] ?? 0];
        }

        return $this->filterTrackOutliers($raw);
    }

    private function filterTrackOutliers(array $points): array
    {
        if (count($points) < 3) {
            return $points;
        }

        $lats = array_column($points, 0);
        sort($lats);
        $medLat = $lats[intdiv(count($lats), 2)];
        $lngs = array_column($points, 1);
        sort($lngs);
        $medLng = $lngs[intdiv(count($lngs), 2)];

        return array_values(array_filter($points, function ($p) use ($medLat, $medLng) {
            $d = sqrt(($p[0] - $medLat) ** 2 + ($p[1] - $medLng) ** 2);

            return $d < 2.0;
        }));
    }

    public function getLogData(?string $duration = '24h', string $step = '3600', ?int $start = null, ?int $end = null): array
    {
        $stepSeconds = (int) $step;

        if ($start !== null && $end !== null) {
            $alignedStart = (int) ceil($start / $stepSeconds) * $stepSeconds;
            $alignedEnd = (int) floor($end / $stepSeconds) * $stepSeconds;
        } else {
            $alignedEnd = (int) floor(now()->timestamp / $stepSeconds) * $stepSeconds;
            $seconds = CarbonInterval::fromString($duration ?? '24h')->totalSeconds;
            $alignedStart = $alignedEnd - (int) $seconds;
            $alignedStart = (int) ceil($alignedStart / $stepSeconds) * $stepSeconds;
        }

        $keys = $this->registry->groupKeys('log');
        $seriesByKey = [];

        foreach ($keys as $registryKey) {
            $data = $this->registry->fetchRange($registryKey, $step.'s', $alignedStart, $alignedEnd);
            $seriesByKey[$registryKey] = collect($data)->keyBy('timestamp');
        }

        $rows = [];
        for ($ts = $alignedStart; $ts <= $alignedEnd; $ts += $stepSeconds) {
            $row = ['timestamp' => $ts];
            foreach ($keys as $registryKey) {
                $point = $seriesByKey[$registryKey]->get($ts);
                $row[$registryKey] = $point ? $point['value'] : null;
            }

            $tw = $this->calculateTrueWind($row['wind_speed_apparent_raw'], $row['wind_angle_apparent_raw'], $row['speed_stw_raw'], $row['heading_raw']);
            $row['wind_speed'] = $tw['speed'];
            $row['wind_direction'] = $tw['direction'];
            $row['course'] = $row['cog_raw'] !== null ? rad2deg($row['cog_raw']) : ($row['heading_raw'] !== null ? rad2deg($row['heading_raw']) : null);
            $row['latitude'] = $row['track_latitude'];
            $row['longitude'] = $row['track_longitude'];
            $row['pressure'] = $row['cabin_pressure_forepeak'];
            $row['wp_distance'] = $row['nav_wp_distance'];
            $row['wp_ttg'] = $row['nav_wp_ttg'];
            $row['battery_soc'] = $row['house_battery_soc'];
            unset(
                $row['wind_speed_apparent_raw'], $row['wind_angle_apparent_raw'],
                $row['speed_stw_raw'], $row['heading_raw'], $row['cog_raw'],
                $row['track_latitude'], $row['track_longitude'],
                $row['cabin_pressure_forepeak'], $row['nav_wp_distance'],
                $row['nav_wp_ttg'], $row['house_battery_soc'],
            );

            if (GeoUtils::isNullIsland($row['latitude'] ?? null, $row['longitude'] ?? null)) {
                $row['latitude'] = null;
                $row['longitude'] = null;
            }

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

            $dmg = NavigationMath::distanceMadeGood($prev['wp_distance'], $curr['wp_distance'], $dist);

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

    private function calculateTrueWind(?float $aws, ?float $awa, ?float $stw, ?float $heading): array
    {
        $tw = NavigationMath::calculateTrueWind($aws, $awa, $stw, $heading);

        return [
            'speed' => $tw['speed'] !== null ? $tw['speed'] * 1.94384 : null,
            'direction' => $tw['direction'] !== null ? rad2deg($tw['direction']) : null,
        ];
    }

    public function getLatestTrueWind(): ?array
    {
        $metrics = $this->registry->fetchInstant([
            'wind_speed_apparent_raw', 'wind_angle_apparent_raw',
            'speed_stw_raw', 'heading_raw',
        ]);

        $tw = $this->calculateTrueWind(
            $metrics['wind_speed_apparent_raw'],
            $metrics['wind_angle_apparent_raw'],
            $metrics['speed_stw_raw'],
            $metrics['heading_raw'],
        );

        return ($tw['speed'] !== null) ? $tw : null;
    }

    public function getTrueWindSeries(string $field, ?string $duration, string $step, ?int $start = null, ?int $end = null): array
    {
        $aws = $this->registry->fetchRange('wind_speed_apparent_raw', $step, $start, $end, fillGaps: false);
        $awa = $this->registry->fetchRange('wind_angle_apparent_raw', $step, $start, $end, fillGaps: false);
        $stw = $this->registry->fetchRange('speed_stw_raw', $step, $start, $end, fillGaps: false);
        $hdg = $this->registry->fetchRange('heading_raw', $step, $start, $end, fillGaps: false);

        $awaByTs = collect($awa)->keyBy('timestamp');
        $stwByTs = collect($stw)->keyBy('timestamp');
        $hdgByTs = collect($hdg)->keyBy('timestamp');

        $result = [];
        foreach ($aws as $point) {
            $ts = $point['timestamp'];
            $awaPoint = $awaByTs->get($ts);
            $stwPoint = $stwByTs->get($ts);
            $hdgPoint = $hdgByTs->get($ts);

            if (! $awaPoint || ! $stwPoint || ! $hdgPoint) {
                continue;
            }

            $tw = $this->calculateTrueWind($point['value'], $awaPoint['value'], $stwPoint['value'], $hdgPoint['value']);

            if ($tw[$field] !== null) {
                $result[] = ['timestamp' => $ts, 'value' => $tw[$field]];
            }
        }

        return $result;
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
