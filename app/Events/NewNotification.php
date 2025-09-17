<?php

namespace App\Events;

use App\Models\Notification;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewNotification implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public $notification;

    public function __construct(Notification $notification)
    {
        // Muat semua relasi yang mungkin dibutuhkan di frontend
        $this->notification = $notification->load(['user', 'post', 'relatedComment.user']);
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('App.Models.User.' . $this->notification->recipient_id)];
    }

    public function broadcastAs(): string
    {
        return 'notification.new';
    }
}