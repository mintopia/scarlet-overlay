<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class MetricsUpdated implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(
        public array $boat,
        public array $tracker,
        public array $gps,
        public ?array $weather,
        public array $settings,
        public ?array $sun,
        public string $timestamp,
        public array $canonical = [],
        public int $catalogVersion = 0,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('metrics');
    }

    public function broadcastAs(): string
    {
        return 'metrics.updated';
    }
}
