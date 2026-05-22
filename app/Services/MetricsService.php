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
        return $this->prometheus->queryMultiple(
            config('scarlet.metrics.mappings.boat')
        );
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

            $sog = $sogByTs->get($ts);

            $track[] = [
                $point['value'],
                $lng['value'],
                $sog['value'] ?? 0,
            ];
        }

        return $track;
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
