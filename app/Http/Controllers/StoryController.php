<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Story;
use App\Models\StoryView;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class StoryController extends Controller
{
    /**
     * Upload story baru (image, video, music)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'type'        => 'required|in:image,video,music',
            'caption'     => 'nullable',
            'caption.*'   => 'nullable|string', // Kalau array caption
            'media'       => 'nullable', // Bisa single atau array file
            'media.*'     => 'nullable|file|mimes:jpeg,png,jpg,mp4,mp3,wav|max:20480',
        ]);
    
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'User belum login'], 401);
        }
    
        // Pastikan $mediaFiles selalu array
        $mediaFiles = $request->file('media');
        if ($mediaFiles && !is_array($mediaFiles)) {
            $mediaFiles = [$mediaFiles];
        }
    
        // Kalau bukan music, wajib ada file
        if ($request->type !== 'music' && empty($mediaFiles)) {
            return response()->json(['message' => 'Media file wajib diunggah untuk image/video.'], 422);
        }
    
        $stories = [];
    
        // Upload media jika ada
        if (!empty($mediaFiles)) {
            foreach ($mediaFiles as $index => $file) {
                $path = $file->store('stories', 'public');
    
                $stories[] = Story::create([
                    'user_id'   => $user->user_id,
                    'type'      => $request->type,
                    'caption'   => is_array($request->caption) ? ($request->caption[$index] ?? null) : $request->caption,
                    'media_url' => $path,
                ]);
            }
        } else {
            // Untuk music tanpa file
            $stories[] = Story::create([
                'user_id'   => $user->user_id,
                'type'      => $request->type,
                'caption'   => $request->caption,
                'media_url' => null,
            ]);
        }
    
        return response()->json([
            'message' => 'Story berhasil dibuat',
            'stories' => $stories
        ], 201);
    }
    
    
    /**
     * Ambil story dari user yang diikuti + diri sendiri
     */
    public function feed()
    {
        $user = Auth::user();

        $followedIds = $user->following()->pluck('users.user_id')->toArray();
        $allIds = array_merge($followedIds, [$user->user_id]);

        $stories = Story::with(['user:user_id,username,profile_picture_url'])
            ->whereIn('user_id', $allIds)
            ->where('expires_at', '>', now())
            ->latest()
            ->get();

        $grouped = $stories->groupBy('user.user_id')->map(function ($userStories) {
            $user = $userStories->first()->user;

            return [
                'user_id' => $user->user_id,
                'username' => $user->username,
                'profile_picture_url' => $user->profile_picture_url,
                'stories' => $userStories->map(function ($story) {
                    return [
                        'story_id'                => $story->story_id,
                        'type'                    => $story->type,
                        'media_url'               => $story->media_url,
                        'caption'                 => $story->caption,
                        'music_track_name'        => $story->music_track_name,
                        'music_artist_name'       => $story->music_artist_name,
                        'music_preview_url'       => $story->music_preview_url,
                        'music_start_position_ms' => $story->music_start_position_ms,
                        'music_display_style'     => $story->music_display_style,
                        'created_at'              => $story->created_at,
                        'expires_at'              => $story->expires_at,
                    ];
                })->values()
            ];
        })->values();

        return response()->json($grouped);
    }

    /**
     * Hapus story milik sendiri
     */
    public function destroy($id)
    {
        $story = Story::where('story_id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        if ($story->media_url) {
            $relativePath = str_replace(asset('storage') . '/', '', $story->media_url);
            Storage::disk('public')->delete($relativePath);
        }

        $story->delete();

        return response()->json(['message' => 'Story berhasil dihapus.']);
    }

    /**
     * Lihat story (catat view jika bukan milik sendiri)
     */
    public function view($id)
    {
        $user = Auth::user();
        $story = Story::findOrFail($id);

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

        return response()->json([
            'message' => 'Story milik sendiri berhasil dilihat (tanpa dicatat).'
        ]);
    }

    /**
     * Ambil semua story milik sendiri
     */
    public function myStories()
    {
        $user = Auth::user();

        $stories = Story::where('user_id', $user->user_id)
            ->where('expires_at', '>', now())
            ->latest()
            ->get()
            ->map(function ($story) {
                return [
                    'story_id'                => $story->story_id,
                    'type'                    => $story->type,
                    'media_url'               => $story->media_url,
                    'caption'                 => $story->caption,
                    'music_track_name'        => $story->music_track_name,
                    'music_artist_name'       => $story->music_artist_name,
                    'music_preview_url'       => $story->music_preview_url,
                    'music_start_position_ms' => $story->music_start_position_ms,
                    'music_display_style'     => $story->music_display_style,
                    'created_at'              => $story->created_at,
                    'expires_at'              => $story->expires_at,
                ];
            });

        return response()->json($stories);
    }
}
