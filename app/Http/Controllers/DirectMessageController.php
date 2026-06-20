<?php

namespace App\Http\Controllers;

// 🔽 IMPORT SEMUA EVENT YANG DIPERLUKAN
use App\Events\NewDirectMessage;
use App\Events\MessageSent;
// Jika Anda ingin membuat fitur 'read receipt' dan 'delete' real-time:
// use App\Events\MessageRead; 
// use App\Events\MessageDeleted;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Events\ChatListUpdated;
use App\Models\DirectMessage;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMessage;
use Illuminate\Support\Facades\Storage;
use App\Services\FirebaseService;

class DirectMessageController extends Controller
{
    /**
     * Kirim pesan dengan teks / media
     */
public function send(Request $request)
{
    $request->validate([
        'receiver_id'         => 'required|exists:users,user_id',
        'content'             => 'nullable|string',
        'media'               => 'nullable|file|mimes:jpg,jpeg,png,mp4,pdf|max:51200',
        'is_story_response'   => 'nullable|boolean',
        'story_id'            => 'nullable|integer|exists:stories,story_id',
        'responded_media_url' => 'nullable|string|max:255',
    ]);

    $mediaUrl = null;

    if ($request->hasFile('media')) {
        // Configured default disk (r2 in prod, public locally) so it works
        // without cloud credentials.
        $disk = config('filesystems.default');
        $mediaPath = $request->file('media')->store('uploads/direct_messages', $disk);
        $mediaUrl  = Storage::disk($disk)->url($mediaPath);
    }

    $message = DirectMessage::create([
        'sender_id'           => Auth::id(),
        'receiver_id'         => $request->receiver_id,
        'content'             => $request->content,
        'media_url'           => $mediaUrl,
        'is_story_response'   => $request->boolean('is_story_response', false),
        'story_id'            => $request->is_story_response ? $request->story_id : null,
        'responded_media_url' => $request->is_story_response ? $request->responded_media_url : null,
        'sent_at'             => now(),
        'is_read'             => false,
    ]);

    $message->refresh();

    broadcast(new NewDirectMessage($message))->toOthers();

    $sender   = Auth::user();
    $receiver = User::findOrFail($request->receiver_id);

    $conversationData = [
        'type'                => 'user',
        'id'                  => $receiver->user_id,
        'name'                => $receiver->full_name ?? $receiver->username,
        'username'            => $receiver->username,
        'profile_picture_url' => $receiver->profile_picture_url,
        'last_message'        => $message->content ?? '📎 Media',
        'last_media'          => $message->media_url,
        'sent_at'             => $message->sent_at?->toIso8601String(),
        'is_read'             => $message->is_read,
        'is_story_response'   => (bool) $message->is_story_response,
        'story_id'            => $message->story_id,
        'responded_media_url' => $message->responded_media_url,
    ];

    // 🔹 Update chat list untuk kedua user
    $dataForReceiver = $conversationData;
    $dataForReceiver['id']                  = $sender->user_id;
    $dataForReceiver['name']                = $sender->full_name ?? $sender->username;
    $dataForReceiver['username']            = $sender->username;
    $dataForReceiver['profile_picture_url'] = $sender->profile_picture_url;
    $dataForReceiver['recipient_id']        = $receiver->user_id;
    broadcast(new ChatListUpdated($dataForReceiver));

    $dataForSender = $conversationData;
    $dataForSender['recipient_id'] = $sender->user_id;
    broadcast(new ChatListUpdated($dataForSender));

     try {
        // Cek apakah receiver offline (contoh logika sederhana)
        if (is_null($receiver->last_seen) || $receiver->last_seen < now()->subMinutes(3)) {
            $firebase = new FirebaseService();
            $firebase->sendToUser(
                $receiver->user_id,
                "Pesan baru dari {$sender->username}",
                $message->content ?: '📎 Mengirim media',
                [
                    'type' => 'dm',
                    'sender_id' => (string) $sender->user_id,
                    'receiver_id' => (string) $receiver->user_id,
                    'message_id' => (string) $message->message_id,
                ]
            );
        }
    } catch (\Exception $e) {
        \Log::error('Gagal kirim FCM: ' . $e->getMessage());
    }

    return response()->json([
        'message' => 'Pesan berhasil dikirim.',
        'data'    => $message
    ], 201);
}

