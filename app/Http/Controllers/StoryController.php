<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Story;
use App\Models\StoryView;
use Illuminate\Support\Facades\Auth;

class StoryController extends Controller
{
    // ✅ Upload story
    public function store(Request $request)
    {
        $validated = $request->validate([
            'media_url' => 'required|url',
            'caption'   => 'nullable|string',
        ]);

        $story = Story::create([
            'user_id'    => Auth::id(),
            'media_url'  => $validated['media_url'],
            'caption'    => $validated['caption'] ?? null,
            'created_at' => now(),
            'expires_at' => now()->addHours(24), // ⏳ 24 jam story
        ]);

        return response()->json([
            'message' => 'Story berhasil dibuat.',
            'story' => $story
        ], 201);
    }

    // ✅ Ambil story dari user yang diikuti
    public function feed()
    {
        $user = Auth::user();

        $followedIds = $user->following()->pluck('users.user_id');

        $stories = Story::whereIn('user_id', $followedIds)
            ->where('expires_at', '>', now())
            ->latest()
            ->get();

        return response()->json($stories);
    }

    // ✅ Hapus story milik sendiri
    public function destroy($id)
    {
        $story = Story::where('story_id', $id)
                      ->where('user_id', Auth::id())
                      ->firstOrFail();

        $story->delete();

        return response()->json(['message' => 'Story berhasil dihapus.']);
    }

    // ✅ Lihat story (mencatat view)
    public function view($id)
    {
        $user = Auth::user();
        $story = Story::findOrFail($id);

        // 🚫 Tidak boleh melihat story sendiri
        if ($story->user_id == $user->user_id) {
            return response()->json([
                'message' => 'Tidak dapat melihat story milik sendiri.'
            ], 403);
        }

        // ✅ Cek apakah sudah melihat
        $alreadyViewed = StoryView::where('story_id', $story->story_id)
            ->where('viewer_id', $user->user_id)
            ->exists();

        if (!$alreadyViewed) {
            StoryView::create([
                'story_id'   => $story->story_id,
                'viewer_id'  => $user->user_id,
                'viewed_at'  => now()
            ]);
        }

        return response()->json([
            'message' => $alreadyViewed ? 'Sudah pernah melihat story.' : 'Story berhasil dilihat.'
        ]);
    }
}
