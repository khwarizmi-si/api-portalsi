<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\User;

class UserOnlineStatus implements ShouldBroadcast
{
  use Dispatchable, InteractsWithSockets, SerializesModels;

  public $user;
  public $isOnline;

  /**
   * Create a new event instance.
   */
  public function __construct(User $user, bool $isOnline)
  {
    $this->user = $user;
    $this->isOnline = $isOnline;
  }

  /**
   * Get the channels the event should broadcast on.
   */
  public function broadcastOn(): array
  {
    // Broadcast to all followers of this user
    $followers = $this->user->followers()->pluck('follower_id')->toArray();

    $channels = [];
    foreach ($followers as $followerId) {
      $channels[] = new PrivateChannel('user.' . $followerId);
    }

    return $channels;
  }

  /**
   * The event's broadcast name.
   */
  public function broadcastAs(): string
  {
    return 'user.status.updated';
  }

  /**
   * Get the data to broadcast.
   */
  public function broadcastWith(): array
  {
    return [
      'user_id' => $this->user->user_id,
      'username' => $this->user->username,
      'full_name' => $this->user->full_name,
      'profile_picture_url' => $this->user->profile_picture_url,
      'is_online' => $this->isOnline,
      'last_seen' => $this->isOnline ? now()->toISOString() : $this->user->last_seen?->toISOString(),
    ];
  }
}
