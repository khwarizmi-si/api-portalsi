<?php

namespace App\Events;

use App\Models\Comment;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CommentUpdated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public $comment;

    public function __construct(Comment $comment)
    {
        $this->comment = $comment->load('user');
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('post.' . $this->comment->post_id)];
    }

    public function broadcastAs(): string
    {
        return 'comment.updated';
    }
}