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
            'media'       => 'nullable|file|mimes:jpg,jpeg,png,mp4,pdf|max:10240', // maksimal 10MB
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

    // Ambil semua chat yang melibatkan user ini
    $chats = DirectMessage::where('sender_id', $auth_id)
        ->orWhere('receiver_id', $auth_id)
        ->latest('sent_at')
        ->get()
        ->groupBy(function ($message) use ($auth_id) {
            // Grup berdasarkan lawan bicara
            return $message->sender_id == $auth_id
                ? $message->receiver_id
                : $message->sender_id;
        })
        ->map(function ($messages, $user_id) {
            $lastMessage = $messages->sortByDesc('sent_at')->first();

            // Ambil data user lawan bicara
            $user = \App\Models\User::select('id', 'username', 'full_name', 'profile_picture_url')
                ->find($user_id);

            return [
                'user_id'             => (int) $user_id,
                'username'            => $user->username ?? null,
                'full_name'           => $user->full_name ?? null,
                'profile_picture_url' => $user->profile_picture_url ?? null,
                'last_message'        => $lastMessage->content ?? '📎 Media',
                'last_media'          => $lastMessage->media_url,
                'sent_at'             => $lastMessage->sent_at,
                'is_read'             => $lastMessage->is_read,
            ];
        })
        ->values();

    return response()->json($chats);
}



}
