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
    /**
     * Legacy boat-metric output key → canonical catalog key. The output array still
     * exposes the legacy keys; only the source of each value is the canonical reader.
     *
     * @var array<string, string>
     */
    private const BOAT_KEYS = [
        'speed_sog' => 'speed_sog',
        'speed_stw' => 'speed_stw',
        'heading' => 'heading_true',
        'cog' => 'cog',
        'depth' => 'depth_below_surface',
        'heel' => 'heel',
        'pitch' => 'pitch',
        'heading_magnetic' => 'heading_magnetic',
        'trip_log' => 'trip_log',
        'nav_wp_distance' => 'wp_distance',
        'nav_wp_ttg' => 'wp_ttg',
        'vmg' => 'vmg',
        'wind_speed_apparent' => 'wind_speed_apparent',
        'wind_angle_apparent' => 'wind_angle_apparent',
        'magnetic_variation' => 'magnetic_variation',
        'water_temp' => 'water_temp',
        'house_battery_voltage' => 'house_battery_voltage',
        'house_battery_soc' => 'house_battery_soc',
        'house_battery_current' => 'house_battery_current',
        'house_battery_time_remaining' => 'house_battery_time_remaining',
        'engine_battery_voltage' => 'engine_battery_voltage',
        'fuel_level' => 'fuel_level',
        'water_level' => 'water_fresh_level',
        'cabin_temp_quarterberth' => 'cabin_temp_quarterberth',
        'cabin_humidity_quarterberth' => 'cabin_humidity_quarterberth',
        'cabin_temp_main' => 'cabin_temp_main',
        'cabin_humidity_main' => 'cabin_humidity_main',
        'cabin_temp_forepeak' => 'cabin_temp_forepeak',
        'cabin_humidity_forepeak' => 'cabin_humidity_forepeak',
        'cabin_pressure_forepeak' => 'cabin_pressure_forepeak',
    ];

    /**
     * Legacy tracker output key → canonical catalog key.
     *
     * @var array<string, string>
     */
    private const TRACKER_KEYS = [
        'tracker_battery' => 'tracker_battery_voltage',
        'tracker_usb' => 'tracker_usb_powered',
        'tracker_lte_connected' => 'tracker_lte_connected',
        'tracker_lte_rssi' => 'tracker_lte_rssi',
        'tracker_lte_quality' => 'tracker_lte_quality',
        'tracker_lte_rat' => 'tracker_lte_rat',
        'tracker_wifi_connected' => 'tracker_wifi_connected',
        'tracker_wifi_rssi' => 'tracker_wifi_rssi',
        'tracker_uptime' => 'tracker_uptime',
        'tracker_heap' => 'tracker_free_heap',
        'tracker_mode' => 'tracker_mode',
        'cabin_temp_forepeak' => 'cabin_temp_forepeak',
        'cabin_humidity_forepeak' => 'cabin_humidity_forepeak',
        'tracker_cpu' => 'tracker_cpu',
    ];

    /**
     * Legacy GPS output key → canonical catalog key.
     *
     * @var array<string, string>
     */
    private const GPS_KEYS = [
        'gps_latitude' => 'position_latitude',
        'gps_longitude' => 'position_longitude',
        'gps_altitude' => 'gps_altitude',
        'gps_satellites' => 'gps_satellites',
        'gps_hdop' => 'gps_hdop',
        'gps_speed' => 'gps_speed',
        'gps_heading' => 'gps_heading',
    ];

    public function __construct(
        protected PrometheusService $prometheus,
        protected WeatherService $weather,
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
        $metrics = $this->readLegacyKeys(self::BOAT_KEYS);

        $tw = $this->trueWindFromCanonical($metrics);
        $metrics['wind_speed_true'] = $tw['speed'];
        $metrics['wind_direction_true'] = $tw['direction'];

        return $metrics;
    }

    public function getTrackerMetrics(): array
    {
        $metrics = $this->readLegacyKeys(self::TRACKER_KEYS);

        $metrics['battery_percent'] = $this->voltageToPct($metrics['tracker_battery'] ?? null);
        $uptime = $this->canonical->read('tracker_uptime');
        $metrics['last_seen'] = $uptime['timestamp'] ?? null;

        return $metrics;
    }

    public function getGpsMetrics(): array
    {
        $gps = $this->readLegacyKeys(self::GPS_KEYS);

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
    private function getCanonicalContracts(): array
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
        $boat = $this->readLegacyKeysAt(self::BOAT_KEYS, $timestamp);

        $tw = $this->trueWindFromCanonical($boat);
        $boat['wind_speed_true'] = $tw['speed'];
        $boat['wind_direction_true'] = $tw['direction'];

        $tracker = $this->readLegacyKeysAt(self::TRACKER_KEYS, $timestamp);
        $tracker['battery_percent'] = $this->voltageToPct($tracker['tracker_battery'] ?? null);

        $gpsRaw = $this->readLegacyKeysAt(self::GPS_KEYS, $timestamp);

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

        $latData = $this->canonical->readRange('position_latitude', null, $step, $start, $end);
        $lngData = $this->canonical->readRange('position_longitude', null, $step, $start, $end);
        $sogData = $this->canonical->readRange('speed_sog', null, $step, $start, $end);

        $lngByTs = collect($lngData)->keyBy('t');
        $sogByTs = collect($sogData)->keyBy('t');

        $raw = [];
        foreach ($latData as $point) {
            $ts = $point['t'];
            $lng = $lngByTs->get($ts);
            if (! $lng) {
                continue;
            }

            if (GeoUtils::isNullIsland($point['v'], $lng['v'])) {
                continue;
            }

            $sog = $sogByTs->get($ts);
            $raw[] = [$point['v'], $lng['v'], $sog['v'] ?? 0];
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

        // Canonical series feeding the ship-log table. Wind needs raw SI values so the
        // true-wind calculation matches the legacy `*_raw` registry intermediates.
        $latData = $this->canonical->readRange('position_latitude', null, $step.'s', $alignedStart, $alignedEnd);
        $lngData = $this->canonical->readRange('position_longitude', null, $step.'s', $alignedStart, $alignedEnd);
        $tripData = $this->canonical->readRange('trip_log', null, $step.'s', $alignedStart, $alignedEnd);
        $pressureData = $this->canonical->readRange('cabin_pressure_forepeak', null, $step.'s', $alignedStart, $alignedEnd);
        $wpDistData = $this->canonical->readRange('wp_distance', null, $step.'s', $alignedStart, $alignedEnd);
        $wpTtgData = $this->canonical->readRange('wp_ttg', null, $step.'s', $alignedStart, $alignedEnd);
        $socData = $this->canonical->readRange('house_battery_soc', null, $step.'s', $alignedStart, $alignedEnd);
        $waterData = $this->canonical->readRange('water_fresh_level', null, $step.'s', $alignedStart, $alignedEnd);
        $fuelData = $this->canonical->readRange('fuel_level', null, $step.'s', $alignedStart, $alignedEnd);

        $awsData = $this->canonical->readRange('wind_speed_apparent', null, $step.'s', $alignedStart, $alignedEnd);
        $awaData = $this->canonical->readRange('wind_angle_apparent', null, $step.'s', $alignedStart, $alignedEnd);
        $stwData = $this->canonical->readRange('speed_stw', null, $step.'s', $alignedStart, $alignedEnd);
        $hdgData = $this->canonical->readRange('heading_true', null, $step.'s', $alignedStart, $alignedEnd);
        $cogData = $this->canonical->readRange('cog', null, $step.'s', $alignedStart, $alignedEnd);

        $lat = $this->seriesByTs($latData);
        $lng = $this->seriesByTs($lngData);
        $trip = $this->seriesByTs($tripData);
        $pressure = $this->seriesByTs($pressureData);
        $wpDist = $this->seriesByTs($wpDistData);
        $wpTtg = $this->seriesByTs($wpTtgData);
        $soc = $this->seriesByTs($socData);
        $water = $this->seriesByTs($waterData);
        $fuel = $this->seriesByTs($fuelData);
        $aws = $this->seriesByTs($awsData);
        $awa = $this->seriesByTs($awaData);
        $stw = $this->seriesByTs($stwData);
        $hdg = $this->seriesByTs($hdgData);
        $cog = $this->seriesByTs($cogData);

        $rows = [];
        for ($ts = $alignedStart; $ts <= $alignedEnd; $ts += $stepSeconds) {
            $headingDeg = $hdg[$ts] ?? null;
            $cogDeg = $cog[$ts] ?? null;

            $tw = $this->trueWindFromCanonicalValues($aws[$ts] ?? null, $awa[$ts] ?? null, $stw[$ts] ?? null, $headingDeg);

            $row = [
                'timestamp' => $ts,
                'trip_log' => $trip[$ts] ?? null,
                'water_level' => $water[$ts] ?? null,
                'fuel_level' => $fuel[$ts] ?? null,
                'wind_speed' => $tw['speed'],
                'wind_direction' => $tw['direction'],
                'course' => $cogDeg ?? $headingDeg,
                'latitude' => $lat[$ts] ?? null,
                'longitude' => $lng[$ts] ?? null,
                'pressure' => $pressure[$ts] ?? null,
                'wp_distance' => $wpDist[$ts] ?? null,
                'wp_ttg' => $wpTtg[$ts] ?? null,
                'battery_soc' => $soc[$ts] ?? null,
            ];

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

    /**
     * Compute true wind from a legacy boat-metric array carrying canonical display-unit
     * values: apparent wind speed (kn), apparent wind angle (deg), STW (kn), true
     * heading (deg). Converts back to raw SI units for the cosine-rule calculation.
     *
     * @param  array<string, ?float>  $metrics
     * @return array{speed: ?float, direction: ?float}
     */
    private function trueWindFromCanonical(array $metrics): array
    {
        return $this->trueWindFromCanonicalValues(
            $metrics['wind_speed_apparent'] ?? null,
            $metrics['wind_angle_apparent'] ?? null,
            $metrics['speed_stw'] ?? null,
            $metrics['heading'] ?? null,
        );
    }

    /**
     * @return array{speed: ?float, direction: ?float}
     */
    private function trueWindFromCanonicalValues(?float $awsKn, ?float $awaDeg, ?float $stwKn, ?float $headingDeg): array
    {
        $aws = $awsKn !== null ? $awsKn / 1.94384 : null;
        $awa = $awaDeg !== null ? deg2rad($awaDeg) : null;
        $stw = $stwKn !== null ? $stwKn / 1.94384 : null;
        $heading = $headingDeg !== null ? deg2rad($headingDeg) : null;

        return $this->calculateTrueWind($aws, $awa, $stw, $heading);
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
        $values = $this->readLegacyKeys([
            'wind_speed_apparent' => 'wind_speed_apparent',
            'wind_angle_apparent' => 'wind_angle_apparent',
            'speed_stw' => 'speed_stw',
            'heading' => 'heading_true',
        ]);

        $tw = $this->trueWindFromCanonical($values);

        return ($tw['speed'] !== null) ? $tw : null;
    }

    public function getTrueWindSeries(string $field, ?string $duration, string $step, ?int $start = null, ?int $end = null): array
    {
        $aws = $this->seriesByTs($this->canonical->readRange('wind_speed_apparent', $duration, $step, $start, $end));
        $awa = $this->seriesByTs($this->canonical->readRange('wind_angle_apparent', $duration, $step, $start, $end));
        $stw = $this->seriesByTs($this->canonical->readRange('speed_stw', $duration, $step, $start, $end));
        $hdg = $this->seriesByTs($this->canonical->readRange('heading_true', $duration, $step, $start, $end));

        $result = [];
        foreach ($aws as $ts => $awsValue) {
            if (! isset($awa[$ts], $stw[$ts], $hdg[$ts])) {
                continue;
            }

            $tw = $this->trueWindFromCanonicalValues($awsValue, $awa[$ts], $stw[$ts], $hdg[$ts]);

            if ($tw[$field] !== null) {
                $result[] = ['timestamp' => $ts, 'value' => $tw[$field]];
            }
        }

        return $result;
    }

    /**
     * Read a set of legacy output keys from the canonical reader (live).
     *
     * @param  array<string, string>  $keyMap  legacy key => canonical key
     * @return array<string, ?float>
     */
    private function readLegacyKeys(array $keyMap): array
    {
        $envelopes = $this->canonical->readMany(array_values(array_unique($keyMap)));

        $out = [];
        foreach ($keyMap as $legacyKey => $canonicalKey) {
            $out[$legacyKey] = $envelopes[$canonicalKey]['value'] ?? null;
        }

        return $out;
    }

    /**
     * Read a set of legacy output keys at a past instant.
     *
     * @param  array<string, string>  $keyMap  legacy key => canonical key
     * @return array<string, ?float>
     */
    private function readLegacyKeysAt(array $keyMap, int $timestamp): array
    {
        $out = [];
        $cache = [];
        foreach ($keyMap as $legacyKey => $canonicalKey) {
            if (! array_key_exists($canonicalKey, $cache)) {
                $cache[$canonicalKey] = $this->canonical->readAt($canonicalKey, $timestamp);
            }
            $out[$legacyKey] = $cache[$canonicalKey]['value'] ?? null;
        }

        return $out;
    }

    /**
     * @param  array<int, array{t: int, v: float}>  $series
     * @return array<int, float>
     */
    private function seriesByTs(array $series): array
    {
        $out = [];
        foreach ($series as $point) {
            $out[$point['t']] = $point['v'];
        }

        return $out;
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
