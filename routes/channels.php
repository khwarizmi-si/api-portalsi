<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\Group;

// 🔽 INI ADALAH KODE LAMA ANDA, TETAP DIPERLUKAN!
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->user_id === (int) $id; // (Pastikan menggunakan user_id jika itu primary key Anda)
});

// ➕ KODE TAMBAHAN UNTUK FITUR LAIN
// Channel untuk update komentar di sebuah post
Broadcast::channel('post.{postId}', function ($user, $postId) {
    return $user !== null;
});

// Channel untuk Direct Message antara 2 user
Broadcast::channel('dm.{conversationId}', function ($user, $conversationId) {
    $userIds = explode('-', $conversationId);
    return in_array($user->user_id, $userIds);
});

// Channel untuk update pesan di sebuah grup
Broadcast::channel('group.{group}', function ($user, Group $group) {
    return $group->members()->where('user_id', $user->user_id)->exists();
});