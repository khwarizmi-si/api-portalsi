<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\DirectMessage;
use App\Models\GroupMessage;

class MessageSent implements ShouldBroadcast
{
  use Dispatchable, InteractsWithSockets, SerializesModels;

  public $message;
  public $type; // 'direct' or 'group'
  public $roomId;

  /**
   * Create a new event instance.
   */
  public function __construct($message, string $type = 'direct')
  {
    $this->message = $message;
    $this->type = $type;

    if ($type === 'group') {
      $this->roomId = $message->group_id;
    } else {
      // For direct messages, create a consistent room ID
      $this->roomId = $this->createDirectMessageRoomId($message);
    }
  }

  /**
   * Get the channels the event should broadcast on.
   */
  public function broadcastOn(): array
  {
    if ($this->type === 'group') {
      return [
        new PrivateChannel('chat.group.' . $this->roomId)
      ];
    }

    return [
      new PrivateChannel('chat.direct.' . $this->roomId)
    ];
  }

  /**
   * The event's broadcast name.
   */
  public function broadcastAs(): string
  {
    return 'message.sent';
  }

  /**
   * Get the data to broadcast.
   */
  public function broadcastWith(): array
  {
    return [
      'id' => $this->message->id,
      'sender_id' => $this->message->sender_id,
      'sender_name' => $this->message->sender->full_name ?? $this->message->sender->username,
      'sender_avatar' => $this->message->sender->profile_picture_url,
      'content' => $this->message->content,
      'type' => $this->type,
      'room_id' => $this->roomId,
      'created_at' => $this->message->created_at->toISOString(),
      'updated_at' => $this->message->updated_at->toISOString(),
    ];
  }

  /**
   * Create a consistent room ID for direct messages
   */
  private function createDirectMessageRoomId($message): string
  {
    $userIds = [$message->sender_id, $message->receiver_id ?? $message->recipient_id];
    sort($userIds);
    return implode('-', $userIds);
  }
}
