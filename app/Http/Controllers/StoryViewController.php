<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Story;
use App\Models\User;

class StoryViewController extends Controller
{
    // ✅ Catat view (sudah ada)
    public function store(Request $request, $story_id)
    {
        $viewer_id = Auth::id();

        $alreadyViewed = DB::table('story_views')
            ->where('story_id', $story_id)
            ->where('viewer_id', $viewer_id)
            ->exists();

        if (!$alreadyViewed) {
            DB::table('story_views')->insert([
                'story_id'   => $story_id,
                'viewer_id'  => $viewer_id,
                'viewed_at'  => now(),
            ]);
        }

        return response()->json([
            'message' => $alreadyViewed
                ? 'Sudah pernah melihat story ini'
                : 'Berhasil mencatat view story'
        ]);
    }

    // ✅ Ambil siapa saja yang melihat story saya
    public function viewers($story_id)
    {
        $story = Story::where('story_id', $story_id)
            ->where('user_id', Auth::id()) // hanya bisa lihat story sendiri
            ->firstOrFail();

        $viewers = DB::table('story_views')
            ->join('users', 'story_views.viewer_id', '=', 'users.user_id')
            ->where('story_views.story_id', $story_id)
            ->select('users.user_id', 'users.username', 'users.profile_picture_url', 'story_views.viewed_at')
            ->orderBy('story_views.viewed_at', 'desc')
            ->get();

        return response()->json([
            'story_id' => $story->story_id,
            'total_viewers' => $viewers->count(),
            'viewers' => $viewers
        ]);
    }
}
