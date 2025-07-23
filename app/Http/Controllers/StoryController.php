<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Story;
use App\Models\StoryView;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class StoryController extends Controller
{
    // ✅ Upload story (dengan file)
    public function store(Request $request)
    {
        $validated = $request->validate([
            'media'   => 'required|file|mimes:jpg,jpeg,png,mp4,mov|max:51200', // max 50MB
            'caption' => 'nullable|string',
        ]);

        // Simpan file ke storage/app/public/uploads/stories
        $path = $request->file('media')->store('uploads/stories', 'public');

        // Generate URL yang bisa diakses publik
        $mediaUrl = asset('storage/' . $path);

        $story = Story::create([
            'user_id'    => Auth::id(),
            'media_url'  => $mediaUrl,
            'caption'    => $validated['caption'] ?? null,
            'created_at' => now(),
            'expires_at' => now()->addHours(24),
        ]);

        return response()->json([
            'message' => 'Story berhasil dibuat.',
            'story'   => $story
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

        // Hapus file dari storage jika ada
        if ($story->media_url) {
            $relativePath = str_replace(asset('storage') . '/', '', $story->media_url);
            Storage::disk('public')->delete($relativePath);
        }

        $story->delete();

        return response()->json(['message' => 'Story berhasil dihapus.']);
    }

    // ✅ Lihat story (mencatat view)
    public function view($id)
    {
        $user = Auth::user();
        $story = Story::findOrFail($id);

        if ($story->user_id == $user->user_id) {
            return response()->json([
                'message' => 'Tidak dapat melihat story milik sendiri.'
            ], 403);
        }

        $alreadyViewed = StoryView::where('story_id', $story->story_id)
            ->where('viewer_id', $user->user_id)
            ->exists();

        if (!$alreadyViewed) {
            StoryView::create([
                'story_id'  => $story->story_id,
                'viewer_id' => $user->user_id,
                'viewed_at' => now()
            ]);
        }

        return response()->json([
            'message' => $alreadyViewed ? 'Sudah pernah melihat story.' : 'Story berhasil dilihat.'
        ]);
    }
}
