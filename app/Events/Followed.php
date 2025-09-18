<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class Followed implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public $follower;
    public $followedUser;

    public function __construct(User $follower, User $followedUser)
    {
        $this->follower = $follower;
        $this->followedUser = $followedUser;
    }

    public function broadcastOn(): array
    {
        // Channel privat untuk user yang baru di-follow
        return [
            new PrivateChannel('user.' . $this->followedUser->user_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'user.followed';
    }

    public function broadcastWith(): array
    {
        return [
            'follower_id' => $this->follower->user_id,
            'follower_username' => $this->follower->username,
            'follower_name' => $this->follower->full_name,
            'follower_avatar' => $this->follower->profile_picture_url,
        ];
    }
}