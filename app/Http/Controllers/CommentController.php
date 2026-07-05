<?php

namespace App\Http\Controllers;

// 🔽 Perbarui import
use App\Events\CommentCreated;
use App\Events\CommentDeleted;
use App\Events\CommentUpdated;
use App\Events\NotificationCreated;
use App\Models\Comment;
use App\Models\CommentLike;
use App\Models\Notification;
// ✅ Gunakan hanya satu event untuk setiap aksi
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CommentController extends Controller
{
    public function store(Request $request, $post_id)
    {
        $request->validate([
            'content' => 'required|string',
            'parent_comment_id' => 'nullable|exists:comments,comment_id',
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
        // ✅ Hanya satu event, dikirim ke semua KECUALI pengirim
        broadcast(new CommentCreated($comment))->toOthers();

        // 🔔 Notifikasi COMMENT ke pemilik POST
        // ❌ Hindari notifikasi diri sendiri
        if (! $request->filled('parent_comment_id') && $post->user_id != $user_id) {
            $notification = Notification::createFor($post->user_id, [
                'type' => 'comment',
                'related_user_id' => $user_id,
                'related_post_id' => $post_id,
                'related_comment_id' => $comment->comment_id,
                'is_read' => false,
            ]);

            // ✨ SIARKAN EVENT NOTIFIKASI (jika tidak ditekan preferensi)
            if ($notification) {
                broadcast(new NotificationCreated($notification));
            }
        }

        // 🔁 Notifikasi REPLY ke pemilik komentar (kalau ada parent)
        if ($request->filled('parent_comment_id')) {
            $parent = Comment::where('comment_id', $request->parent_comment_id)->first();
            // ❌ Hindari notifikasi diri sendiri
            if ($parent && $parent->user_id != $user_id) {
                $notification = Notification::createFor($parent->user_id, [
                    'type' => 'reply',
                    'related_user_id' => $user_id,
                    'related_post_id' => $post_id,
                    'related_comment_id' => $comment->comment_id,
                    'is_read' => false,
                ]);
                // ✨ SIARKAN EVENT NOTIFIKASI (jika tidak ditekan preferensi)
                if ($notification) {
                    broadcast(new NotificationCreated($notification));
                }
            }
        }

        // 📣 Notifikasi MENTION
        preg_match_all('/@([A-Za-z0-9._]+)/', (string) $request->content, $mentionMatches);
        $mentionedUsernames = collect($mentionMatches[1] ?? [])->unique();

        foreach ($mentionedUsernames as $username) {
            $mentionedUser = User::where('username', $username)->first();
            // ❌ Hindari notifikasi diri sendiri
            if ($mentionedUser && $mentionedUser->user_id != $user_id) {
                $notification = Notification::createFor($mentionedUser->user_id, [
                    'type' => 'mention',
                    'related_user_id' => $user_id,
                    'related_post_id' => $post_id,
                    'related_comment_id' => $comment->comment_id,
                    'is_read' => false,
                ]);
                // ✨ SIARKAN EVENT NOTIFIKASI (jika tidak ditekan preferensi)
                if ($notification) {
                    broadcast(new NotificationCreated($notification));
                }
            }
        }

        return response()->json([
            'message' => 'Komentar berhasil dikirim.',
            'data' => $comment->load('user'),
        ], 201);
    }

    public function getCommentsByPost($post_id)
    {
        $authId = Auth::id();
        $post = Post::findOrFail($post_id);

        $comments = $post->comments()
            ->withCount('likes') // hitung jumlah likes
            ->withExists([
                'likes as is_liked' => fn ($q) => $q->where('user_id', $authId),
            ]) // cek apakah user login sudah like
            ->with([
                'user',
                'replies.user',
                'replies.likes',
                'replies' => function ($q) use ($authId) {
                    $q->withCount('likes')
                        ->withExists([
                            'likes as is_liked' => fn ($q2) => $q2->where('user_id', $authId),
                        ])
                        ->orderByDesc('likes_count')
                        ->orderBy('created_at', 'desc');
                },
            ])
            ->whereNull('parent_comment_id')
            ->orderByDesc('likes_count') // komentar dengan like terbanyak dulu
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'post_id' => $post_id,
            'comments' => $comments,
        ]);
    }

    public function like($comment_id)
    {
        $user_id = Auth::id();
        $comment = Comment::findOrFail($comment_id);

        // Cek apakah sudah pernah like
        $alreadyLiked = CommentLike::where('comment_id', $comment_id)
            ->where('user_id', $user_id)
            ->exists();

        if ($alreadyLiked) {
            return response()->json(['message' => 'Kamu sudah menyukai komentar ini.'], 400);
        }

        $like = CommentLike::create([
            'comment_id' => $comment_id,
            'user_id' => $user_id,
        ]);

        return response()->json([
            'message' => 'Komentar berhasil disukai.',
            'data' => $like,
        ], 201);
    }

    public function unlike($comment_id)
    {
        $user_id = Auth::id();
        $comment = Comment::findOrFail($comment_id);

        $deleted = CommentLike::where('comment_id', $comment_id)
            ->where('user_id', $user_id)
            ->delete();

        if (! $deleted) {
            return response()->json(['message' => 'Kamu belum pernah menyukai komentar ini.'], 400);
        }

        return response()->json(['message' => 'Like pada komentar berhasil dihapus.']);
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
            'data' => $comment,
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
