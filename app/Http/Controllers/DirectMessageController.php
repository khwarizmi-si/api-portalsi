<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\DirectMessage;
use Illuminate\Support\Facades\Storage;

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

    // Ambil chat terakhir tiap lawan bicara
    $subQuery = DirectMessage::select(
            DB::raw("CASE 
                        WHEN sender_id = $auth_id THEN receiver_id 
                        ELSE sender_id 
                     END as user_id"),
            'content',
            'media_url',
            'sent_at',
            'is_read'
        )
        ->where(function($q) use ($auth_id) {
            $q->where('sender_id', $auth_id)
              ->orWhere('receiver_id', $auth_id);
        })
        ->orderBy('sent_at', 'desc');

        $subQuery = DB::table('direct_messages')
        ->selectRaw('user_id, MAX(id) as last_id')
        ->groupBy('user_id');
    
    $lastChats = DB::table('direct_messages as dm')
        ->joinSub($subQuery, 'sq', function ($join) {
            $join->on('dm.id', '=', 'sq.last_id');
        })
        ->get();
    

    // Ambil user data sekaligus (pakai user_id)
    $userIds = $lastChats->pluck('user_id')->toArray();
    $users = User::whereIn('user_id', $userIds)
        ->select('user_id','username','full_name','profile_picture_url')
        ->get()
        ->keyBy('user_id');

    // Gabungkan hasil
    $result = $lastChats->map(function($chat) use ($users) {
        $user = $users[$chat->user_id] ?? null;
        return [
            'user_id'             => (int) $chat->user_id,
            'username'            => $user->username ?? null,
            'full_name'           => $user->full_name ?? null,
            'profile_picture_url' => $user->profile_picture_url ?? null,
            'last_message'        => $chat->content ?? '📎 Media',
            'last_media'          => $chat->media_url,
            'sent_at'             => $chat->sent_at,
            'is_read'             => $chat->is_read,
        ];
    });

    return response()->json($result->values());
}



}
