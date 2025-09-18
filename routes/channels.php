<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\Group;

// Channel default untuk user
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->user_id === (int) $id; // ganti ke $user->id kalau primary key default
});

// Channel untuk update komentar & like post
Broadcast::channel('post.{postId}', function ($user, $postId) {
    return $user !== null;
});

// Channel untuk Direct Message (private)
Broadcast::channel('dm.{conversationId}', function ($user, $conversationId) {
    $userIds = explode('-', $conversationId);
    return in_array($user->user_id, $userIds);
});

// Channel untuk update pesan di grup
Broadcast::channel('group.{group}', function ($user, Group $group) {
    return $group->members()->where('user_id', $user->user_id)->exists();
});

// Private channel untuk notifikasi user
Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->user_id === (int) $userId;
});

// Private channel direct chat
Broadcast::channel('chat.direct.{roomId}', function ($user, $roomId) {
    $userIds = explode('-', $roomId);
    return in_array($user->user_id, $userIds);
});

// Private channel group chat
Broadcast::channel('chat.group.{groupId}', function ($user, $groupId) {
    return Group::find($groupId)
        ?->members()
        ->where('user_id', $user->user_id)
        ->exists() ?? false;
});

// Presence channel untuk story viewers
Broadcast::channel('story.{storyId}', function ($user, $storyId) {
    return [
        'id' => $user->user_id,
        'name' => $user->username,
    ];
});

// Test channel (semua user login bisa akses)
Broadcast::channel('test-channel', function ($user) {
    return $user !== null;
});


// Channel untuk pengumuman publik
Broadcast::channel('announcements', function ($user) {
    // Semua user, termasuk yang tidak login (guest), bisa mendengarkan pengumuman
    // return true;

    // Atau, jika hanya user yang login yang bisa menerima pengumuman
    return $user !== null;
});