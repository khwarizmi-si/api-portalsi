<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TestEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;

    public function __construct($message)
    {
        $this->message = $message;
    }

    // Channel publik
    public function broadcastOn()
    {
        return new Channel('test-channel');
    }

    // Nama event di frontend (default: nama class, tapi bisa override)
    public function broadcastAs()
    {
        return 'test-event';
    }
}
