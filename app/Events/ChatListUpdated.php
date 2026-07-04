<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatListUpdated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    // Payload akan berisi data percakapan yang sudah diformat
    public $conversation;

    public function __construct(array $conversationData)
    {
        $this->conversation = $conversationData;
    }

    public function broadcastOn(): array
    {
        // Kirim ke channel personal penerima
        // ID penerima harus ada di dalam data percakapan
        $recipientId = $this->conversation['recipient_id'];

        return [new PrivateChannel('App.Models.User.'.$recipientId)];
    }

    public function broadcastAs(): string
    {
        return 'chat.updated';
    }
}
