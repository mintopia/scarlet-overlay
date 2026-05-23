<?php

namespace App\Http\Resources\V1;

use App\Models\Weather;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Weather
 */
class WeatherResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'timezone' => $this->timezone,
            'code' => $this->wmoCode,
            'summary' => $this->getWeatherSummary(),
            'conditionText' => $this->getConditionText(),
            'temp' => $this->temp,
            'seaTemp' => $this->seaTemp,
            'daytime' => $this->daytime,
            'pressure' => $this->pressure,
            'wind' => (object)[
                'speed' => $this->windSpeed,
                'gusts' => $this->windGusts,
                'direction' => $this->windDirection,
            ],
            'waves' => (object)[
                'height' => $this->waveHeight,
                'direction' => $this->waveDirection,
                'period' => $this->wavePeriod,
            ],
            'current' => (object)[
                'speed' => round($this->current * 1.94384, 1),
                'direction' => $this->currentDirection,
            ],
        ];
    }
}
