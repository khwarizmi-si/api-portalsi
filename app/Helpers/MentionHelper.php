<?php

namespace App\Helpers;

use App\Models\User;
use App\Models\PostMention;
use App\Models\Notification;
use Illuminate\Support\Facades\Auth;

class MentionHelper
{
    public static function sendMentionNotifications($caption, $postId)
    {
        preg_match_all('/@([\w.]+)/', $caption, $matches);
        $mentionedUsernames = array_unique($matches[1]);

        foreach ($mentionedUsernames as $username) {
            $mentionedUser = User::where('username', $username)->first();

            if ($mentionedUser && $mentionedUser->user_id != Auth::id()) {
                // Simpan ke tabel post_mentions
                PostMention::create([
                    'post_id' => $postId,
                    'mentioned_user_id' => $mentionedUser->user_id,
                ]);

                // Kirim notifikasi
                Notification::create([
                    'recipient_id'     => $mentionedUser->user_id,
                    'type'             => 'mention',
                    'related_user_id'  => Auth::id(),
                    'related_post_id'  => $postId,
                    'created_at'       => now(),
                    'is_read'          => false,
                ]);
            }
        }
    }
}
