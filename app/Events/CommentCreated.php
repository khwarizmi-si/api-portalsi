<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Comment;

class CommentCreated implements ShouldBroadcast
{
  use Dispatchable, InteractsWithSockets, SerializesModels;

  public $comment;
  public $postId;

  /**
   * Create a new event instance.
   */
  public function __construct(Comment $comment)
  {
    $this->comment = $comment;
    $this->postId = $comment->post_id;
  }

  /**
   * Get the channels the event should broadcast on.
   */
  public function broadcastOn(): array
  {
    return [
      new PrivateChannel('post.' . $this->postId),
      new PrivateChannel('user.' . $this->comment->post->user_id) // Notify post owner
    ];
  }

  /**
   * The event's broadcast name.
   */
  public function broadcastAs(): string
  {
    return 'comment.created';
  }

  /**
   * Get the data to broadcast.
   */
  public function broadcastWith(): array
  {
    return [
      'id' => $this->comment->id,
      'post_id' => $this->comment->post_id,
      'user_id' => $this->comment->user_id,
      'user_name' => $this->comment->user->full_name ?? $this->comment->user->username,
      'user_avatar' => $this->comment->user->profile_picture_url,
      'content' => $this->comment->content,
      'parent_id' => $this->comment->parent_id,
      'created_at' => $this->comment->created_at->toISOString(),
      'updated_at' => $this->comment->updated_at->toISOString(),
    ];
  }
}
