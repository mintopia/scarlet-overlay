<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CanonicalMetric extends Model
{
    protected $fillable = [
        'key', 'label', 'group', 'storage_unit', 'display_unit', 'volatile',
        'trend_fn', 'trend_window', 'staleness_threshold_s', 'coverage_window_s',
        'coverage_min', 'valid_min', 'valid_max', 'reject_null_island', 'enabled', 'description',
    ];

    protected function casts(): array
    {
        return [
            'volatile' => 'boolean',
            'enabled' => 'boolean',
            'staleness_threshold_s' => 'integer',
            'coverage_window_s' => 'integer',
            'coverage_min' => 'float',
            'valid_min' => 'float',
            'valid_max' => 'float',
            'reject_null_island' => 'boolean',
        ];
    }

    public function sources(): HasMany
    {
        return $this->hasMany(CanonicalMetricSource::class)->orderBy('priority');
    }
}
