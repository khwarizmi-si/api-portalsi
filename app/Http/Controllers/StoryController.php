<?php

namespace App\Http\Controllers;

use App\Events\NotificationCreated;
use App\Events\StoryCreated;
use App\Models\Notification;
use App\Models\Story;
use App\Models\StoryMention;
use App\Models\StoryView;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class StoryController extends Controller
{
    private function mediaDisk(): string
    {
        return config('filesystems.default', 'public');
    }

    private function storagePathFromUrl(string $url): string
    {
        $path = ltrim(parse_url($url, PHP_URL_PATH) ?? $url, '/');

        return preg_replace('#^storage/#', '', $path);
    }

    /**
     * Upload story baru (image, video, music)
     */
    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|in:image,video,music',
            'media' => 'nullable|file|max:512000',
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

        $media = $request->file('media');
        if ($media) {
            $extension = strtolower($media->getClientOriginalExtension());
            $extensionsByType = [
                'image' => ['jpg', 'jpeg', 'png', 'webp', 'gif'],
                'video' => ['mp4', 'mov', 'webm', 'avi', '3gp', 'mkv', 'm4v'],
                'music' => ['mp3', 'wav', 'm4a', 'aac', 'ogg'],
            ];
            if (! in_array($extension, $extensionsByType[$request->type] ?? [], true)) {
                return response()->json([
                    'message' => 'Format berkas tidak sesuai dengan jenis cerita yang dipilih.',
                    'errors' => ['media' => ['Format media cerita tidak didukung.']],
                ], 422);
            }
        } elseif (in_array($request->type, ['image', 'video'], true)) {
            return response()->json([
                'message' => 'Foto atau video cerita wajib dipilih.',
                'errors' => ['media' => ['Media wajib dipilih.']],
            ], 422);
        }

        $user = Auth::user();
        $mediaPath = null;
        // Configured default disk (r2 in prod, public locally).
        $disk = $this->mediaDisk();

        // Kalau ada file media diupload
        if ($media) {
            try {
                $mediaPath = $media->store('uploads/stories', $disk);
            } catch (\Throwable $error) {
                \Log::error('Story media upload failed', [
                    'user_id' => $user->user_id,
                    'type' => $request->type,
                    'extension' => $extension,
                    'mime' => $media->getMimeType(),
                    'size' => $media->getSize(),
                    'error' => $error->getMessage(),
                ]);

                return response()->json(['message' => 'Media cerita gagal disimpan. Silakan coba lagi.'], 503);
            }
            if (! $mediaPath) {
                return response()->json(['message' => 'Media cerita gagal disimpan. Silakan coba lagi.'], 503);
            }
        }

        // Insert ke DB
        $story = Story::create([
            'user_id' => $user->user_id,
            'media_url' => $mediaPath ? Storage::disk($disk)->url($mediaPath) : null,
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
            preg_match_all('/@([A-Za-z0-9._]+)/', $request->caption, $mentions);

            foreach ($mentions[1] as $username) {
                $mentionedUser = User::where('username', $username)->first();
                if ($mentionedUser && $mentionedUser->user_id !== $user->user_id) {
                    StoryMention::create([
                        'story_id' => $story->story_id,
                        'mentioned_user_id' => $mentionedUser->user_id,
                    ]);

                    $notification = Notification::create([
                        'recipient_id' => $mentionedUser->user_id,
                        'type' => 'story_mention',
                        'related_user_id' => $user->user_id,
                        'related_story_id' => $story->story_id,
                        'created_at' => now(),
                        'is_read' => false,
                    ]);
                    broadcast(new NotificationCreated($notification));
                }
            }
        }

        // Broadcast story created event
        broadcast(new StoryCreated($story));

        return response()->json([
            'message' => 'Story uploaded successfully',
            'data' => $story,
        ]);
    }

    /**
     * Ambil story dari user yang diikuti + diri sendiri
     */
    public function feed()
    {
        $authUser = Auth::user();

        // 🔹 Ambil daftar user_id yang masuk feed (diri sendiri + yang diikuti)
        $followedIds = $authUser->following()->wherePivot('status', 'accepted')->pluck('users.user_id')->toArray();
        $allIds = array_merge([$authUser->user_id], $followedIds);

        // 🔹 Ambil daftar user yang punya story aktif
        $usersWithStories = Story::with('user:user_id,username,profile_picture_url')
            ->whereIn('user_id', $allIds)
            ->where('expires_at', '>', now())
            ->selectRaw('user_id, MAX(created_at) as latest_story_time')
            ->groupBy('user_id')
            ->orderBy('latest_story_time', 'asc')
            ->get();

        // 🔹 Ambil semua story aktif
        $stories = Story::with(['user:user_id,username,profile_picture_url'])
            ->whereIn('user_id', $usersWithStories->pluck('user_id'))
            ->where('expires_at', '>', now())
            ->orderBy('created_at', 'asc')
            ->get();

        // 🔹 Kelompokkan story per user
        $grouped = $usersWithStories->map(function ($userWithStory) use ($stories, $authUser) {
            $userStories = $stories->where('user_id', $userWithStory->user_id);
            if ($userStories->isEmpty()) {
                return null;
            }

            $storyOwner = $userStories->first()?->user;

            $storyIds = $userStories->pluck('story_id')->toArray();
            $viewedCount = \DB::table('story_views')
                ->whereIn('story_id', $storyIds)
                ->where('viewer_id', $authUser->user_id)
                ->count();

            $isAllViewed = $viewedCount >= count($storyIds);

            return [
                'user_id' => $storyOwner->user_id,
                'username' => $storyOwner->username,
                'profile_picture_url' => $storyOwner->profile_picture_url,
                'is_viewed' => $isAllViewed,
                'stories' => $userStories->map(function ($story) use ($authUser) {
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
                        'color_pallete' => $story->color_pallete,
                        'created_at' => $story->created_at,
                        'expires_at' => $story->expires_at,
                        'is_viewed' => $alreadyViewed,
                    ];
                })->values(),
            ];
        })->filter()->values();

        $mutualCount = $grouped->count();
        $suggestions = collect();

        // 🔹 Tampilkan saran hanya jika story mutual < 3
        if ($mutualCount < 3) {
            $excludedIds = array_merge([$authUser->user_id], $followedIds);

            /**
             * 🔸 PRIORITAS 1: Followers yang belum difollow balik
             */
            $notFollowedBack = $authUser->followers()
                ->whereNotIn('users.user_id', $followedIds)
                ->where('users.user_id', '<>', $authUser->user_id)
                ->limit(8)
                ->get(['users.user_id', 'users.username', 'users.profile_picture_url']);

            $suggestions = $suggestions->merge($notFollowedBack);

            /**
             * 🔸 PRIORITAS 2: "Teman dari teman" (orang yang diikuti oleh teman yang lu ikuti)
             * Misal lu follow A, dan A follow B → maka B bisa jadi suggestion
             */
            if ($suggestions->count() < 8) {
                $friendOfFriends = \DB::table('follows as f1')
                    ->join('follows as f2', 'f1.followed_id', '=', 'f2.follower_id')
                    ->join('users', 'users.user_id', '=', 'f2.followed_id')
                    ->whereIn('f1.follower_id', $followedIds)
                    ->whereNotIn('users.user_id', $excludedIds)
                    ->select('users.user_id', 'users.username', 'users.profile_picture_url')
                    ->distinct()
                    ->limit(8 - $suggestions->count())
                    ->get();

                $suggestions = $suggestions->merge($friendOfFriends);
            }

            /**
             * 🔸 PRIORITAS 3: Random user (cadangan)
             */
            if ($suggestions->count() < 8) {
                $randomUsers = User::whereNotIn('user_id', $excludedIds)
                    ->inRandomOrder()
                    ->limit(8 - $suggestions->count())
                    ->get(['user_id', 'username', 'profile_picture_url']);

                $suggestions = $suggestions->merge($randomUsers);
            }
        }

        return response()->json([
            'stories' => $grouped,
            'suggestions' => $suggestions->values(),
        ]);
    }

    public function feedUser(Request $request, $userId)
    {
        $authUser = Auth::user();

        // Target yang ingin dilihat storynya (boleh siapa saja, tidak harus diikuti).
        $target = User::find($userId);
        if (! $target) {
            return response()->json(['message' => 'Pengguna tidak ditemukan.', 'data' => []], 404);
        }

        // Privasi: akun privat hanya boleh dilihat diri sendiri atau follower yang sudah diterima.
        $isSelf = $authUser->user_id == $target->user_id;
        $isAcceptedFollower = $authUser->following()
            ->where('users.user_id', $target->user_id)
            ->wherePivot('status', 'accepted')
            ->exists();
        if ($target->is_private && ! $isSelf && ! $isAcceptedFollower) {
            return response()->json([
                'message' => 'Akun ini privat. Ikuti dan tunggu persetujuan untuk melihat ceritanya.',
                'data' => [],
            ], 403);
        }

        // Konteks urutan feed (diri sendiri + yang diikuti accepted) untuk navigasi prev/next.
        $followedIds = $authUser->following()->wherePivot('status', 'accepted')->pluck('users.user_id')->toArray();
        $allIds = array_merge([$authUser->user_id], $followedIds);
        $usersWithStories = Story::query()
            ->whereIn('user_id', $allIds)
            ->where('expires_at', '>', now())
            ->selectRaw('user_id, MAX(created_at) as latest_story_time')
            ->groupBy('user_id')
            ->orderByDesc('latest_story_time')
            ->get()
            ->values();

        // Posisi target dalam feed. Kalau tidak diikuti, view berdiri sendiri (tanpa prev/next).
        $currentIndex = $usersWithStories->search(fn ($u) => $u->user_id == $target->user_id);

        // Ambil story aktif milik target.
        $stories = Story::where('user_id', $target->user_id)
            ->where('expires_at', '>', now())
            ->orderBy('created_at', 'asc')
            ->get();

        if ($stories->isEmpty()) {
            return response()->json([
                'message' => 'Tidak ada story aktif.',
                'data' => [],
            ], 404);
        }

        $formattedStories = $stories->map(function ($story) use ($authUser) {
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
                'color_pallete' => $story->color_pallete,
                'created_at' => $story->created_at,
                'expires_at' => $story->expires_at,
                'is_viewed' => $alreadyViewed,
            ];
        });

        // Urutan navigasi prev/next: pakai urutan rail (?order=) bila ada agar linear dan
        // BERHENTI di ujung (tidak loop kembali ke awal). Kalau tidak ada, pakai urutan feed.
        $orderedIds = collect(explode(',', (string) $request->query('order', '')))
            ->map(fn ($v) => (int) trim($v))
            ->filter(fn ($v) => $v > 0)
            ->values();
        $navList = $orderedIds->isNotEmpty() ? $orderedIds : $usersWithStories->pluck('user_id');
        // Sisakan hanya user yang masih punya story aktif supaya navigasi tidak mendarat di 404.
        $activeIds = $navList->isNotEmpty()
            ? Story::whereIn('user_id', $navList->all())
                ->where('expires_at', '>', now())
                ->distinct()->pluck('user_id')->flip()
            : collect();
        $navList = $navList->filter(fn ($id) => $activeIds->has($id))->values();
        $navIndex = $navList->search($target->user_id);
        $prevUserId = ($navIndex !== false && $navIndex > 0) ? $navList->get($navIndex - 1) : null;
        $nextUserId = ($navIndex !== false && $navIndex < $navList->count() - 1) ? $navList->get($navIndex + 1) : null;

        return response()->json([
            'current_user' => [
                'user_id' => $target->user_id,
                'username' => $target->username,
                'full_name' => $target->full_name,
                'is_verified' => (bool) $target->is_verified,
                'role' => $target->role,
                'profile_picture_url' => $target->profile_picture_url,
            ],
            'stories' => $formattedStories,
            'prev_user_id' => $prevUserId,
            'next_user_id' => $nextUserId,
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
            Storage::disk($this->mediaDisk())->delete(
                $this->storagePathFromUrl($story->media_url)
            );
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

        if (! $alreadyViewed) {
            StoryView::create([
                'story_id' => $story->story_id,
                'viewer_id' => $user->user_id,
                'viewed_at' => now(),
            ]);
        }

        return response()->json([
            'message' => $alreadyViewed
                ? 'Story sudah pernah dilihat.'
                : 'Story berhasil dilihat dan dicatat.',
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
        $owner = User::findOrFail($userId);
        $canView = ! $owner->is_private || $owner->user_id === $authUser->user_id || $owner->followers()
            ->where('users.user_id', $authUser->user_id)
            ->wherePivot('status', 'accepted')
            ->exists();
        abort_unless($canView, 403, 'Ikuti akun privat ini untuk melihat cerita.');

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
            'stories' => $stories,
        ]);
    }

    /**
     * Ambil story expired (arsip)
     */
    public function myArchivedStories(Request $request)
    {
        $authUser = Auth::user();

        $page = max(1, (int) $request->input('page', 1));
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
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'next_page_url' => $paginator->nextPageUrl(),
            'prev_page_url' => $paginator->previousPageUrl(),
            'last_page_url' => $paginator->url($paginator->lastPage()),
            'stories' => $stories->values(),
        ]);
    }
}
