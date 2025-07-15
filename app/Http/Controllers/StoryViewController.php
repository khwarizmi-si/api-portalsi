<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StoryViewController extends Controller
{
    // ✅ Catat bahwa user telah melihat story tertentu
    public function store(Request $request, $story_id)
    {
        $viewer_id = Auth::id();

        // Cek apakah sudah pernah melihat story ini
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
}
