<?php

namespace App\Events;

use App\Models\DirectMessage;
use App\Models\User; // <-- 1. TAMBAHKAN IMPORT USER
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewDirectMessage implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public $message;

    public $sender; // <-- 2. TAMBAHKAN PROPERTI BARU

    public function __construct(DirectMessage $message)
    {
        // 3. UBAH CONSTRUCTOR UNTUK MENGISI KEDUA PROPERTI
        $this->message = $message->load('sender');
        $this->sender = $this->message->sender;
    }

    public function broadcastOn(): array
    {
        // Urutkan ID user untuk nama channel yang konsisten
        $userIds = [$this->message->sender_id, $this->message->receiver_id];
        sort($userIds);
        $conversationId = implode('-', $userIds);

        return [new PrivateChannel('dm.'.$conversationId)];
    }

    public function broadcastAs(): string
    {
        return 'dm.new';
    }
}
