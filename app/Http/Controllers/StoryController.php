<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Story;
use App\Models\StoryView;
use App\Models\StoryMention;
use App\Models\Notification;
use App\Models\User;
use App\Events\StoryCreated;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class StoryController extends Controller
{
    /**
     * Upload story baru (image, video, music)
     */
    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|in:image,video,music',
            'media' => 'nullable|file|mimes:jpg,jpeg,png,mp4,mp3,wav',
            'caption' => 'nullable|string',
            'music_track_name' => 'nullable|string|max:255',
            'music_artist_name' => 'nullable|string|max:255',
            'music_preview_url' => 'nullable|url',
            'music_album_art_url' => 'nullable|url',
            'music_start_position_ms' => 'nullable|integer',
            'music_clip_duration_ms' => 'nullable|integer',
            'music_display_style' => 'nullable|string|max:50',
            'music_sticker_position_x' => 'nullable|numeric',
            'music_sticker_position_y' => 'nullable|numeric',
        ]);

        $user = Auth::user();
        $mediaPath = null;

        // Kalau ada file media diupload
        if ($request->hasFile('media')) {
            $mediaPath = $request->file('media')->store('uploads/stories', 'public');
        }

        // Insert ke DB
        $story = Story::create([
            'user_id' => $user->user_id,
            'media_url' => $mediaPath ? asset('storage/' . $mediaPath) : null,
            'type' => $request->type,
            'music_track_name' => $request->music_track_name,
            'music_artist_name' => $request->music_artist_name,
            'music_preview_url' => $request->music_preview_url,
            'music_album_art_url' => $request->music_album_art_url,
            'music_start_position_ms' => $request->music_start_position_ms,
            'music_clip_duration_ms' => $request->music_clip_duration_ms,
            'music_display_style' => $request->music_display_style,
            'music_sticker_position_x' => $request->music_sticker_position_x,
            'music_sticker_position_y' => $request->music_sticker_position_y,
            'caption' => $request->caption,
            'created_at' => now(),
            'expires_at' => Carbon::now()->addHours(24),
        ]);

// 👥 Tangani mention di caption
if ($request->filled('caption')) {
    preg_match_all('/@(\w+)/', $request->caption, $mentions);

    foreach ($mentions[1] as $username) {
        $mentionedUser = User::where('username', $username)->first();
        if ($mentionedUser && $mentionedUser->user_id !== $user->user_id) {
            StoryMention::create([
                'story_id' => $story->story_id,
                'mentioned_user_id' => $mentionedUser->user_id
            ]);

            Notification::create([
                'recipient_id'     => $mentionedUser->user_id,
                'type'             => 'story_mention',
                'related_user_id'  => $user->user_id,
                'related_story_id' => $story->story_id,
                'created_at'       => now(),
                'is_read'          => false,
            ]);
        }
    }
}


        // Broadcast story created event
        broadcast(new StoryCreated($story));

        return response()->json([
            'message' => 'Story uploaded successfully',
            'data' => $story
        ]);
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

        $grouped = $stories->groupBy('user.user_id')->map(function ($userStories) use ($user) {
            $storyOwner = $userStories->first()->user;

            $storyIds = $userStories->pluck('story_id')->toArray();
            $viewedCount = \DB::table('story_views')
                ->whereIn('story_id', $storyIds)
                ->where('viewer_id', $user->user_id)
                ->count();

            $isAllViewed = $viewedCount >= count($storyIds);

            return [
                'user_id' => $storyOwner->user_id,
                'username' => $storyOwner->username,
                'profile_picture_url' => $storyOwner->profile_picture_url,
                'is_viewed' => $isAllViewed,
                'stories' => $userStories->map(function ($story) use ($user) {
                    $alreadyViewed = \DB::table('story_views')
                        ->where('story_id', $story->story_id)
                        ->where('viewer_id', $user->user_id)
                        ->exists();

                    return [
                        'story_id' => $story->story_id,
                        'type' => $story->type,
                        'media_url' => $story->media_url,
                        'caption' => $story->caption,
                        'music_track_name' => $story->music_track_name,
                        'music_artist_name' => $story->music_artist_name,
                        'music_preview_url' => $story->music_preview_url,
                        'music_album_art_url' => $story->music_album_art_url,
                        'music_start_position_ms' => $story->music_start_position_ms,
                        'music_clip_duration_ms' => $story->music_clip_duration_ms,
                        'music_display_style' => $story->music_display_style,
                        'music_sticker_position_x' => $story->music_sticker_position_x,
                        'music_sticker_position_y' => $story->music_sticker_position_y,
                        'created_at' => $story->created_at,
                        'expires_at' => $story->expires_at,
                        'is_viewed' => $alreadyViewed,
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
                    'story_id' => $story->story_id,
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
                    'story_id' => $story->story_id,
                    'type' => $story->type,
                    'media_url' => $story->media_url,
                    'caption' => $story->caption,
                    'music_track_name' => $story->music_track_name,
                    'music_artist_name' => $story->music_artist_name,
                    'music_preview_url' => $story->music_preview_url,
                    'music_album_art_url' => $story->music_album_art_url,
                    'music_start_position_ms' => $story->music_start_position_ms,
                    'music_clip_duration_ms' => $story->music_clip_duration_ms,
                    'music_display_style' => $story->music_display_style,
                    'music_sticker_position_x' => $story->music_sticker_position_x,
                    'music_sticker_position_y' => $story->music_sticker_position_y,
                    'created_at' => $story->created_at,
                    'expires_at' => $story->expires_at,
                ];
            });

        return response()->json($stories);
    }

    /**
 * Ambil semua story milik user tertentu (hanya story_id saja)
 */
public function getByUser($userId)
{
    $authUser = Auth::user();

    $stories = Story::with(['user:user_id,username,profile_picture_url'])
        ->where('user_id', $userId)
        ->where('expires_at', '>', now())
        ->latest()
        ->get(['story_id', 'user_id', 'created_at', 'expires_at']);

    if ($stories->isEmpty()) {
        return response()->json([
            'user_id' => $userId,
            'username' => null,
            'profile_picture_url' => null,
            'stories' => []
        ]);
    }

    $storyOwner = $stories->first()->user;

    return response()->json([
        'user_id' => $storyOwner->user_id,
        'username' => $storyOwner->username,
        'profile_picture_url' => $storyOwner->profile_picture_url,
        'stories' => $stories->map(function ($story) {
            return [
                'story_id' => $story->story_id,
                'created_at' => $story->created_at,
                'expires_at' => $story->expires_at,
            ];
        })->values()
    ]);
}

}
