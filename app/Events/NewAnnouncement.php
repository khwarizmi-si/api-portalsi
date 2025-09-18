<?php

namespace App\Events;

use App\Models\Announcement;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewAnnouncement implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $announcement;

    /**
     * Create a new event instance.
     */
    public function __construct(Announcement $announcement)
    {
        // Muat data pembuat pengumuman agar bisa langsung digunakan di frontend
        $this->announcement = $announcement->load('creator:user_id,full_name,username,profile_picture_url');
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        // Broadcast ke channel publik, karena pengumuman bisa dilihat semua orang
        return [
            new Channel('announcements')
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'announcement.created';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->announcement->id,
            'title' => $this->announcement->title,
            'content' => $this->announcement->content,
            'image_url' => $this->announcement->image_url,
            'poll_data' => $this->announcement->poll_data,
            'pinned' => $this->announcement->pinned,
            'created_by_user' => $this->announcement->creator,
            'created_at' => $this->announcement->created_at->toISOString(),
        ];
    }
}