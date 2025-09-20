<?php

namespace App\Http\Controllers;

use App\Events\NewGroupMessage;
use App\Events\GroupMessageUpdated;
use App\Events\MessageSent;
use App\Events\ChatListUpdated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Group;
use App\Models\GroupMessage;
use App\Models\GroupMessageMention;
use App\Models\User;

class GroupMessageController extends Controller
{
    /**
     * Simpan pesan baru ke dalam grup
     */
    public function store(Request $request, Group $group)
    {
        $user = Auth::user();

        // Validasi anggota grup
        if (!$group->members()->where('user_id', $user->user_id)->exists()) {
            return response()->json(['message' => 'Kamu bukan anggota grup ini.'], 403);
        }

        // Validasi request
        $request->validate([
            'content' => 'required|string',
            'media'   => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:51200',
        ]);

        // Upload media (jika ada)
        $mediaUrl = null;
        if ($request->hasFile('media')) {
            $media = $request->file('media');
            $path = $media->store('group-media', 'public');
            $mediaUrl = asset('storage/' . $path);
        }

        // Simpan pesan
        $message = GroupMessage::create([
            'group_id'  => $group->id,
            'sender_id' => $user->user_id,
            'content'   => $request->content,
            'media_url' => $mediaUrl,
            'sent_at'   => now(),
        ]);

        // ✅ refresh agar cast sent_at -> Carbon dan relasi sender ikut
        $message = $message->fresh(['sender']);

        // Broadcast ke channel realtime
        // broadcast(new NewGroupMessage($message))->toOthers();
        // broadcast(new MessageSent($message, 'group'));

        // Update chat list semua anggota
        $conversationData = [
            'type'        => 'group',
            'id'          => $group->id,
            'name'        => $group->name,
            'avatar_url'  => $group->avatar_url,
            'last_message'=> $message->content ?: '📎 Media',
            'last_media'  => $message->media_url,
            'sent_at'     => optional($message->sent_at)->toIso8601String() ?? now()->toIso8601String(),
        ];

        foreach ($group->members as $member) {
            $dataForMember = $conversationData;
            $dataForMember['recipient_id'] = (int) $member->user_id;
            broadcast(new ChatListUpdated($dataForMember));
        }

        // Cek mention di konten
        if ($request->filled('content')) {
            preg_match_all('/@([a-zA-Z0-9_]+)/', $request->content, $matches);
            $mentionedUsernames = $matches[1] ?? [];

            foreach ($mentionedUsernames as $username) {
                $mentionedUser = User::where('username', $username)->first();
                if ($mentionedUser) {
                    GroupMessageMention::create([
                        'group_message_id'  => $message->id,
                        'mentioned_user_id' => $mentionedUser->user_id,
                    ]);
                }
            }
        }

        // ✅ Return response aman
        return response()->json([
            'message' => 'Pesan berhasil dikirim.',
            'data'    => [
                'id'        => $message->id,
                'group_id'  => $message->group_id,
                'sender'    => [
                    'user_id'  => $message->sender->user_id,
                    'username' => $message->sender->username,
                ],
                'content'   => $message->content,
                'media_url' => $message->media_url,
                'is_pinned' => (bool) $message->is_pinned,
                'is_edited' => (bool) $message->is_edited,
                'is_deleted'=> (bool) $message->is_deleted,
                'sent_at'   => optional($message->sent_at)->toIso8601String() ?? now()->toIso8601String(),
            ]
        ]);
    }

    /**
     * Ambil daftar pesan grup
     */
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
                    'id'        => $msg->id,
                    'sender'    => [
                        'user_id'  => $msg->sender->user_id,
                        'username' => $msg->sender->username,
                    ],
                    'content'   => $msg->is_deleted ? '[Pesan telah dihapus]' : $msg->content,
                    'media_url' => $msg->is_deleted ? null : $msg->media_url,
                    'is_pinned' => (bool) $msg->is_pinned,
                    'is_edited' => (bool) $msg->is_edited,
                    'is_deleted'=> (bool) $msg->is_deleted,
                    'sent_at'   => optional($msg->sent_at)->toIso8601String(),
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

    /**
     * Soft delete pesan
     */
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

    /**
     * Pin / Unpin pesan
     */
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
