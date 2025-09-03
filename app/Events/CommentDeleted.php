<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CommentDeleted implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public $commentId;
    public $postId;

    public function __construct(int $commentId, int $postId)
    {
        $this->commentId = $commentId;
        $this->postId = $postId;
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('post.' . $this->postId)];
    }

    public function broadcastAs(): string
    {
        return 'comment.deleted';
    }
}