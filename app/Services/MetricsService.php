<?php

namespace App\Services;

use App\Http\Resources\V1\WeatherResource;
use App\Models\BoatSetting;
use App\Models\Journey;
use Carbon\CarbonInterval;

class MetricsService
{
    public function __construct(
        protected PrometheusService $prometheus,
        protected WeatherService $weather,
        protected MetricRegistry $registry,
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
        $keys = [
            'speed_sog', 'speed_stw', 'heading', 'cog', 'depth', 'heel',
            'trip_log', 'nav_wp_distance', 'nav_wp_ttg',
            'wind_speed_apparent', 'wind_angle_apparent',
            'wind_speed_apparent_raw', 'wind_angle_apparent_raw',
            'speed_stw_raw', 'heading_raw',
            'water_temp',
            'house_battery_voltage', 'house_battery_soc', 'house_battery_current',
            'house_battery_time_remaining', 'engine_battery_voltage',
            'fuel_level', 'water_level',
            'cabin_temp_quarterberth', 'cabin_humidity_quarterberth',
            'cabin_temp_main', 'cabin_humidity_main',
            'cabin_temp_forepeak', 'cabin_humidity_forepeak',
            'cabin_pressure_forepeak',
        ];

        $metrics = $this->registry->fetchInstant($keys);

        $tw = $this->calculateTrueWind(
            $metrics['wind_speed_apparent_raw'],
            $metrics['wind_angle_apparent_raw'],
            $metrics['speed_stw_raw'],
            $metrics['heading_raw'],
        );
        $metrics['wind_speed_true'] = $tw['speed'];
        $metrics['wind_direction_true'] = $tw['direction'];
        unset($metrics['wind_speed_apparent_raw'], $metrics['wind_angle_apparent_raw'], $metrics['speed_stw_raw'], $metrics['heading_raw']);

        return $metrics;
    }

    public function getTrackerMetrics(): array
    {
        $keys = [
            'tracker_battery', 'tracker_usb', 'tracker_lte_connected',
            'tracker_lte_rssi', 'tracker_lte_quality', 'tracker_lte_rat',
            'tracker_wifi_connected', 'tracker_wifi_rssi',
            'tracker_uptime', 'tracker_heap', 'tracker_mode',
            'cabin_temp_forepeak', 'cabin_humidity_forepeak', 'tracker_cpu',
        ];

        $metrics = $this->registry->fetchInstant($keys);

        $metrics['battery_percent'] = $this->voltageToPct($metrics['tracker_battery'] ?? null);
        $metrics['last_seen'] = $this->prometheus->queryTimestamp(
            $this->registry->instantQuery('tracker_uptime')
        );

        return $metrics;
    }

    public function getGpsMetrics(): array
    {
        $gps = $this->registry->fetchInstant([
            'gps_latitude', 'gps_longitude', 'gps_altitude',
            'gps_satellites', 'gps_hdop', 'gps_speed', 'gps_heading',
        ]);

        $remapped = [
            'latitude' => $gps['gps_latitude'],
            'longitude' => $gps['gps_longitude'],
            'altitude' => $gps['gps_altitude'],
            'satellites' => $gps['gps_satellites'],
            'hdop' => $gps['gps_hdop'],
            'speed' => $gps['gps_speed'],
            'heading' => $gps['gps_heading'],
        ];

        if ($this->isNullIsland($remapped['latitude'], $remapped['longitude'])) {
            $remapped['latitude'] = null;
            $remapped['longitude'] = null;
        }

        return $remapped;
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

        return [
            'boat' => $this->getBoatMetrics(),
            'tracker' => $this->getTrackerMetrics(),
            'gps' => $gps,
            'weather' => $weather,
            'settings' => $this->getSettings(),
            'sun' => $this->getSunTimes($gps['latitude'] ?? null, $gps['longitude'] ?? null, $weather['timezone'] ?? 'UTC'),
            'timestamp' => now()->toIso8601String(),
        ];
    }

