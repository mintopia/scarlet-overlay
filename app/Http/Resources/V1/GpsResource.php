<?php

namespace App\Http\Resources\V1;

use App\Models\Gps;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Gps
 */
class GpsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'timestamp' => $this->timestamp?->toIso8601String(),
            'latitude' => $this->latitude ?? 0,
            'longitude' => $this->longitude ?? 0,
            'valid' => $this->valid,
            'satellites' => $this->satellites,
            'hdop' => $this->hdop,
            'speed' => $this->speed,
            'course' => $this->course,
        ];
    }
}
