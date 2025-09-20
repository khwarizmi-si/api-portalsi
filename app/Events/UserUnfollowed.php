<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserUnfollowed implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public $unfollower;
    public $unfollowedUser;

    public function __construct(User $unfollower, User $unfollowedUser)
    {
        $this->unfollower = $unfollower;
        $this->unfollowedUser = $unfollowedUser;
    }

    public function broadcastOn(): array
    {
        // Broadcast ke user yang di-unfollow
        return [
            new PrivateChannel('user.' . $this->unfollowedUser->user_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'user.unfollowed';
    }

    public function broadcastWith(): array
    {
        return [
            'unfollower_id' => $this->unfollower->user_id,
            'unfollower_username' => $this->unfollower->username,
            'unfollower_name' => $this->unfollower->full_name,
            'unfollower_avatar' => $this->unfollower->profile_picture_url,
        ];
    }
}