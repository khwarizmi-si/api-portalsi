<?php

namespace App\Helpers;

use App\Models\Notification;

class Notify
{
    /**
     * Kirim notifikasi ke user
     *
     * @param int $recipientId       ID user yang menerima notifikasi
     * @param string $type           Jenis notifikasi ('follow', 'like', 'comment', 'mention')
     * @param int|null $relatedUserId  User yang menyebabkan notifikasi (biasanya Auth::id())
     * @param int|null $relatedPostId  Jika berkaitan dengan post, isi post_id-nya
     */
    public static function send($recipientId, $type, $relatedUserId = null, $relatedPostId = null)
    {
        Notification::create([
            'recipient_id'     => $recipientId,
            'type'             => $type,
            'related_user_id'  => $relatedUserId,
            'related_post_id'  => $relatedPostId,
            'created_at'       => now(),
            'is_read'          => false,
        ]);
    }
}
