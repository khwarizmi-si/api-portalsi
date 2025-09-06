<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TestEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $message;

    /**
     * Create a new event instance.
     */
    public function __construct(string $message)
    {
        $this->message = $message;
    }

    /**
     * The event's broadcast channel.
     */
    public function broadcastOn(): Channel|array
    {
        // bisa ganti ke PresenceChannel/PrivateChannel sesuai kebutuhan
        return new Channel('test-channel');
    }

    /**
     * Nama event saat broadcast.
     */
    public function broadcastAs(): string
    {
        return 'test-event';
    }

    /**
     * Data yang dikirim ke frontend.
     */
    public function broadcastWith(): array
    {
        return [
            'message' => $this->message,
        ];
    }
}
