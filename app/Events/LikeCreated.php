<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Like;

class LikeCreated implements ShouldBroadcast
{
  use Dispatchable, InteractsWithSockets, SerializesModels;

  public $like;
  public $postId;

  /**
   * Create a new event instance.
   */
  public function __construct(Like $like)
  {
    $this->like = $like;
    $this->postId = $like->post_id;
  }

  /**
   * Get the channels the event should broadcast on.
   */
  public function broadcastOn(): array
  {
    return [
      new PrivateChannel('post.' . $this->postId),
      new PrivateChannel('user.' . $this->like->post->user_id) // Notify post owner
    ];
  }

  /**
   * The event's broadcast name.
   */
  public function broadcastAs(): string
  {
    return 'like.created';
  }

  /**
   * Get the data to broadcast.
   */
  public function broadcastWith(): array
  {
    return [
      'id' => $this->like->id,
      'post_id' => $this->like->post_id,
      'user_id' => $this->like->user_id,
      'user_name' => $this->like->user->full_name ?? $this->like->user->username,
      'user_avatar' => $this->like->user->profile_picture_url,
      'created_at' => $this->like->created_at->toISOString(),
    ];
  }
}
