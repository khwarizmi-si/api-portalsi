<?php

namespace App\Events;

use App\Models\DirectMessage;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewDirectMessage implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public $message;

    public function __construct(DirectMessage $message)
    {
        $this->message = $message;
    }

    public function broadcastOn(): array
    {
        // Urutkan ID user untuk nama channel yang konsisten
        $userIds = [$this->message->sender_id, $this->message->receiver_id];
        sort($userIds);
        $conversationId = implode('-', $userIds);

        return [new PrivateChannel('dm.' . $conversationId)];
    }

    public function broadcastAs(): string
    {
        return 'dm.new';
    }
}