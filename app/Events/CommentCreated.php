<?php

// app/Events/CommentCreated.php

namespace App\Events;

use App\Models\Comment;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CommentCreated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public $comment;

    /**
     * Create a new event instance.
     */
    public function __construct(Comment $comment)
    {
        // PENTING: Muat relasi `post` untuk bisa mengakses gambar
        $this->comment = $comment->load(['user', 'post']);
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        // Mengirim ke channel post yang relevan dan channel user pemilik postingan
        return [
            new PrivateChannel('post.' . $this->comment->post_id),
            new PrivateChannel('user.' . $this->comment->post->user_id),
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
            'created_at' => $this->comment->created_at->toISOString(),
            // 👇 TAMBAHKAN BARIS INI
            'post_image_url' => $this->comment->post->media_url ?? null,
        ];
    }
}