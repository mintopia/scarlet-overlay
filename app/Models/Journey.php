<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Journey extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug', 'title', 'from_port', 'to_port', 'started_at', 'ended_at',
        'status', 'is_public', 'gpx_route_path', 'route_waypoints', 'notes',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'is_public' => 'boolean',
        'route_waypoints' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Journey $journey) {
            if (empty($journey->slug)) {
                $journey->slug = static::generateUniqueSlug($journey->from_port, $journey->to_port);
            }
            if (empty($journey->title)) {
                $journey->title = $journey->from_port.' → '.$journey->to_port;
            }
        });

        static::saved(function () {
            Cache::forget('journey.active');
        });
    }

    public function trackPoints(): HasMany
    {
        return $this->hasMany(JourneyTrackPoint::class)->orderBy('recorded_at');
    }

    public function scopePlanned($query)
    {
        return $query->where('status', 'planned');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    public static function current(): ?self
    {
        return Cache::remember('journey.active', 60, function () {
            return static::active()->first();
        });
    }

    public static function planned(): ?self
    {
        return static::query()->where('status', 'planned')->first();
    }

    public static function lastPort(): ?string
    {
        $active = static::current();
        if ($active && $active->trackPoints()->exists()) {
            return $active->to_port;
        }

        return static::completed()
            ->orderByDesc('ended_at')
            ->value('to_port');
    }

    public static function createPlanned(string $fromPort, string $toPort, array $extra = []): self
    {
        if (static::active()->exists() || static::query()->where('status', 'planned')->exists()) {
            throw ValidationException::withMessages([
                'status' => 'A journey is already planned or active. End or delete it first.',
            ]);
        }

        return static::create(array_merge([
            'from_port' => $fromPort,
            'to_port' => $toPort,
            'status' => 'planned',
        ], $extra));
    }

    public function activate(): void
    {
        if ($this->status !== 'planned') {
            throw ValidationException::withMessages([
                'status' => 'Only planned journeys can be started.',
            ]);
        }

        if (static::active()->exists()) {
            throw ValidationException::withMessages([
                'status' => 'Another journey is already active.',
            ]);
        }

        $this->update([
            'status' => 'active',
            'started_at' => now(),
        ]);
    }

    public function getDurationAttribute(): ?float
    {
        if (! $this->started_at) {
            return null;
        }
        $end = $this->ended_at ?? now();

        return $this->started_at->diffInSeconds($end);
    }

    public function getDistanceAttribute(): float
    {
        $points = $this->trackPoints()->select(['latitude', 'longitude'])->get();
        if ($points->count() < 2) {
            return 0;
        }

        $total = 0;
        for ($i = 1; $i < $points->count(); $i++) {
            $total += static::haversineNm(
                $points[$i - 1]->latitude, $points[$i - 1]->longitude,
                $points[$i]->latitude, $points[$i]->longitude,
            );
        }

        return round($total, 1);
    }

    protected static function generateUniqueSlug(string $from, string $to): string
    {
        $base = Str::slug($from.' to '.$to);
        $slug = $base;
        $counter = 1;

        while (static::where('slug', $slug)->exists()) {
            $counter++;
            $slug = $base.'-'.$counter;
        }

        return $slug;
    }

    protected static function haversineNm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $r = 3440.065;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
