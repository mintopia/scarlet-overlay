<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class MetricsExtractCommand extends Command
{
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

        // Calculate true wind for each frame
        // Use converted values (knots/degrees) when raw SI values unavailable
        foreach ($timeline as &$frame) {
            $m = $frame['metrics'];

            $awsRaw = $m['boat._aws'] ?? null;
            $awaRaw = $m['boat._awa'] ?? null;
            $stwRaw = $m['boat._stw'] ?? null;
            $hdgRaw = $m['boat._heading'] ?? null;

            // Try raw SI values first (m/s, radians)
            if ($awsRaw !== null && $awaRaw !== null && $stwRaw !== null && $hdgRaw !== null) {
                $u = $stwRaw * cos(0.0) + $awsRaw * cos($awaRaw);
                $v = $stwRaw * sin(0.0) + $awsRaw * sin($awaRaw);
                $tws = sqrt($u * $u + $v * $v);
                $twa = atan2($v, $u);
                $twd = fmod($hdgRaw + $twa + 2 * M_PI, 2 * M_PI);

                $frame['metrics']['boat.wind_speed_true'] = $tws * 1.94384;
                $frame['metrics']['boat.wind_direction_true'] = $twd * 180 / M_PI;
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

                $awaRad = $awaDeg * M_PI / 180;
                $u = $stwKn + $awsKn * cos($awaRad);
                $v = $awsKn * sin($awaRad);
                $tws = sqrt($u * $u + $v * $v);
                $twaRad = atan2($v, $u);
                $twdDeg = fmod($hdgDeg + $twaRad * 180 / M_PI + 360, 360);

                $frame['metrics']['boat.wind_speed_true'] = $tws;
                $frame['metrics']['boat.wind_direction_true'] = $twdDeg;
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

        foreach (['boat', 'tracker', 'gps'] as $group) {
            foreach (config("scarlet.metrics.mappings.{$group}") as $key => $promql) {
                if (is_string($promql)) {
                    $queries["{$group}.{$key}"] = $promql;
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
