<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\DirectMessage;

class DirectMessageController extends Controller
{
    // ✅ Kirim pesan
    public function send(Request $request)
    {
        $request->validate([
            'receiver_id' => 'required|exists:users,user_id',
            'content'     => 'nullable|string',
            'media_url'   => 'nullable|url'
        ]);

        $message = DirectMessage::create([
            'sender_id'   => Auth::id(),
            'receiver_id' => $request->receiver_id,
            'content'     => $request->content,
            'media_url'   => $request->media_url,
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
}
