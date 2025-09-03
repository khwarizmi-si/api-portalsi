<?php

namespace App\Http\Controllers;

// 🔽 IMPORT SEMUA EVENT YANG DIPERLUKAN
use App\Events\NewGroupMessage;
use App\Events\GroupMessageUpdated;
use App\Events\NewNotification; // Jika Anda ingin notifikasi mention grup

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Events\ChatListUpdated;
use App\Models\Group;
use App\Models\GroupMessage;
use App\Models\GroupMessageMention;
use App\Models\User;
use App\Models\Notification; // Tambahkan ini jika membuat notifikasi

class GroupMessageController extends Controller
{
    public function store(Request $request, Group $group)
    {
        $user = Auth::user();

        if (!$group->members()->where('user_id', $user->user_id)->exists()) {
            return response()->json(['message' => 'Kamu bukan anggota grup ini.'], 403);
        }

        $request->validate([
            'content' => 'required|string',
            'media' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:51200',
        ]);

        $mediaUrl = null;
        if ($request->hasFile('media')) {
            $media = $request->file('media');
            $path = $media->store('group-media', 'public');
            $mediaUrl = asset('storage/' . $path);
        }
        
        $message = GroupMessage::create([
            'group_id' => $group->id,
            'sender_id' => $user->user_id,
            'content' => $request->content,
            'media_url' => $mediaUrl,
            'sent_at' => now(),
        ]);

        // ✨ SIARKAN PESAN BARU KE CHANNEL GRUP
        broadcast(new NewGroupMessage($message))->toOthers();


        // ✨ 2. SIAPKAN DAN SIARKAN UPDATE UNTUK CHAT LIST SEMUA ANGGOTA
        $conversationData = [
            'type' => 'group',
            'id' => $group->id,
            'name' => $group->name,
            'avatar_url' => $group->avatar_url,
            'last_message' => $message->content ?: '📎 Media',
            'last_media' => $message->media_url,
            'sent_at' => $message->sent_at->toIso8601String(),
        ];

        // ✨ 3. LOOPING & PANGGIL BROADCAST UNTUK SETIAP ANGGOTA
        foreach ($group->members as $member) {
            $dataForMember = $conversationData;
            $dataForMember['recipient_id'] = $member->user_id; // Tentukan target siaran
            broadcast(new ChatListUpdated($dataForMember));
        }

        if ($request->filled('content')) {
            preg_match_all('/@([a-zA-Z0-9_]+)/', $request->content, $matches);
            $mentionedUsernames = $matches[1] ?? [];

            foreach ($mentionedUsernames as $username) {
                $mentionedUser = User::where('username', $username)->first();
                if ($mentionedUser) {
                    GroupMessageMention::create([
                        'group_message_id' => $message->id,
                        'mentioned_user_id' => $mentionedUser->user_id,
                    ]);

                    // ✨ [OPSIONAL] BUAT DAN SIARKAN NOTIFIKASI MENTION
                    // $notification = Notification::create([...]); // Buat notifikasi di DB
                    // broadcast(new NewNotification($notification));
                }
            }
        }

        return response()->json([
            'message' => 'Pesan berhasil dikirim.',
            'data' => $message->load('sender'),
        ]);
    }

    public function index(Request $request, Group $group)
    {
        $user = Auth::user();

        if (!$group->members()->where('user_id', $user->user_id)->exists()) {
            return response()->json(['message' => 'Kamu bukan anggota grup ini.'], 403);
        }

        $messages = $group->messages()
            ->with(['sender:user_id,username', 'mentions.mentioned:user_id,username'])
            ->orderByDesc('sent_at')
            ->paginate(20);

        return response()->json([
            'group_id' => $group->id,
            'messages' => $messages->map(function ($msg) {
                return [
                    'id' => $msg->id,
                    'sender' => [
                        'user_id' => $msg->sender->user_id,
                        'username' => $msg->sender->username,
                    ],
                    'content' => $msg->is_deleted ? '[Pesan telah dihapus]' : $msg->content,
                    'media_url' => $msg->is_deleted ? null : $msg->media_url,
                    'is_pinned' => $msg->is_pinned,
                    'is_edited' => $msg->is_edited,
                    'sent_at' => $msg->sent_at ? $msg->sent_at->toIso8601String() : null,
                    'mentions' => $msg->mentions->map(function ($mention) {
                        return [
                            'user_id' => $mention->mentioned->user_id,
                            'username' => $mention->mentioned->username,
                        ];
                    }),
                ];
            }),
            'pagination' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
            ]
        ]);
    }

    public function destroy(Request $request, Group $group, GroupMessage $message)
    {
        if ($message->sender_id !== auth()->user()->user_id) {
            return response()->json(['message' => 'Tidak diizinkan menghapus pesan ini'], 403);
        }

        $message->is_deleted = true;
        $message->save();

        // ✨ SIARKAN UPDATE PESAN (KARENA is_deleted BERUBAH)
        broadcast(new GroupMessageUpdated($message))->toOthers();

        return response()->json(['message' => 'Pesan berhasil disembunyikan (soft delete).']);
    }

    public function togglePin(Request $request, Group $group, GroupMessage $message)
    {
        // Anda bisa menambahkan logika otorisasi yang lebih baik di sini, misal admin grup
        if ($group->owner_id !== auth()->user()->user_id) {
            return response()->json(['message' => 'Hanya pemilik grup yang bisa pin/unpin pesan.'], 403);
        }

        $message->is_pinned = !$message->is_pinned;
        $message->save();

        // ✨ SIARKAN UPDATE PESAN (KARENA is_pinned BERUBAH)
        broadcast(new GroupMessageUpdated($message))->toOthers();

        return response()->json([
            'message' => $message->is_pinned ? 'Pesan berhasil dipin.' : 'Pin dihapus.',
            'is_pinned' => $message->is_pinned
        ]);
    }
}