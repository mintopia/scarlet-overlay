<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanGroup extends Model
{
    use HasFactory;

    protected $fillable = ['plan_id', 'name', 'color_index', 'sort_order'];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function routes(): HasMany
    {
        return $this->hasMany(PlanRoute::class)->orderBy('sort_order');
    }

    public function nextRouteColorIndex(): int
    {
        return $this->routes()->count() % 4;
    }
}
