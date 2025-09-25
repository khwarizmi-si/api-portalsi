<?php

namespace App\Http\Controllers;

use App\Events\NewGroupMessage;
use App\Events\GroupMessageUpdated;
use App\Events\ChatListUpdated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Group;
use App\Models\GroupMessage;
use App\Models\GroupMessageMention;
use App\Models\GroupMessageRead;
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
            'content'   => 'nullable|string',
            'media'     => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:51200',
            'reply_to'  => $request->reply_to ? (int) $request->reply_to : null,
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
    'reply_to'  => $request->reply_to ? (int) $request->reply_to : null,
    'sent_at'   => now(),
]);


        // refresh agar relasi ikut
        $message = $message->fresh(['sender', 'replyTo.sender']);

        // Broadcast ke channel realtime
        broadcast(new NewGroupMessage($message))->toOthers();

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

        return response()->json([
            'message' => 'Pesan berhasil dikirim.',
            'data'    => $this->formatMessage($message, $user)
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
            ->with([
                'sender:user_id,username,is_verified',
                'mentions.mentioned:user_id,username,is_verified',
                'replyTo.sender:user_id,username,is_verified',
                'reads.user:user_id,username,is_verified'
            ])
            ->orderBy('sent_at', 'asc')
            ->get();

        // Cek reverse param
        $reverse = filter_var($request->query('reverse'), FILTER_VALIDATE_BOOLEAN);
        if ($reverse) {
            $messages = $messages->reverse()->values();
        }

        return response()->json([
            'group_id' => $group->id,
            'messages' => $messages->map(fn($msg) => $this->formatMessage($msg, $user)),
        ]);
    }

    /**
     * Tandai pesan sebagai dibaca
     */
    public function markAsRead(Request $request, Group $group, GroupMessage $message)
    {
        $user = Auth::user();

        if (!$group->members()->where('user_id', $user->user_id)->exists()) {
            return response()->json(['message' => 'Kamu bukan anggota grup ini.'], 403);
        }

        GroupMessageRead::updateOrCreate(
            ['group_message_id' => $message->id, 'user_id' => $user->user_id],
            ['read_at' => now()]
        );

        return response()->json(['message' => 'Pesan ditandai telah dibaca.']);
    }

    /**
     * Info siapa saja yang sudah baca pesan
     */
    public function readInfo(Request $request, Group $group, GroupMessage $message)
    {
        $reads = $message->reads()->with('user:user_id,username,is_verified')->get();

        return response()->json([
            'message_id' => $message->id,
            'reads'      => $reads->map(fn($read) => [
                'user_id'  => $read->user->user_id,
                'username' => $read->user->username,
                'is_verified' => $read->user->is_verified,
                'read_at'  => $read->read_at,
            ])
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

    /**
     * Helper: Format pesan untuk response JSON
     */
    private function formatMessage(GroupMessage $msg, $currentUser)
    {
        $isMentioned = $msg->mentions
            ? $msg->mentions->contains('mentioned_user_id', $currentUser->user_id)
            : false;

        return [
            'id'        => $msg->id,
            'sender'    => [
                'user_id'    => $msg->sender->user_id,
                'username'   => $msg->sender->username,
                'is_verified'=> $msg->sender->is_verified,
            ],
            'content'   => $msg->is_deleted ? '[Pesan telah dihapus]' : $msg->content,
            'media_url' => $msg->is_deleted ? null : $msg->media_url,
            'is_pinned' => (bool) $msg->is_pinned,
            'is_edited' => (bool) $msg->is_edited,
            'is_deleted'=> (bool) $msg->is_deleted,
            'sent_at'   => optional($msg->sent_at)->toIso8601String(),
            'reply_to'  => $msg->replyTo ? [
                'id'       => $msg->replyTo->id,
                'content'  => $msg->replyTo->content,
                'sender'   => [
                    'user_id'    => $msg->replyTo->sender->user_id,
                    'username'   => $msg->replyTo->sender->username,
                    'is_verified'=> $msg->replyTo->sender->is_verified,
                ]
            ] : null,
            'mentions'  => $msg->mentions->map(fn($mention) => [
                'user_id'    => $mention->mentioned->user_id,
                'username'   => $mention->mentioned->username,
                'is_verified'=> $mention->mentioned->is_verified,
            ]),
            'has_mention' => $isMentioned,
            'reads'    => $msg->reads->map(fn($read) => [
                'user_id'    => $read->user->user_id,
                'username'   => $read->user->username,
                'is_verified'=> $read->user->is_verified,
                'read_at'    => $read->read_at,
            ])
        ];
    }

    /**
     * Ambil daftar pesan yang belum dibaca oleh user saat ini dalam grup
     */
    public function unreadMessages(Request $request, Group $group)
    {
        $user = Auth::user();

        // Pastikan user anggota grup
        if (!$group->members()->where('user_id', $user->user_id)->exists()) {
            return response()->json(['message' => 'Kamu bukan anggota grup ini.'], 403);
        }

        // Ambil pesan yang belum dibaca
        $unreadMessages = $group->messages()
            ->whereDoesntHave('reads', function ($q) use ($user) {
                $q->where('user_id', $user->user_id);
            })
            ->with([
                'sender:user_id,username,is_verified',
                'mentions.mentioned:user_id,username,is_verified',
                'replyTo.sender:user_id,username,is_verified',
            ])
            ->orderBy('sent_at', 'asc')
            ->get();

        return response()->json([
            'group_id' => $group->id,
            'unread_messages' => $unreadMessages->map(fn($msg) => $this->formatMessage($msg, $user)),
        ]);
    }
}
