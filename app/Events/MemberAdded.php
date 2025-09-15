<?php

namespace App\Events;

use App\Models\Group;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MemberAdded implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    /**
     * Grup tempat anggota ditambahkan.
     * @var \App\Models\Group
     */
    public Group $group;

    /**
     * Pengguna baru yang ditambahkan ke grup.
     * @var \App\Models\User
     */
    public User $newUser;

    /**
     * Buat instance event baru.
     *
     * @param  \App\Models\Group  $group
     * @param  \App\Models\User  $newUser
     * @return void
     */
    public function __construct(Group $group, User $newUser)
    {
        $this->group = $group;
        $this->newUser = $newUser;
    }

    /**
     * Mendapatkan channel tempat event harus disiarkan.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        // Event ini dikirim ke channel privat milik grup.
        // Hanya anggota grup yang bisa mendengarkan.
        return [new PrivateChannel('group.' . $this->group->id)];
    }

    /**
     * Nama event yang akan disiarkan.
     *
     * @return string
     */
    public function broadcastAs(): string
    {
        return 'member.added';
    }
}