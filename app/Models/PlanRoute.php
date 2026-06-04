<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanRoute extends Model
{
    use HasFactory;

    protected $fillable = [
        'plan_group_id', 'name', 'gpx_path', 'distance_nm',
        'is_enabled', 'color_index', 'track_points', 'waypoints', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'track_points' => 'array',
            'waypoints' => 'array',
            'distance_nm' => 'decimal:1',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(PlanGroup::class, 'plan_group_id');
    }
}
