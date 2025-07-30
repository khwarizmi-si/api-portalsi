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

        // Simpan komentar nih
        $comment = Comment::create([
            'post_id' => $post_id,
            'user_id' => $user_id,
            'content' => $request->input('content'),
            'parent_comment_id' => $request->input('parent_comment_id'),
        ]);

        // 🔔 Notifikasi COMMENT ke pemilik POST wkwkwk
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

    // 🔍 Ambil semua komentar & reply dari sebuah post
public function getCommentsByPost($post_id)
{
    $post = Post::findOrFail($post_id);

    $comments = $post->comments()
        ->with(['user', 'replies.user']) // eager load user dan replies
        ->whereNull('parent_comment_id') // hanya ambil komentar utama
        ->orderBy('created_at', 'asc')
        ->get();

    return response()->json([
        'post_id' => $post_id,
        'comments' => $comments
    ]);
}

// ✏️ Update komentar / reply
public function update(Request $request, $comment_id)
{
    $request->validate([
        'content' => 'required|string'
    ]);

    $comment = Comment::findOrFail($comment_id);

    // Hanya user yang membuat komentar yang boleh mengedit
    if ($comment->user_id !== Auth::id()) {
        return response()->json([
            'message' => 'Kamu tidak punya izin untuk mengedit komentar ini.'
        ], 403);
    }

    $comment->content = $request->input('content');
    $comment->updated_at = now();
    $comment->save();

    return response()->json([
        'message' => 'Komentar berhasil diperbarui.',
        'data' => $comment
    ]);
}

// 🗑️ Hapus komentar / reply
public function destroy($comment_id)
{
    $comment = Comment::findOrFail($comment_id);

    // Hanya user yang membuat komentar yang boleh menghapus
    if ($comment->user_id !== Auth::id()) {
        return response()->json([
            'message' => 'Kamu tidak punya izin untuk menghapus komentar ini.'
        ], 403);
    }

    // Jika komentar utama, hapus juga reply-nya
    if ($comment->parent_comment_id === null) {
        Comment::where('parent_comment_id', $comment->comment_id)->delete();
    }

    $comment->delete();

    return response()->json([
        'message' => 'Komentar berhasil dihapus.'
    ]);
}


}
