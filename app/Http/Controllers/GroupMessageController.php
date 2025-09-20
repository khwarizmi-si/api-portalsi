<?php

namespace App\Http\Controllers;

use App\Events\NewGroupMessage;
use App\Events\GroupMessageUpdated;
use App\Events\NewNotification;
use App\Events\MessageSent;
use App\Events\ChatListUpdated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\Group;
use App\Models\GroupMessage;
use App\Models\GroupMessageMention;
use App\Models\User;
use App\Models\Notification;

class GroupMessageController extends Controller
{
    public function store(Request $request, Group $group)
    {
        $user = Auth::user();

        // 🚨 Cek apakah user anggota grup
        if (!$group->members()->where('user_id', $user->user_id)->exists()) {
            return response()->json(['message' => 'Kamu bukan anggota grup ini.'], 403);
        }

        // ✅ Validasi input
        $request->validate([
            'content' => 'nullable|string',
            'media'   => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:51200',
        ]);

        // ✅ Upload media jika ada
        $mediaUrl = null;
        if ($request->hasFile('media')) {
            $media = $request->file('media');
            $path = $media->store('group-media', 'public');
            $mediaUrl = asset('storage/' . $path);
        }

        // ✅ Buat pesan
        $message = GroupMessage::create([
            'group_id'  => $group->id,
            'sender_id' => $user->user_id,
            'content'   => $request->content,
            'media_url' => $mediaUrl,
            'sent_at'   => now(), // selalu isi sent_at
        ]);

        // Refresh supaya cast ke Carbon aktif
        $message->refresh();

        // ✅ Broadcast pesan baru ke channel realtime
        broadcast(new NewGroupMessage($message))->toOthers();
        broadcast(new MessageSent($message, 'group'));

        // ✅ Update chat list semua anggota
        $conversationData = [
            'type'        => 'group',
            'id'          => $group->id,
            'name'        => $group->name,
            'avatar_url'  => $group->avatar_url,
            'last_message'=> $message->content ?: '📎 Media',
            'last_media'  => $message->media_url,
            'sent_at'     => $message->sent_at?->toIso8601String() ?? now()->toIso8601String(),
        ];

        foreach ($group->members as $member) {
            $dataForMember             = $conversationData;
            $dataForMember['recipient_id'] = (int) $member->user_id;
            broadcast(new ChatListUpdated($dataForMember));
        }

        // ✅ Deteksi mention
        if ($request->filled('content')) {
            preg_match_all('/@([a-zA-Z0-9_]+)/', $request->content, $matches);
            $mentionedUsernames = $matches[1] ?? [];

            foreach ($mentionedUsernames as $username) {
                $mentionedUser = User::where('username', $username)->first();
                if ($mentionedUser) {
                    GroupMessageMention::create([
                        'group_message_id'   => $message->id,
                        'mentioned_user_id'  => $mentionedUser->user_id,
                    ]);

                    // Bisa tambahkan notifikasi mention di sini
                    // $notification = Notification::create([...]);
                    // broadcast(new NewNotification($notification));
                }
            }
        }

        return response()->json([
            'message' => 'Pesan berhasil dikirim.',
            'data'    => $message->load('sender'),
        ]);
    }

    public function index(Request $request, Group $group)
    {
        $user = Auth::user();

        // 🚨 Cek anggota
        if (!$group->members()->where('user_id', $user->user_id)->exists()) {
            return response()->json(['message' => 'Kamu bukan anggota grup ini.'], 403);
        }

        // ✅ Ambil pesan dengan relasi
        $messages = $group->messages()
            ->with(['sender:user_id,username', 'mentions.mentioned:user_id,username'])
            ->orderByDesc('sent_at')
            ->paginate(20);

        return response()->json([
            'group_id' => $group->id,
            'messages' => $messages->map(function ($msg) {
                return [
                    'id'        => $msg->id,
                    'sender'    => [
                        'user_id'  => $msg->sender->user_id,
                        'username' => $msg->sender->username,
                    ],
                    'content'   => $msg->is_deleted ? '[Pesan telah dihapus]' : $msg->content,
                    'media_url' => $msg->is_deleted ? null : $msg->media_url,
                    'is_pinned' => (bool) $msg->is_pinned,
                    'is_edited' => (bool) $msg->is_edited,
                    'sent_at'   => $msg->sent_at?->toIso8601String(),
                    'mentions'  => $msg->mentions->map(function ($mention) {
                        return [
                            'user_id'  => $mention->mentioned->user_id,
                            'username' => $mention->mentioned->username,
                        ];
                    }),
                ];
            }),
            'pagination' => [
                'current_page' => $messages->currentPage(),
                'last_page'    => $messages->lastPage(),
                'per_page'     => $messages->perPage(),
                'total'        => $messages->total(),
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

        broadcast(new GroupMessageUpdated($message))->toOthers();

        return response()->json(['message' => 'Pesan berhasil disembunyikan (soft delete).']);
    }

    public function togglePin(Request $request, Group $group, GroupMessage $message)
    {
        if ($group->owner_id !== auth()->user()->user_id) {
            return response()->json(['message' => 'Hanya pemilik grup yang bisa pin/unpin pesan.'], 403);
        }

        $message->is_pinned = !$message->is_pinned;
        $message->save();

        broadcast(new GroupMessageUpdated($message))->toOthers();

        return response()->json([
            'message'   => $message->is_pinned ? 'Pesan berhasil dipin.' : 'Pin dihapus.',
            'is_pinned' => $message->is_pinned
        ]);
    }
}
