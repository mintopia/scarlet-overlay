<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class ForceReload implements ShouldBroadcast
{
    public function broadcastOn(): Channel
    {
        return new Channel('metrics');
    }

    public function broadcastAs(): string
    {
        return 'force-reload';
    }
}
