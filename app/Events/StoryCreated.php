<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Story;

class StoryCreated implements ShouldBroadcast
{
  use Dispatchable, InteractsWithSockets, SerializesModels;

  public $story;

  /**
   * Create a new event instance.
   */
  public function __construct(Story $story)
  {
    $this->story = $story;
  }

  /**
   * Get the channels the event should broadcast on.
   */
  public function broadcastOn(): array
  {
    // Broadcast to all followers of the story creator
    $followers = $this->story->user->followers()->pluck('follower_id')->toArray();

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
    return 'story.created';
  }

  /**
   * Get the data to broadcast.
   */
  public function broadcastWith(): array
  {
    return [
      'id' => $this->story->id,
      'user_id' => $this->story->user_id,
      'user_name' => $this->story->user->full_name ?? $this->story->user->username,
      'user_avatar' => $this->story->user->profile_picture_url,
      'media_url' => $this->story->media_url,
      'media_type' => $this->story->media_type,
      'caption' => $this->story->caption,
      'expires_at' => $this->story->expires_at?->toISOString(),
      'created_at' => $this->story->created_at->toISOString(),
    ];
  }
}
