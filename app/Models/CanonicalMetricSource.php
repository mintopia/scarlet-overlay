<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CanonicalMetricSource extends Model
{
    protected $fillable = [
        'canonical_metric_id', 'priority', 'source_metric_name', 'label_matchers',
        'source_class', 'source_kind', 'select_fn', 'unit_transform', 'staleness_threshold_s',
    ];

    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'label_matchers' => 'array',
            'unit_transform' => 'array',
            'staleness_threshold_s' => 'integer',
        ];
    }

    public function metric(): BelongsTo
    {
        return $this->belongsTo(CanonicalMetric::class, 'canonical_metric_id');
    }
}
