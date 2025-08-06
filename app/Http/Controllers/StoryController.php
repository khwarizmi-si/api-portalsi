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

// ✅ Ambil story dari user yang diikuti dan diri sendiri, lengkap dengan user info
public function feed()
{
    $user = Auth::user();

    // Ambil ID user yang diikuti + diri sendiri
    $followedIds = $user->following()->pluck('users.user_id')->toArray();
    $allIds = array_merge($followedIds, [$user->user_id]);

    // Ambil semua story dan user terkait (sekali query)
    $stories = Story::with(['user:user_id,username,profile_picture_url'])
        ->whereIn('user_id', $allIds)
        ->where('expires_at', '>', now())
        ->latest()
        ->get();

    // Group by user
    $grouped = $stories->groupBy('user.user_id')->map(function ($userStories) {
        $user = $userStories->first()->user;

        return [
            'user_id' => $user->user_id,
            'username' => $user->username,
            'profile_picture_url' => $user->profile_picture_url,
            'stories' => $userStories->map(function ($story) {
                return [
                    'story_id'   => $story->story_id,
                    'media_url'  => $story->media_url,
                    'caption'    => $story->caption,
                    'created_at' => $story->created_at,
                    'expires_at' => $story->expires_at,
                ];
            })->values()
        ];
    })->values(); // Reset keys ke array numerik

    return response()->json($grouped);
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

    // ✅ Lihat story (boleh lihat sendiri tapi tidak dicatat view)
    public function view($id)
    {
        $user = Auth::user();
        $story = Story::findOrFail($id);

        // Jika story bukan milik sendiri, catat view
        if ($story->user_id !== $user->user_id) {
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
                'message' => $alreadyViewed
                    ? 'Story sudah pernah dilihat.'
                    : 'Story berhasil dilihat dan dicatat.'
            ]);
        }

        // Jika melihat story sendiri, tetap bisa, tapi tidak dicatat
        return response()->json([
            'message' => 'Story milik sendiri berhasil dilihat (tanpa dicatat).'
        ]);
    }

    // ✅ (Opsional) Ambil semua story milik sendiri
    public function myStories()
    {
        $user = Auth::user();

        $stories = Story::where('user_id', $user->user_id)
            ->where('expires_at', '>', now())
            ->latest()
            ->get();

        return response()->json($stories);
    }
}
