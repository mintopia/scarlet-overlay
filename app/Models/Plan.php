<?php

namespace App\Models;

use App\Support\SlugGenerator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Str;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = ['slug', 'title'];

    protected static function booted(): void
    {
        static::creating(function (Plan $plan) {
            if (empty($plan->slug)) {
                $plan->slug = static::generateUniqueSlug($plan->title);
            }
        });
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
        $this->share_token = $token;
        $this->save();

        return $token;
    }

    public function toDetailArray(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'share_token' => $this->share_token ?? null,
            'created_at' => $this->created_at->toIso8601String(),
            'groups' => $this->groups->map(fn ($g) => [
                'id' => $g->id,
                'name' => $g->name,
                'color_index' => $g->color_index,
                'sort_order' => $g->sort_order ?? 0,
                'routes' => $g->routes->map(fn ($r) => [
                    'id' => $r->id,
                    'name' => $r->name,
                    'distance_nm' => (float) $r->distance_nm,
                    'is_enabled' => $r->is_enabled,
                    'color_index' => $r->color_index,
                    'track_points' => $r->track_points,
                    'waypoints' => $r->waypoints,
                ]),
            ]),
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected static function generateUniqueSlug(string $title): string
    {
        return SlugGenerator::unique(static::class, $title);
    }
}
