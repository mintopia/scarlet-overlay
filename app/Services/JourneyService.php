<?php

namespace App\Services;

use App\Models\Journey;
use App\Models\JourneyTrackPoint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class JourneyService
{
    const STATIONARY_THRESHOLD = 480;
    const STATIONARY_SPEED = 0.5;

    public function recordTrackPoint(Journey $journey, array $metrics): void
    {
        $boat = $metrics['boat'] ?? [];
        $gps = $metrics['gps'] ?? [];

        $lat = $gps['latitude'] ?? null;
        $lng = $gps['longitude'] ?? null;

        if ($lat === null || $lng === null || ($lat == 0 && $lng == 0)) {
            return;
        }

        $journey->trackPoints()->create([
            'recorded_at' => now(),
            'latitude' => $lat,
            'longitude' => $lng,
            'speed_sog' => $boat['speed_sog'] ?? null,
            'speed_stw' => $boat['speed_stw'] ?? null,
            'heading' => $boat['heading'] ?? null,
            'cog' => $boat['cog'] ?? null,
            'depth' => $boat['depth'] ?? null,
            'wind_speed_apparent' => $boat['wind_speed_apparent'] ?? null,
            'wind_angle_apparent' => $boat['wind_angle_apparent'] ?? null,
            'wind_speed_true' => $boat['wind_speed_true'] ?? null,
            'wind_direction_true' => $boat['wind_direction_true'] ?? null,
            'house_battery_voltage' => $boat['house_battery_voltage'] ?? null,
            'house_battery_current' => $boat['house_battery_current'] ?? null,
            'heel' => $boat['heel'] ?? null,
        ]);

        $speed = $boat['speed_sog'] ?? 0;

        if ($speed < self::STATIONARY_SPEED) {
            $count = Cache::increment('journey.stationary_count');
            if ($count >= self::STATIONARY_THRESHOLD) {
                $this->autoEndJourney($journey);
            }
        } else {
            Cache::put('journey.stationary_count', 0);
        }
    }

    public function endJourney(Journey $journey): void
    {
        $journey->update([
            'status' => 'completed',
            'ended_at' => now(),
        ]);
        Cache::forget('journey.stationary_count');
    }

    public function abandonJourney(Journey $journey): void
    {
        $journey->update([
            'status' => 'abandoned',
            'ended_at' => now(),
        ]);
        Cache::forget('journey.stationary_count');
    }

    protected function autoEndJourney(Journey $journey): void
    {
        $lastMoving = $journey->trackPoints()
            ->where('speed_sog', '>=', self::STATIONARY_SPEED)
            ->orderByDesc('recorded_at')
            ->first();

        $endedAt = $lastMoving?->recorded_at ?? now();

        $journey->update([
            'status' => 'completed',
            'ended_at' => $endedAt,
        ]);

        if ($lastMoving) {
            $journey->trackPoints()
                ->where('recorded_at', '>', $endedAt)
                ->delete();
        }

        Cache::forget('journey.stationary_count');
        Log::info("Journey #{$journey->id} auto-ended after 2 hours stationary");
    }
}
