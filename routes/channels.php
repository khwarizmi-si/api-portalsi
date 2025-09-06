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

// ===== NEW WEBSOCKET CHANNELS =====

// Private channel for user notifications
Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->user_id === (int) $userId;
});

// Private channel for direct messages
Broadcast::channel('chat.direct.{roomId}', function ($user, $roomId) {
    $userIds = explode('-', $roomId);
    return in_array($user->user_id, $userIds);
});

// Private channel for group messages
Broadcast::channel('chat.group.{groupId}', function ($user, $groupId) {
    return Group::find($groupId)->members()->where('user_id', $user->user_id)->exists();
});

// Presence channel for story viewers
Broadcast::channel('story.{storyId}', function ($user, $storyId) {
    // Allow any authenticated user to join story presence channel
    return $user !== null;
});

// Private channel for post updates (likes, comments)
Broadcast::channel('post.{postId}', function ($user, $postId) {
    return $user !== null;
});