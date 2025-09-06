<?php

namespace App\Http\Controllers;

// 🔽 PASTIKAN SEMUA EVENT DI-IMPORT
use App\Events\CommentPublished;
use App\Events\CommentUpdated;
use App\Events\CommentDeleted;
use App\Events\NewNotification;
use App\Events\CommentCreated;
use App\Events\NotificationCreated;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Post;
use App\Models\Comment;
use App\Models\Notification;
use App\Models\User;

class CommentController extends Controller
{
    public function store(Request $request, $post_id)
    {
        $request->validate([
            'content' => 'required|string',
            'parent_comment_id' => 'nullable|exists:comments,comment_id'
        ]);

        $user_id = Auth::id();
        $post = Post::findOrFail($post_id);

        $comment = Comment::create([
            'post_id' => $post_id,
            'user_id' => $user_id,
            'content' => $request->input('content'),
            'parent_comment_id' => $request->input('parent_comment_id'),
        ]);

        // ✨ SIARKAN EVENT KOMENTAR BARU
        broadcast(new CommentPublished($comment))->toOthers();
        broadcast(new CommentCreated($comment));

        // 🔔 Notifikasi COMMENT ke pemilik POST
        if (!$request->filled('parent_comment_id') && $post->user_id != $user_id) {
            $notification = Notification::create([
                'recipient_id' => $post->user_id,
                'type' => 'comment',
                'related_user_id' => $user_id,
                'related_post_id' => $post_id,
                'related_comment_id' => $comment->comment_id,
                'created_at' => now(),
                'is_read' => false,
            ]);
            // ✨ SIARKAN EVENT NOTIFIKASI
            broadcast(new NewNotification($notification));
            broadcast(new NotificationCreated($notification));
        }

        // 🔁 Notifikasi REPLY ke pemilik komentar (kalau ada parent)
        if ($request->filled('parent_comment_id')) {
            $parent = Comment::where('comment_id', $request->parent_comment_id)->first();
            if ($parent && $parent->user_id != $user_id) {
                $notification = Notification::create([
                    'recipient_id' => $parent->user_id,
                    'type' => 'reply',
                    'related_user_id' => $user_id,
                    'related_post_id' => $post_id,
                    'related_comment_id' => $comment->comment_id,
                    'created_at' => now(),
                    'is_read' => false,
                ]);
                // ✨ SIARKAN EVENT NOTIFIKASI
                broadcast(new NewNotification($notification));
                broadcast(new NotificationCreated($notification));
            }
        }

        // 📣 Notifikasi MENTION
        $mentionedUsernames = collect(explode(' ', $request->content))
            ->filter(fn($word) => str_starts_with($word, '@'))
            ->map(fn($mention) => ltrim($mention, '@'));

        foreach ($mentionedUsernames as $username) {
            $mentionedUser = User::where('username', $username)->first();
            if ($mentionedUser && $mentionedUser->user_id != $user_id) {
                $notification = Notification::create([
                    'recipient_id' => $mentionedUser->user_id,
                    'type' => 'mention',
                    'related_user_id' => $user_id,
                    'related_post_id' => $post_id,
                    'related_comment_id' => $comment->comment_id,
                    'created_at' => now(),
                    'is_read' => false,
                ]);
                // ✨ SIARKAN EVENT NOTIFIKASI
                broadcast(new NewNotification($notification));
                broadcast(new NotificationCreated($notification));
            }
        }

        return response()->json([
            'message' => 'Komentar berhasil dikirim.',
            'data' => $comment->load('user') // Selalu muat relasi user untuk response
        ], 201);
    }

    public function getCommentsByPost($post_id)
    {
        $post = Post::findOrFail($post_id);
        $comments = $post->comments()
            ->with(['user', 'replies.user'])
            ->whereNull('parent_comment_id')
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'post_id' => $post_id,
            'comments' => $comments
        ]);
    }

    public function update(Request $request, $comment_id)
    {
        $request->validate(['content' => 'required|string']);
        $comment = Comment::findOrFail($comment_id);

        if ($comment->user_id !== Auth::id()) {
            return response()->json(['message' => 'Kamu tidak punya izin untuk mengedit komentar ini.'], 403);
        }

        $comment->content = $request->input('content');
        $comment->updated_at = now();
        $comment->save();

        // ✨ SIARKAN EVENT UPDATE KOMENTAR
        broadcast(new CommentUpdated($comment))->toOthers();

        return response()->json([
            'message' => 'Komentar berhasil diperbarui.',
            'data' => $comment
        ]);
    }

    public function destroy($comment_id)
    {
        $comment = Comment::findOrFail($comment_id);

        if ($comment->user_id !== Auth::id()) {
            return response()->json(['message' => 'Kamu tidak punya izin untuk menghapus komentar ini.'], 403);
        }

        $postId = $comment->post_id; // Simpan ID post sebelum dihapus

        if ($comment->parent_comment_id === null) {
            Comment::where('parent_comment_id', $comment->comment_id)->delete();
        }

        $comment->delete();

        // ✨ SIARKAN EVENT HAPUS KOMENTAR
        broadcast(new CommentDeleted((int) $comment_id, $postId))->toOthers();

        return response()->json(['message' => 'Komentar berhasil dihapus.']);
    }
}