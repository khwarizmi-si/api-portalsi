<?php

namespace App\Http\Controllers;

use App\Models\Bookmark;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BookmarkController extends Controller
{
    // Simpan bookmark
    public function store($postId)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $post = Post::findOrFail($postId);

        $bookmark = Bookmark::firstOrCreate([
            'user_id' => $user->user_id,   // 👈 pakai user_id
            'post_id' => $post->post_id,   // 👈 pakai post_id
        ]);

        return response()->json([
            'message' => 'Post bookmarked successfully',
            'bookmark' => $bookmark
        ]);
    }

    // Hapus bookmark
    public function destroy($postId)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $post = Post::findOrFail($postId);

        Bookmark::where('user_id', $user->user_id)   // 👈 user_id
            ->where('post_id', $post->post_id)       // 👈 post_id
            ->delete();

        return response()->json([
            'message' => 'Bookmark removed successfully',
        ]);
    }

    // Lihat semua bookmark user
    public function index()
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $bookmarks = $user->bookmarkedPosts()
            ->with('user')
            ->latest()
            ->get();

        return response()->json($bookmarks);
    }
}