    /**
     * Ambil semua chat antara 2 user
     */
public function conversation($user_id)
{
    $auth_id = Auth::id();

    $messages = DirectMessage::where(function ($q) use ($auth_id, $user_id) {
        $q->where('sender_id', $auth_id)->where('receiver_id', $user_id);
    })
        ->orWhere(function ($q) use ($auth_id, $user_id) {
            $q->where('sender_id', $user_id)->where('receiver_id', $auth_id);
        })
        ->orderBy('sent_at', 'asc')
        ->get()
        ->map(function ($msg) use ($auth_id) {
            // kalau message dari kita sendiri, selalu is_read = true
            if ($msg->sender_id == $auth_id) {
                $msg->is_read = true;
            }
            return $msg;
        });

    return response()->json($messages);
}


    /**
     * Ambil semua chat dari lawan bicara (tidak termasuk pesan kita sendiri)
     */
    public function conversationFromUser($user_id)
    {
        $auth_id = Auth::id();

        $messages = DirectMessage::where('sender_id', $user_id) // hanya pesan yg dikirim user lawan
            ->where('receiver_id', $auth_id)                   // penerimanya kita
            ->orderBy('sent_at', 'asc')
            ->get();

        return response()->json($messages);
    }


    /**
     * Tandai pesan sebagai terbaca
     */
    public function markAsRead($id)
    {
        $message = DirectMessage::where('message_id', $id)
            ->where('receiver_id', Auth::id())
            ->firstOrFail();

        $message->update(['is_read' => true]);

        // ✨ [OPSIONAL] SIARKAN EVENT 'READ RECEIPT'
        // broadcast(new MessageRead($message))->toOthers();

        return response()->json(['message' => 'Pesan ditandai sebagai telah dibaca.']);
    }

    /**
     * Tandai semua pesan dari 1 user sebagai sudah dibaca
     */
    public function markAsReadByUser($user_id)
    {
        $auth_id = Auth::id();


    $updated = DirectMessage::where('sender_id', $user_id)   // harus dari lawan bicara
        ->where('receiver_id', $auth_id)                     // dan masuk ke kita
        ->where('is_read', false)
        ->update(['is_read' => true]);

    return response()->json([
        'message' => "Semua pesan dari user {$user_id} ke {$auth_id} ditandai sebagai dibaca.",
        'updated_count' => $updated
    ]);
}




    /**
     * Hapus pesan (oleh pengirim saja)
     */
    public function destroy($id)
    {
        $message = DirectMessage::where('message_id', $id)
            ->where('sender_id', Auth::id())
            ->firstOrFail();

        // Hapus file media jika ada
        if ($message->media_url) {
            $path = ltrim(parse_url($message->media_url, PHP_URL_PATH), '/');
            Storage::disk(config('filesystems.default'))->delete($path);
        }

        // ✨ [OPSIONAL] SIARKAN EVENT PENGHAPUSAN PESAN
        // broadcast(new MessageDeleted($message))->toOthers();

        $message->delete();

        return response()->json(['message' => 'Pesan berhasil dihapus.']);
    }

