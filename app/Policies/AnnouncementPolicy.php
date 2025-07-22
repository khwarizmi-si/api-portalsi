<?php

namespace App\Policies;

use App\Models\Announcement;
use App\Models\User;

class AnnouncementPolicy
{
    public function update(User $user, Announcement $announcement): bool
    {
        return $user->user_id === $announcement->created_by;
    }

    public function delete(User $user, Announcement $announcement): bool
    {
        return $user->user_id === $announcement->created_by;
    }
}
