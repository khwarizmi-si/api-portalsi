<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    // GET /notifications/preferences — preferensi notifikasi in-app user login.
    public function preferences(Request $request)
    {
        return response()->json([
            'preferences' => Auth::user()->notificationPreferences(),
        ]);
    }

    // PUT /notifications/preferences — simpan preferensi notifikasi in-app.
    public function updatePreferences(Request $request)
    {
        $user = Auth::user();
        $validated = $request->validate([
            'new_post_reminders' => ['sometimes', 'in:all,mutual,off'],
            'likes' => ['sometimes', 'boolean'],
            'comments' => ['sometimes', 'boolean'],
            'mentions' => ['sometimes', 'boolean'],
            'follows' => ['sometimes', 'boolean'],
        ]);

        $prefs = array_merge($user->notificationPreferences(), $validated);
        $user->notification_preferences = $prefs;
        $user->save();

        return response()->json([
            'message' => 'Preferensi notifikasi disimpan.',
            'preferences' => $prefs,
        ]);
    }

    // 🔔 GET /notifications - Tampilkan semua notifikasi user login
    public function index(Request $request)
    {
        $user = Auth::user();

        $perPage = (int) $request->input('per_page', 15); // default 15
        $page = (int) $request->input('page', 1);

        $notificationsQuery = Notification::where('recipient_id', $user->user_id)
            ->orderBy('created_at', 'desc')
            ->with(['sender', 'post', 'comment', 'reply']);

        $paginated = $notificationsQuery->paginate($perPage, ['*'], 'page', $page);

        $notifications = $paginated->getCollection()->map(function ($notif) {
            return [
                'notification_id' => $notif->notification_id,
                'type' => $notif->type,
                'message' => $this->generateMessage($notif),
                'sender' => $notif->sender ? [
                    'user_id' => $notif->sender->user_id,
                    'username' => $notif->sender->username,
                    'full_name' => $notif->sender->full_name,
                    'profile_picture_url' => $notif->sender->profile_picture_url,
                    'role' => $notif->sender->role,
                    'is_verified' => (bool) $notif->sender->is_verified,
                ] : null,
                'related_post_id' => $notif->related_post_id,
                'related_story_id' => $notif->related_story_id,
                'is_read' => $notif->is_read,
                'created_at' => $notif->created_at,
            ];
        });

        $pagination = [
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'per_page' => $paginated->perPage(),
            'total' => $paginated->total(),
            'next_page_url' => $paginated->nextPageUrl(),
        ];

        return response()->json([
            'notifications' => $notifications,
            'pagination' => $pagination,
        ]);
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

    public function unreadCount()
    {
        return response()->json([
            'count' => Notification::where('recipient_id', Auth::id())->where('is_read', false)->count(),
        ]);
    }

    // 🔧 Pesan notifikasi dinamis
    private function generateMessage($notif)
    {
        $username = $notif->sender ? $notif->sender->username : 'Seseorang';

        // Ambil isi comment/reply kalau ada
        $commentText = null;
        if (in_array($notif->type, ['comment', 'reply']) && $notif->related_comment_id) {
            $comment = Comment::find($notif->related_comment_id);
            if ($comment) {
                $commentText = trim($comment->content) !== ''
                    ? $comment->content
                    : '(tidak ada isi komentar)';
            }
        }

        return match ($notif->type) {
            'follow' => "$username mulai mengikuti kamu",
            'follow_request' => "$username meminta mengikuti akun kamu",
            'follow_accepted' => "$username menerima permintaan mengikuti kamu",
            'like' => "$username menyukai postingan kamu",
            'comment' => "$username mengomentari postingan kamu: \"$commentText\"",
            'reply' => "$username membalas komentar kamu: \"$commentText\"",
            'mention' => "$username menyebut kamu di postingan atau komentar",
            'story_mention' => "$username menyebut kamu di cerita",
            'bio_mention' => "$username menyebut kamu di bio profil",
            'new_post' => "$username membagikan postingan baru",
            default => "$username melakukan aksi tidak dikenal"
        };
    }
}
