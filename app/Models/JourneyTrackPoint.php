<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JourneyTrackPoint extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'journey_id', 'recorded_at', 'latitude', 'longitude',
        'speed_sog', 'speed_stw', 'heading', 'cog', 'depth',
        'wind_speed_apparent', 'wind_angle_apparent',
        'wind_speed_true', 'wind_direction_true',
        'house_battery_voltage', 'house_battery_current', 'heel',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function journey(): BelongsTo
    {
        return $this->belongsTo(Journey::class);
    }
}