    public function getAllMetricsAt(int $timestamp): array
    {
        $boatKeys = [
            'speed_sog', 'speed_stw', 'heading', 'cog', 'depth', 'heel',
            'trip_log', 'nav_wp_distance', 'nav_wp_ttg',
            'wind_speed_apparent', 'wind_angle_apparent',
            'wind_speed_apparent_raw', 'wind_angle_apparent_raw',
            'speed_stw_raw', 'heading_raw',
            'water_temp',
            'house_battery_voltage', 'house_battery_soc', 'house_battery_current',
            'house_battery_time_remaining', 'engine_battery_voltage',
            'fuel_level', 'water_level',
            'cabin_temp_quarterberth', 'cabin_humidity_quarterberth',
            'cabin_temp_main', 'cabin_humidity_main',
            'cabin_temp_forepeak', 'cabin_humidity_forepeak',
            'cabin_pressure_forepeak',
        ];

        $boat = $this->registry->fetchInstant($boatKeys, $timestamp);

        $tw = $this->calculateTrueWind($boat['wind_speed_apparent_raw'], $boat['wind_angle_apparent_raw'], $boat['speed_stw_raw'], $boat['heading_raw']);
        $boat['wind_speed_true'] = $tw['speed'];
        $boat['wind_direction_true'] = $tw['direction'];
        unset($boat['wind_speed_apparent_raw'], $boat['wind_angle_apparent_raw'], $boat['speed_stw_raw'], $boat['heading_raw']);

        $trackerKeys = [
            'tracker_battery', 'tracker_usb', 'tracker_lte_connected',
            'tracker_lte_rssi', 'tracker_lte_quality', 'tracker_lte_rat',
            'tracker_wifi_connected', 'tracker_wifi_rssi',
            'tracker_uptime', 'tracker_heap', 'tracker_mode',
            'cabin_temp_forepeak', 'cabin_humidity_forepeak', 'tracker_cpu',
        ];

        $tracker = $this->registry->fetchInstant($trackerKeys, $timestamp);
        $tracker['battery_percent'] = $this->voltageToPct($tracker['tracker_battery'] ?? null);

        $gpsRaw = $this->registry->fetchInstant([
            'gps_latitude', 'gps_longitude', 'gps_altitude',
            'gps_satellites', 'gps_hdop', 'gps_speed', 'gps_heading',
        ], $timestamp);

        $gps = [
            'latitude' => $gpsRaw['gps_latitude'],
            'longitude' => $gpsRaw['gps_longitude'],
            'altitude' => $gpsRaw['gps_altitude'],
            'satellites' => $gpsRaw['gps_satellites'],
            'hdop' => $gpsRaw['gps_hdop'],
            'speed' => $gpsRaw['gps_speed'],
            'heading' => $gpsRaw['gps_heading'],
        ];

        if ($this->isNullIsland($gps['latitude'] ?? null, $gps['longitude'] ?? null)) {
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

            if ($this->isNullIsland($point['value'], $lng['value'])) {
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

        $mapping = $this->registry->logMapping();
        $seriesByKey = [];

        foreach ($mapping as $fieldName => $registryKey) {
            $data = $this->registry->fetchRange($registryKey, $step.'s', $alignedStart, $alignedEnd);
            $seriesByKey[$fieldName] = collect($data)->keyBy('timestamp');
        }

        $rows = [];
        for ($ts = $alignedStart; $ts <= $alignedEnd; $ts += $stepSeconds) {
            $row = ['timestamp' => $ts];
            foreach ($mapping as $fieldName => $registryKey) {
                $point = $seriesByKey[$fieldName]->get($ts);
                $row[$fieldName] = $point ? $point['value'] : null;
            }

            $tw = $this->calculateTrueWind($row['aws'], $row['awa'], $row['stw'], $row['heading']);
            $row['wind_speed'] = $tw['speed'];
            $row['wind_direction'] = $tw['direction'];
            $row['course'] = $row['cog'] !== null ? rad2deg($row['cog']) : ($row['heading'] !== null ? rad2deg($row['heading']) : null);
            unset($row['aws'], $row['awa'], $row['stw'], $row['heading'], $row['cog']);

            if ($this->isNullIsland($row['latitude'] ?? null, $row['longitude'] ?? null)) {
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
