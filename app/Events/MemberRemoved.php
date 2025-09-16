<?php

namespace App\Events;

use App\Models\Group;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MemberRemoved implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    /**
     * Grup tempat anggota dihapus.
     * @var \App\Models\Group
     */
    public Group $group;

    /**
     * Pengguna yang dihapus dari grup.
     * @var \App\Models\User
     */
    public User $removedUser;

    /**
     * Buat instance event baru.
     *
     * @param  \App\Models\Group  $group
     * @param  \App\Models\User  $removedUser
     * @return void
     */
    public function __construct(Group $group, User $removedUser)
    {
        $this->group = $group;
        $this->removedUser = $removedUser;
    }

    /**
     * Mendapatkan channel tempat event harus disiarkan.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        // Event ini juga dikirim ke channel privat milik grup.
        return [new PrivateChannel('group.' . $this->group->id)];
    }

    /**
     * Nama event yang akan disiarkan.
     *
     * @return string
     */
    public function broadcastAs(): string
    {
        return 'member.removed';
    }
}