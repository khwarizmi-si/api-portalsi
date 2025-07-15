<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Post;
use App\Models\Comment;
use App\Models\Notification;
use App\Models\User;

class CommentController extends Controller
{
    // ✅ Buat komentar / reply
    public function store(Request $request, $post_id)
    {
        $request->validate([
            'content' => 'required|string',
            'parent_comment_id' => 'nullable|exists:comments,comment_id'
        ]);

        $user_id = Auth::id();
        $post = Post::findOrFail($post_id);

        // Simpan komentar
        $comment = Comment::create([
            'post_id' => $post_id,
            'user_id' => $user_id,
            'content' => $request->input('content'),
            'parent_comment_id' => $request->input('parent_comment_id'),
        ]);

        // 🔔 Notifikasi COMMENT ke pemilik POST
        if (!$request->filled('parent_comment_id') && $post->user_id != $user_id) {
            Notification::create([
                'recipient_id'     => $post->user_id,
                'type'             => 'comment',
                'related_user_id'  => $user_id,
                'related_post_id'  => $post_id,
                'created_at'       => now(),
                'is_read'          => false,
            ]);
        }

        // 🔁 Notifikasi REPLY ke pemilik komentar (kalau ada parent)
        if ($request->filled('parent_comment_id')) {
            $parent = Comment::where('comment_id', $request->parent_comment_id)->first();
            if ($parent && $parent->user_id != $user_id) {
                Notification::create([
                    'recipient_id'     => $parent->user_id,
                    'type'             => 'reply',
                    'related_user_id'  => $user_id,
                    'related_post_id'  => $post_id,
                    'created_at'       => now(),
                    'is_read'          => false,
                ]);
            }
        }

        // 📣 Notifikasi MENTION
        $mentionedUsernames = collect(explode(' ', $request->content))
            ->filter(fn($word) => str_starts_with($word, '@'))
            ->map(fn($mention) => ltrim($mention, '@'));

        foreach ($mentionedUsernames as $username) {
            $mentionedUser = User::where('username', $username)->first();

            if ($mentionedUser && $mentionedUser->user_id != $user_id) {
                Notification::create([
                    'recipient_id'     => $mentionedUser->user_id,
                    'type'             => 'mention',
                    'related_user_id'  => $user_id,
                    'related_post_id'  => $post_id,
                    'created_at'       => now(),
                    'is_read'          => false,
                ]);
            }
        }

        return response()->json([
            'message' => 'Komentar berhasil dikirim.',
            'data' => $comment
        ], 201);
    }
}
