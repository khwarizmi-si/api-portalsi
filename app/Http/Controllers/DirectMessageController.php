<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\DirectMessage;
use Illuminate\Support\Facades\Storage;
use App\Models\Group;
use App\Models\GroupMember;

class DirectMessageController extends Controller
{
    // ✅ Kirim pesan dengan teks / media
    public function send(Request $request)
    {
        $request->validate([
            'receiver_id' => 'required|exists:users,user_id',
            'content'     => 'nullable|string',
            'media'       => 'nullable|file|mimes:jpg,jpeg,png,mp4,pdf|max:51200', // maksimal 10MB
        ]);

        $mediaUrl = null;

        if ($request->hasFile('media')) {
            $mediaPath = $request->file('media')->store('uploads/direct_messages', 'public');
            $mediaUrl = asset('storage/' . $mediaPath);
        }

        $message = DirectMessage::create([
            'sender_id'   => Auth::id(),
            'receiver_id' => $request->receiver_id,
            'content'     => $request->content,
            'media_url'   => $mediaUrl,
            'sent_at'     => now(),
            'is_read'     => false
        ]);

        return response()->json([
            'message' => 'Pesan berhasil dikirim.',
            'data' => $message
        ], 201);
    }

    // ✅ Ambil semua chat antara 2 user
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
            ->get();

        return response()->json($messages);
    }

    // ✅ Tandai pesan sebagai terbaca
    public function markAsRead($id)
    {
        $message = DirectMessage::where('message_id', $id)
            ->where('receiver_id', Auth::id())
            ->firstOrFail();

        $message->update(['is_read' => true]);

        return response()->json(['message' => 'Pesan ditandai sebagai telah dibaca.']);
    }

    // ✅ Hapus pesan (oleh pengirim saja)
public function destroy($id)
{
    $message = DirectMessage::where('message_id', $id)
        ->where('sender_id', Auth::id()) // hanya pengirim yang boleh menghapus
        ->firstOrFail();

    // Hapus file media jika ada
    if ($message->media_url) {
        $path = str_replace(asset('storage') . '/', '', $message->media_url);
        Storage::disk('public')->delete($path);
    }

    $message->delete();

    return response()->json(['message' => 'Pesan berhasil dihapus.']);
}

// ✅ List user yang pernah di-chat
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
        ->where(function($q) use ($auth_id) {
            $q->where('sender_id', $auth_id)
              ->orWhere('receiver_id', $auth_id);
        })
        ->groupBy('user_id');

    $lastChats = DB::table('direct_messages as dm')
        ->joinSub($subQuery, 'sq', function($join) use ($auth_id) {
            $join->on(DB::raw("CASE WHEN dm.sender_id = $auth_id THEN dm.receiver_id ELSE dm.sender_id END"), '=', 'sq.user_id')
                 ->on('dm.sent_at', '=', 'sq.last_sent_at');
        })
        ->select(
            DB::raw("CASE WHEN dm.sender_id = $auth_id THEN dm.receiver_id ELSE dm.sender_id END as user_id"),
            'dm.content',
            'dm.media_url',
            'dm.sent_at',
            'dm.is_read'
        )
        ->orderBy('dm.sent_at', 'desc')
        ->get();

    $userIds = $lastChats->pluck('user_id')->toArray();
    $users = User::whereIn('user_id', $userIds)
        ->select('user_id','username','full_name','profile_picture_url')
        ->get()
        ->keyBy('user_id');

    $chatUsers = $lastChats->map(function($chat) use ($users) {
        $user = $users[$chat->user_id] ?? null;
        return [
            'type'               => 'user',
            'id'                 => (int) $chat->user_id,
            'name'               => $user->full_name ?? $user->username,
            'username'           => $user->username ?? null,
            'profile_picture_url'=> $user->profile_picture_url ?? null,
            'last_message'       => $chat->content ?? '📎 Media',
            'last_media'         => $chat->media_url,
            'sent_at'            => $chat->sent_at,
            'is_read'            => $chat->is_read,
        ];
    });

    // 🔹 2. Ambil grup + last message
    $groups = \App\Models\GroupMember::with('group')
        ->where('user_id', $auth_id)
        ->get()
        ->map(function ($member) {
            // cari pesan terakhir di grup ini
            $lastMsg = \App\Models\GroupMessage::where('group_id', $member->group_id)
                ->orderByDesc('sent_at')
                ->first();

            return [
                'type'        => 'group',
                'id'          => $member->group->id,
                'name'        => $member->group->name,
                'description' => $member->group->description,
                'avatar_url'  => $member->group->avatar_url,
                'cover_url'   => $member->group->cover_url,
                'role'        => $member->role,
                'joined_at'   => $member->joined_at,
                'is_muted'    => (bool) $member->is_muted,

                // tambahan last message
                'last_message'=> $lastMsg ? ($lastMsg->is_deleted ? '[Pesan telah dihapus]' : ($lastMsg->content ?? '📎 Media')) : null,
                'last_media'  => $lastMsg && !$lastMsg->is_deleted ? $lastMsg->media_url : null,
                'sent_at'     => $lastMsg ? $lastMsg->sent_at : null,
                'sender'      => $lastMsg ? User::select('user_id','username')->find($lastMsg->sender_id) : null,
            ];
        });

    // 🔹 3. Gabungkan user chat + group chat
    $result = $chatUsers->merge($groups);

    // 🔹 4. Urutkan berdasarkan waktu terbaru (sent_at)
    $result = $result->sortByDesc('sent_at')->values();

    return response()->json($result);
}


}
