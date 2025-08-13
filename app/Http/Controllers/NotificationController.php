<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    // 🔔 GET /notifications - Tampilkan semua notifikasi user login
    public function index()
    {
        $user = Auth::user();

        $notifications = Notification::where('recipient_id', $user->user_id)
            ->orderBy('created_at', 'desc')
            ->with(['sender', 'post'])
            ->get()
            ->map(function ($notif) {
                return [
                    'notification_id' => $notif->notification_id,
                    'type' => $notif->type,
                    'message' => $this->generateMessage($notif),
                    'sender' => $notif->sender ? [
                        'user_id' => $notif->sender->user_id,
                        'username' => $notif->sender->username,
                        'full_name' => $notif->sender->full_name,
                    ] : null,
                    'related_post_id' => $notif->related_post_id,
                    'is_read' => $notif->is_read,
                    'created_at' => $notif->created_at
                ];
            });

        return response()->json($notifications);
    }

    // 🔔 PATCH /notifications/{id}/read - Tandai satu notif sebagai dibaca
    public function markAsRead($id)
    {
        $user = Auth::user();

        $notif = Notification::where('notification_id', $id)
            ->where('recipient_id', $user->user_id)
            ->first();

        if (! $notif) {
            return response()->json(['error' => 'Notifikasi tidak ditemukan atau bukan milik Anda'], 404);
        }

        $notif->is_read = true;
        $notif->save();

        return response()->json(['message' => 'Notifikasi ditandai sebagai telah dibaca']);
    }

    // 🔔 PATCH /notifications/read/all - Tandai semua notif sebagai dibaca
    public function markAllAsRead()
    {
        $user = Auth::user();

        Notification::where('recipient_id', $user->user_id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['message' => 'Semua notifikasi ditandai sebagai telah dibaca']);
    }

    // 🔧 Pesan notifikasi dinamis
    private function generateMessage($notif)
    {
        $username = optional($notif->sender)->username ?? 'Seseorang';
        $commentText = optional($notif->comment)->content ?? '';
    
        return match ($notif->type) {
            'follow'  => "$username mulai mengikuti kamu",
            'like'    => "$username menyukai postingan kamu",
            'comment' => "$username mengomentari postingan kamu: \"$commentText\"",
            'reply'   => "$username membalas komentar kamu: \"$commentText\"",
            'mention' => "$username menyebut kamu",
            default   => "$username melakukan aksi tidak dikenal"
        };
    }
    
}
