<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Str;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = ['slug', 'title', 'user_id', 'share_token'];

    protected static function booted(): void
    {
        static::creating(function (Plan $plan) {
            if (empty($plan->slug)) {
                $plan->slug = static::generateUniqueSlug($plan->title);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function groups(): HasMany
    {
        return $this->hasMany(PlanGroup::class)->orderBy('sort_order');
    }

    public function routes(): HasManyThrough
    {
        return $this->hasManyThrough(PlanRoute::class, PlanGroup::class);
    }

    public function generateShareToken(): string
    {
        $token = Str::random(32);
        $this->update(['share_token' => $token]);

        return $token;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected static function generateUniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $counter = 1;

        while (static::where('slug', $slug)->exists()) {
            $counter++;
            $slug = $base.'-'.$counter;
        }

        return $slug;
    }
}
