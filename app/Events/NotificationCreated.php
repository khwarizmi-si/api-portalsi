<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Notification;

class NotificationCreated implements ShouldBroadcast
{
  use Dispatchable, InteractsWithSockets, SerializesModels;

  public $notification;

  /**
   * Create a new event instance.
   */
  public function __construct(Notification $notification)
  {
    $this->notification = $notification;
  }

  /**
   * Get the channels the event should broadcast on.
   */
  public function broadcastOn(): array
  {
    return [
      new PrivateChannel('user.' . $this->notification->recipient_id)
    ];
  }

  /**
   * The event's broadcast name.
   */
  public function broadcastAs(): string
  {
    return 'notification.created';
  }

  /**
   * Get the data to broadcast.
   */
  public function broadcastWith(): array
  {
    return [
      'id' => $this->notification->id,
      'type' => $this->notification->type,
      'title' => $this->notification->title,
      'message' => $this->notification->message,
      'data' => $this->notification->data,
      'sender_id' => $this->notification->sender_id,
      'sender_name' => $this->notification->sender->full_name ?? $this->notification->sender->username,
      'sender_avatar' => $this->notification->sender->profile_picture_url,
      'is_read' => $this->notification->is_read,
      'created_at' => $this->notification->created_at->toISOString(),
    ];
  }
}
