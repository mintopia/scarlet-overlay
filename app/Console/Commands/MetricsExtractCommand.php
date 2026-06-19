<?php

namespace App\Console\Commands;

use App\Services\CanonicalCatalog;
use App\Support\NavigationMath;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class MetricsExtractCommand extends Command
{
    /**
     * Replay-file metric key (legacy boat-metric name) → canonical catalog key.
     * The replay file format is keyed by these legacy names for MetricsFakeCommand.
     *
     * @var array<string, array<string, string>>
     */
    private const GROUP_KEYS = [
        'boat' => [
            'speed_sog' => 'speed_sog',
            'speed_stw' => 'speed_stw',
            'heading' => 'heading_true',
            'cog' => 'cog',
            'depth' => 'depth_below_surface',
            'heel' => 'heel',
            'pitch' => 'pitch',
            'trip_log' => 'trip_log',
            'nav_wp_distance' => 'wp_distance',
            'nav_wp_ttg' => 'wp_ttg',
            'vmg' => 'vmg',
            'wind_speed_apparent' => 'wind_speed_apparent',
            'wind_angle_apparent' => 'wind_angle_apparent',
            'magnetic_variation' => 'magnetic_variation',
            'heading_magnetic' => 'heading_magnetic',
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
        ],
        'tracker' => [
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
        ],
        'gps' => [
            'gps_latitude' => 'position_latitude',
            'gps_longitude' => 'position_longitude',
            'gps_altitude' => 'gps_altitude',
            'gps_satellites' => 'gps_satellites',
            'gps_hdop' => 'gps_hdop',
            'gps_speed' => 'gps_speed',
            'gps_heading' => 'gps_heading',
        ],
    ];

    protected $signature = 'metrics:extract
        {--from= : Start time (e.g. "2026-05-23 21:00:00+02:00")}
        {--duration=1h : Duration to extract (e.g. "1h", "30m")}
        {--step=15 : Step in seconds}
        {--output=storage/app/metrics-replay.json : Output file}';

    protected $description = 'Extract historical metrics from Prometheus into a replay file';

    public function handle(): int
    {
        $promUrl = config('scarlet.metrics.prometheus_url');
        $from = $this->option('from')
            ? Carbon::parse($this->option('from'))
            : now()->subHour();

        $durationStr = $this->option('duration');
        preg_match('/^(\d+)(h|m|s)$/', $durationStr, $m);
        $durationSec = match ($m[2] ?? 's') {
            'h' => (int) $m[1] * 3600,
            'm' => (int) $m[1] * 60,
            's' => (int) $m[1],
        };

        $step = (int) $this->option('step');
        $output = $this->option('output');

        $startTs = $from->timestamp;
        $endTs = $startTs + $durationSec;
        $totalPoints = (int) ceil($durationSec / $step);

        $this->info("Extracting from {$from->toDateTimeString()} for {$durationStr} ({$totalPoints} points at {$step}s)");
        $this->info("Prometheus: {$promUrl}");
        $this->info("Output: {$output}");
        $this->newLine();

        $queries = $this->buildQueryList();
        $this->info(count($queries).' metrics to extract');

        $seriesData = [];
        $bar = $this->output->createProgressBar(count($queries));
        $bar->setFormat(' %current%/%max% [%bar%] %message%');

        foreach ($queries as $key => $promql) {
            $bar->setMessage($key);
            $bar->advance();

            try {
                $response = Http::timeout(120)->get("{$promUrl}/api/v1/query_range", [
                    'query' => $promql,
                    'start' => $startTs,
                    'end' => $endTs,
                    'step' => $step,
                ]);

                if ($response->ok()) {
                    $result = $response->json('data.result');
                    if (! empty($result)) {
                        $values = collect($result[0]['values'])
                            ->mapWithKeys(fn ($v) => [(int) $v[0] => (float) $v[1]])
                            ->all();
                        $seriesData[$key] = $values;
                    }
                }
            } catch (\Throwable $e) {
                $this->newLine();
                $this->warn("  Failed {$key}: {$e->getMessage()}");
            }
        }

        $bar->finish();
        $this->newLine(2);

        $filledCount = count($seriesData);
        $this->info("{$filledCount}/".count($queries).' metrics have data');

        // Build the timeline: for each timestamp, assemble the full metrics snapshot
        $timestamps = range($startTs, $endTs, $step);
        $timeline = [];

        foreach ($timestamps as $ts) {
            $snapshot = [];
            foreach ($queries as $key => $promql) {
                $snapshot[$key] = $seriesData[$key][$ts] ?? null;
            }
            $timeline[] = [
                'timestamp' => $ts,
                'time' => date('c', $ts),
                'metrics' => $snapshot,
            ];
        }

        // Calculate true wind for each frame using cosine rule
        foreach ($timeline as &$frame) {
            $m = $frame['metrics'];

            $awsRaw = $m['boat._aws'] ?? null;
            $awaRaw = $m['boat._awa'] ?? null;
            $stwRaw = $m['boat._stw'] ?? null;
            $hdgRaw = $m['boat._heading'] ?? null;

            // Try raw SI values first (m/s, radians)
            if ($awsRaw !== null && $awaRaw !== null && $stwRaw !== null && $hdgRaw !== null) {
                $tw = NavigationMath::calculateTrueWind($awsRaw, $awaRaw, $stwRaw, $hdgRaw);
                $frame['metrics']['boat.wind_speed_true'] = $tw['speed'] * 1.94384;
                $frame['metrics']['boat.wind_direction_true'] = rad2deg($tw['direction']);
            }
            // Fallback: use converted values (knots, degrees)
            elseif (($m['boat.wind_speed_apparent'] ?? null) !== null
                && ($m['boat.wind_angle_apparent'] ?? null) !== null
                && ($m['boat.speed_stw'] ?? null) !== null
                && ($m['boat.heading'] ?? null) !== null) {

                $awsKn = $m['boat.wind_speed_apparent'];
                $awaDeg = $m['boat.wind_angle_apparent'];
                $stwKn = $m['boat.speed_stw'];
                $hdgDeg = $m['boat.heading'];

                $awaRad = deg2rad($awaDeg);
                $tw = NavigationMath::calculateTrueWind($awsKn, $awaRad, $stwKn, deg2rad($hdgDeg));
                $frame['metrics']['boat.wind_speed_true'] = $tw['speed'];
                $frame['metrics']['boat.wind_direction_true'] = rad2deg($tw['direction']);
            }

            unset($frame['metrics']['boat._aws']);
            unset($frame['metrics']['boat._awa']);
            unset($frame['metrics']['boat._stw']);
            unset($frame['metrics']['boat._heading']);
        }
        unset($frame);

        $payload = [
            'extracted_at' => now()->toIso8601String(),
            'source' => $promUrl,
            'start' => date('c', $startTs),
            'end' => date('c', $endTs),
            'step' => $step,
            'frames' => count($timeline),
            'metrics_available' => $filledCount,
            'timeline' => $timeline,
        ];

        $path = base_path($output);
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $size = round(filesize($path) / 1024);
        $this->info("Written {$size}KB to {$output}");
        $this->info("{$totalPoints} frames, {$filledCount} metrics");

        return self::SUCCESS;
    }

    private function buildQueryList(): array
    {
        $queries = [];
        $catalog = app(CanonicalCatalog::class)->all();

        foreach (self::GROUP_KEYS as $group => $keyMap) {
            foreach ($keyMap as $legacyKey => $canonicalKey) {
                $def = $catalog[$canonicalKey] ?? null;
                $selector = $def['sources'][0]['selector'] ?? null;
                if ($selector !== null) {
                    $queries["{$group}.{$legacyKey}"] = $selector;
                }
            }
        }

        $queries['weather.temperature'] = 'scarlet_weather_temperature_celsius';
        $queries['weather.pressure'] = 'scarlet_weather_pressure_hpa';
        $queries['weather.wind_speed'] = 'scarlet_weather_wind_speed_kn';
        $queries['weather.wind_direction'] = 'scarlet_weather_wind_direction_deg';
        $queries['weather.wave_height'] = 'scarlet_weather_wave_height_m';
        $queries['weather.wave_period'] = 'scarlet_weather_wave_period_s';
        $queries['weather.wave_direction'] = 'scarlet_weather_wave_direction_deg';
        $queries['weather.current_speed'] = 'scarlet_weather_current_speed_kn';
        $queries['weather.current_direction'] = 'scarlet_weather_current_direction_deg';
        $queries['weather.sea_temperature'] = 'scarlet_weather_sea_temperature_celsius';

        $queries['srt.up'] = 'scarlet_srt_up';
        $queries['srt.publisher_connected'] = 'scarlet_srt_publisher_connected';
        $queries['srt.publisher_bitrate'] = 'scarlet_srt_publisher_bitrate_bps';
        $queries['srt.publisher_rtt'] = 'scarlet_srt_publisher_rtt_ms';
        $queries['srt.publisher_drops'] = 'scarlet_srt_publisher_dropped_packets_total';
        $queries['srt.publisher_latency'] = 'scarlet_srt_publisher_latency_ms';

        return $queries;
    }
}