    /**
     * List user yang pernah di-chat
     */
public function chatList()
{
    $auth_id = Auth::id();

    // 🔹 1. Ambil chat pribadi (sama seperti sebelumnya)
    $subQuery = DirectMessage::select(
        DB::raw("CASE 
                    WHEN sender_id = $auth_id THEN receiver_id 
                    ELSE sender_id 
                 END as user_id"),
        DB::raw("MAX(sent_at) as last_sent_at")
    )
        ->where(function ($q) use ($auth_id) {
            $q->where('sender_id', $auth_id)
                ->orWhere('receiver_id', $auth_id);
        })
        ->groupBy('user_id');

    $lastChats = DB::table('direct_messages as dm')
        ->joinSub($subQuery, 'sq', function ($join) use ($auth_id) {
            $join->on(DB::raw("CASE WHEN dm.sender_id = $auth_id THEN dm.receiver_id ELSE dm.sender_id END"), '=', 'sq.user_id')
                ->on('dm.sent_at', '=', 'sq.last_sent_at');
        })
        ->select(
            DB::raw("CASE WHEN dm.sender_id = $auth_id THEN dm.receiver_id ELSE dm.sender_id END as user_id"),
            'dm.sender_id', 
            'dm.content',
            'dm.media_url',
            'dm.sent_at',
            'dm.is_read'
        )
        ->orderBy('dm.sent_at', 'desc')
        ->get();

    // 🔹 2. Ambil data user + is_verified
    $userIds = $lastChats->pluck('user_id')->toArray();
    $users = User::whereIn('user_id', $userIds)
        ->select('user_id', 'username', 'full_name', 'profile_picture_url', 'is_verified')
        ->get()
        ->keyBy('user_id');

    $chatUsers = $lastChats->map(function ($chat) use ($users, $auth_id) {
        $user = $users[$chat->user_id] ?? null;

        return [
            'type' => 'user',
            'conversation' => [
                'id' => (int) $chat->user_id,
                'name' => $user->full_name ?? $user->username,
                'username' => $user->username ?? null,
                'profile_picture_url' => $user->profile_picture_url ?? null,
                'is_verified' => (bool) ($user->is_verified ?? false), // 👈 tambahkan ini
            ],
            'last_chat' => [
                'content' => $chat->content ?? '📎 Media',
                'media' => $chat->media_url,
                'sent_at' => $chat->sent_at,
                'is_read' => ($chat->sender_id == $auth_id) ? true : $chat->is_read,
            ],
        ];
    });

    // 🔹 3. Ambil grup + last message
    $groups = \App\Models\GroupMember::with('group')
        ->where('user_id', $auth_id)
        ->get()
        ->map(function ($member) {
            $lastMessage = GroupMessage::where('group_id', $member->group->id)
                ->orderBy('sent_at', 'desc')
                ->first();

            return [
                'type' => 'group',
                'id' => $member->group->id,
                'name' => $member->group->name,
                'description' => $member->group->description ?? '',
                'avatar_url' => $member->group->avatar_url ?? '',
                'cover_url' => $member->group->cover_url ?? '',
                'role' => $member->role,
                'joined_at' => $member->joined_at,
                'is_muted' => (bool) $member->is_muted,

                'last_message' => $lastMessage
                    ? ($lastMessage->is_deleted ? '[Pesan telah dihapus]' : ($lastMessage->content ?: '📎 Media'))
                    : '',
                'last_media' => $lastMessage && !$lastMessage->is_deleted ? ($lastMessage->media_url ?? '') : '',
                'sent_at' => $lastMessage ? $lastMessage->sent_at : '',
            ];
        });

    // 🔹 4. Gabungkan user chat + group chat
    $result = $chatUsers->merge($groups);

    // 🔹 5. Urutkan berdasarkan waktu terbaru
    $result = $result->sortByDesc('sent_at')->values();

    return response()->json($result);
}


    // 🔹 List pesan belum dibaca
    public function unreadConversation($user_id)
    {
        $auth_id = Auth::id();

        $messages = DirectMessage::where('receiver_id', $auth_id)
            ->where('sender_id', $user_id)
            ->where('is_read', false)
            ->orderBy('sent_at', 'asc')
            ->get();

        return response()->json([
            'unread_count' => $messages->count(),
            'messages' => $messages
        ]);
    }

    public function channels()
{
    $auth_id = Auth::id();
    $channels = [];

    // 🔹 Ambil semua lawan chat (DM)
    $chatUserIds = DirectMessage::where('sender_id', $auth_id)
        ->orWhere('receiver_id', $auth_id)
        ->selectRaw("CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END as user_id", [$auth_id])
        ->distinct()
        ->pluck('user_id');

    foreach ($chatUserIds as $uid) {
        // supaya konsisten urutan id kecil duluan
        $ids = [$auth_id, $uid];
        sort($ids);
        $channels[] = "private-dm.{$ids[0]}-{$ids[1]}";
    }

    // 🔹 Ambil semua group yg diikuti user
    $groupIds = GroupMember::where('user_id', $auth_id)
        ->pluck('group_id');

    foreach ($groupIds as $gid) {
        $channels[] = "group.{$gid}";
    }

    return response()->json($channels);
}


}

