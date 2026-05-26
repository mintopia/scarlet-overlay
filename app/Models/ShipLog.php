<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'journey_id',
        'recorded_at',
        'latitude',
        'longitude',
        'course',
        'trip_log',
        'wind_speed',
        'wind_direction',
        'pressure',
        'wp_distance',
        'wp_ttg',
        'battery_soc',
        'water_level',
        'fuel_level',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
        ];
    }

    public function journey(): BelongsTo
    {
        return $this->belongsTo(Journey::class);
    }
}
