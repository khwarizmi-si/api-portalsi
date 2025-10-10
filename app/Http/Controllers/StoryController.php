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

        private function getFeedUsers($authId)
    {
        return Story::selectRaw('user_id, MAX(created_at) as latest_story_time')
            ->where('expires_at', '>', now())
            ->whereHas('user', function ($q) use ($authId) {
                $q->where('user_id', '!=', $authId);
            })
            ->with(['user:user_id,username,profile_picture_url'])
            ->groupBy('user_id')
            ->orderByDesc('latest_story_time')
            ->get()
            ->values();
    }
    
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

            // ✅ Tambahan baru:
            'color_pallete' => 'nullable|json',
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
            'color_pallete' => $request->color_pallete, // ✅ baru
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
     * 🔹 Endpoint utama: ambil semua story feed (semua user dengan story aktif)
     */
    public function feed(Request $request)
    {
        $authId = auth()->id();

        $usersWithStories = $this->getFeedUsers($authId);

        $feed = $usersWithStories->map(function ($item) {
            $stories = Story::where('user_id', $item->user_id)
                ->where('expires_at', '>', now())
                ->orderBy('created_at', 'asc')
                ->get();

            return [
                'user_id' => $item->user->user_id,
                'username' => $item->user->username,
                'profile_picture_url' => $item->user->profile_picture_url,
                'is_viewed' => false,
                'stories' => $stories,
            ];
        });

        return response()->json([
            'message' => 'Berhasil mengambil story feed.',
            'data' => $feed,
        ]);
    }

    /**
     * 🔹 Endpoint: ambil story user selanjutnya berdasarkan urutan feed
     * Param: ?current_user_id=...
     */
    public function feedNextUser(Request $request)
    {
        $currentUserId = $request->input('current_user_id');

        if (!$currentUserId) {
            return response()->json(['message' => 'Parameter current_user_id diperlukan.'], 400);
        }

        $authId = auth()->id();
        $usersWithStories = $this->getFeedUsers($authId);

        $currentIndex = $usersWithStories->search(fn($item) => (int)$item->user_id === (int)$currentUserId);

        if ($currentIndex === false || $currentIndex + 1 >= $usersWithStories->count()) {
            return response()->json([
                'message' => 'Tidak ada user lagi yang memiliki story aktif (next).',
                'data' => []
            ]);
        }

        $nextUser = $usersWithStories[$currentIndex + 1];

        $stories = Story::where('user_id', $nextUser->user_id)
            ->where('expires_at', '>', now())
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'message' => 'Berhasil mengambil story user selanjutnya.',
            'data' => [
                'user_id' => $nextUser->user->user_id,
                'username' => $nextUser->user->username,
                'profile_picture_url' => $nextUser->user->profile_picture_url,
                'stories' => $stories,
            ]
        ]);
    }

    /**
     * 🔹 Endpoint: ambil story user sebelumnya berdasarkan urutan feed
     * Param: ?current_user_id=...
     */
    public function feedPreviousUser(Request $request)
    {
        $currentUserId = $request->input('current_user_id');

        if (!$currentUserId) {
            return response()->json(['message' => 'Parameter current_user_id diperlukan.'], 400);
        }

        $authId = auth()->id();
        $usersWithStories = $this->getFeedUsers($authId);

        $currentIndex = $usersWithStories->search(fn($item) => (int)$item->user_id === (int)$currentUserId);

        if ($currentIndex === false || $currentIndex - 1 < 0) {
            return response()->json([
                'message' => 'Tidak ada user sebelumnya yang memiliki story aktif.',
                'data' => []
            ]);
        }

        $prevUser = $usersWithStories[$currentIndex - 1];

        $stories = Story::where('user_id', $prevUser->user_id)
            ->where('expires_at', '>', now())
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'message' => 'Berhasil mengambil story user sebelumnya.',
            'data' => [
                'user_id' => $prevUser->user->user_id,
                'username' => $prevUser->user->username,
                'profile_picture_url' => $prevUser->user->profile_picture_url,
                'stories' => $stories,
            ]
        ]);
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
                    'color_pallete' => $story->color_pallete, // ✅ baru
                    'created_at' => $story->created_at,
                    'expires_at' => $story->expires_at,
                ];
            });

        return response()->json($stories);
    }

    /**
     * Ambil semua story berdasarkan user_id tertentu
     */
    public function getByUser($userId)
    {
        $authUser = Auth::user();

        $stories = Story::with(['user:user_id,username,profile_picture_url'])
            ->where('user_id', $userId)
            ->where('expires_at', '>', now())
            ->latest()
            ->get();

        $user = optional($stories->first()?->user);

        $stories = $stories->map(function ($story) use ($authUser) {
            $alreadyViewed = \DB::table('story_views')
                ->where('story_id', $story->story_id)
                ->where('viewer_id', $authUser->user_id)
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
                'color_pallete' => $story->color_pallete, // ✅ baru
                'created_at' => $story->created_at,
                'expires_at' => $story->expires_at,
                'is_viewed' => $alreadyViewed,
            ];
        });

        return response()->json([
            'user_id' => $userId,
            'username' => $user->username ?? null,
            'profile_picture_url' => $user->profile_picture_url ?? null,
            'stories' => $stories
        ]);
    }

    /**
     * Ambil story expired (arsip)
     */
    public function myArchivedStories(Request $request)
    {
        $authUser = Auth::user();

        $page    = max(1, (int) $request->input('page', 1));
        $perPage = max(1, (int) $request->input('per_page', 10));

        $query = Story::with(['user:user_id,username,profile_picture_url'])
            ->where('user_id', $authUser->user_id)
            ->where('expires_at', '<=', now())
            ->latest();

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $stories = $paginator->getCollection()->map(function ($story) use ($authUser) {
            $alreadyViewed = \DB::table('story_views')
                ->where('story_id', $story->story_id)
                ->where('viewer_id', $authUser->user_id)
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
                'color_pallete' => $story->color_pallete, // ✅ baru
                'created_at' => $story->created_at,
                'expires_at' => $story->expires_at,
                'is_viewed' => $alreadyViewed,
            ];
        });

        return response()->json([
            'current_page'   => $paginator->currentPage(),
            'per_page'       => $paginator->perPage(),
            'total'          => $paginator->total(),
            'next_page_url'  => $paginator->nextPageUrl(),
            'prev_page_url'  => $paginator->previousPageUrl(),
            'last_page_url'  => $paginator->url($paginator->lastPage()),
            'stories'        => $stories->values(),
        ]);
    }


}
