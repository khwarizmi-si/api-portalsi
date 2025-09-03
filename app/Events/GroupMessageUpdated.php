<?php

namespace App\Events;

use App\Models\GroupMessage;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GroupMessageUpdated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public $message;

    public function __construct(GroupMessage $message)
    {
        $this->message = $message->load('sender');
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('group.' . $this->message->group_id)];
    }

    public function broadcastAs(): string
    {
        return 'group.updated';
    }
}