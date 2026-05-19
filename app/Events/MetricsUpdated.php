<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class MetricsUpdated implements ShouldBroadcast
{
    use Dispatchable;

    public function __construct(
        public array $boat,
        public array $tracker,
        public array $gps,
        public string $timestamp,
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
