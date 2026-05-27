<?php

namespace App\Console\Commands;

use App\Events\MetricsUpdated;
use App\Models\BoatSetting;
use App\Services\MetricsService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class MetricsFakeCommand extends Command
{
    protected $signature = 'metrics:fake
        {--file= : Replay from extracted JSON file}
        {--from= : Start time for live Prometheus replay (e.g. "2026-05-23 21:00+02:00")}
        {--speed=1 : Playback speed multiplier}
        {--interval=15 : Seconds between updates}';

    protected $description = 'Replay metrics from a file or live Prometheus queries';

    public function handle(MetricsService $metrics): int
    {
        if ($this->option('file')) {
            return $this->replayFromFile();
        }

        return $this->replayFromPrometheus($metrics);
    }

    private function replayFromFile(): int
    {
        $path = base_path($this->option('file'));
        if (! file_exists($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $data = json_decode(file_get_contents($path), true);
        $timeline = $data['timeline'] ?? [];
        $interval = (int) $this->option('interval');
        $speed = max(0.1, (float) $this->option('speed'));

        $this->info("Replaying {$data['frames']} frames from {$data['start']}");
        $this->info("Step: {$data['step']}s, Speed: {$speed}x, Interval: {$interval}s");
        $this->newLine();

        $frameIndex = 0;
        $stepsPerTick = max(1, (int) round(($interval * $speed) / $data['step']));

        while (true) {
            if ($frameIndex >= count($timeline)) {
                $this->info('Replay complete, looping...');
                $frameIndex = 0;
            }

            $frame = $timeline[$frameIndex];
            $m = $frame['metrics'];

            $boat = $this->extractGroup($m, 'boat.');
            $tracker = $this->extractGroup($m, 'tracker.');
            $gps = $this->extractGroup($m, 'gps.');
            $weather = $this->extractGroup($m, 'weather.');
            $srt = $this->extractGroup($m, 'srt.');

            $tracker['battery_percent'] = $this->voltageToPct($tracker['battery_voltage'] ?? null);

            if (! empty(array_filter($weather, fn ($v) => $v !== null))) {
                $weather['condition'] = $this->deriveCondition($weather);
                $weather['summary'] = $this->deriveSummary($weather);
                $weather['icon'] = $this->deriveIcon($weather['summary']);
                $weather['sea_temperature'] = $weather['sea_temperature'] ?? $boat['water_temp'] ?? null;
            } else {
                $weather = $this->syntheticWeather($frameIndex, $boat['water_temp'] ?? null);
            }

            $settings = [
                'boat_name' => BoatSetting::getValue('boat_name', config('scarlet.name')),
                'port_name' => BoatSetting::getValue('port_name', ''),
            ];

            MetricsUpdated::dispatch(
                $boat,
                $tracker,
                $gps,
                $weather,
                $settings,
                $frame['time'],
            );

            $sog = $boat['speed_sog'] ?? null;
            $hdg = $boat['heading'] ?? null;
            $tws = $boat['wind_speed_true'] ?? null;
            $lat = $gps['latitude'] ?? null;

            $this->line(sprintf(
                'Frame %d/%d [%s] SOG: %s kn  HDG: %s°  TWS: %s kn  Lat: %s',
                $frameIndex + 1,
                count($timeline),
                Carbon::parse($frame['time'])->format('H:i:s'),
                $sog !== null ? number_format($sog, 1) : '—',
                $hdg !== null ? number_format($hdg, 0) : '—',
                $tws !== null ? number_format($tws, 1) : '—',
                $lat !== null ? number_format($lat, 4) : '—',
            ));

            $frameIndex += $stepsPerTick;
            sleep($interval);
        }
    }

    private function replayFromPrometheus(MetricsService $metrics): int
    {
        $interval = (int) $this->option('interval');
        $speed = max(0.1, (float) $this->option('speed'));

        $replayStart = $this->option('from')
            ? Carbon::parse($this->option('from'))
            : now()->subHour();

        $stepSeconds = (int) ($interval * $speed);

        $this->info("Replaying from {$replayStart->toDateTimeString()} at {$speed}x speed");
        $this->info("Each {$interval}s tick advances {$stepSeconds}s of historical data");
        $this->newLine();

        $tick = 0;
        $replayTs = $replayStart->timestamp;

        while (true) {
            $currentTs = $replayTs + ($tick * $stepSeconds);
            $currentTime = Carbon::createFromTimestamp($currentTs);

            try {
                $all = $metrics->getAllMetricsAt($currentTs);

                MetricsUpdated::dispatch(
                    $all['boat'],
                    $all['tracker'],
                    $all['gps'],
                    $all['weather'],
                    $all['settings'],
                    $all['timestamp'],
                );

                $sog = $all['boat']['speed_sog'] ?? null;
                $hdg = $all['boat']['heading'] ?? null;
                $tws = $all['boat']['wind_speed_true'] ?? null;

                $this->line(sprintf(
                    'Tick %d [%s] SOG: %s kn  HDG: %s°  TWS: %s kn',
                    $tick,
                    $currentTime->format('H:i:s'),
                    $sog !== null ? number_format($sog, 1) : '—',
                    $hdg !== null ? number_format($hdg, 0) : '—',
                    $tws !== null ? number_format($tws, 1) : '—',
                ));
            } catch (\Throwable $e) {
                $this->error("Tick {$tick}: {$e->getMessage()}");
            }

            $tick++;
            sleep($interval);
        }
    }

    private function extractGroup(array $metrics, string $prefix): array
    {
        $result = [];
        $prefixLen = strlen($prefix);
        foreach ($metrics as $key => $value) {
            if (str_starts_with($key, $prefix)) {
                $result[substr($key, $prefixLen)] = $value;
            }
        }

        return $result;
    }

    private function voltageToPct(?float $voltage): int
    {
        if ($voltage === null) {
            return 0;
        }

        return (int) min(100, max(0, (($voltage - 3.0) / (4.2 - 3.0)) * 100));
    }

    private function deriveCondition(array $weather): string
    {
        $wind = $weather['wind_speed'] ?? 0;
        $temp = $weather['temperature'] ?? 15;

        if ($wind > 30) {
            return 'Stormy';
        }
        if ($wind > 20) {
            return 'Very Windy';
        }
        if ($temp > 25) {
            return 'Clear';
        }

        return 'Partly Cloudy';
    }

    private function deriveSummary(array $weather): string
    {
        $wind = $weather['wind_speed'] ?? 0;
        $temp = $weather['temperature'] ?? 15;

        if ($wind > 30) {
            return 'thunderstorm';
        }
        if ($wind > 20) {
            return 'rain';
        }
        if ($temp > 25) {
            return 'day-sunny';
        }

        return 'cloud';
    }

    private function deriveIcon(string $summary): string
    {
        return match ($summary) {
            'day-sunny' => '☀️',
            'night-clear' => '🌙',
            'cloud' => '⛅',
            'cloudy' => '☁️',
            'rain' => '🌧️',
            'thunderstorm' => '⛈️',
            default => '🌤️',
        };
    }

    private function syntheticWeather(int $frameIndex, ?float $waterTemp): array
    {
        $t = $frameIndex * 0.01;

        return [
            'temperature' => round(18.5 + sin($t * 0.3) * 1.5, 1),
            'condition' => 'Partly Cloudy',
            'summary' => 'cloud',
            'icon' => '⛅',
            'sea_temperature' => $waterTemp ?? round(17.5 + sin($t * 0.1) * 0.5, 1),
            'wind_speed' => round(12 + sin($t * 0.5) * 3, 0),
            'wind_direction' => 'NE',
            'wave_height' => round(0.8 + sin($t * 0.2) * 0.3, 1),
            'wave_period' => 8,
            'current_speed' => round(0.3 + sin($t * 0.15) * 0.2, 1),
            'current_direction' => 270,
            'pressure' => round(1022 + sin($t * 0.05) * 3, 0),
        ];
    }
}
