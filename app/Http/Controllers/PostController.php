<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Tag;
use App\Models\PostMention;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class PostController extends Controller
{
    // 🔍 List semua post
    public function index()
    {
        $authUser = Auth::user();
    
        $posts = Post::with(['user', 'tags', 'mentions'])
            ->withCount(['likes', 'comments'])
            ->where(function ($query) use ($authUser) {
                $query->whereHas('user.followers', function ($q) use ($authUser) {
                    $q->where('follower_id', $authUser->user_id)
                      ->where('status', 'accepted');
                })
                ->orWhereHas('user', function ($q) use ($authUser) {
                    $q->where('user_id', $authUser->user_id);
                });
            })
            ->latest()
            ->get()
            ->map(function ($post) use ($authUser) {
                $post->is_liked = $post->likes()->where('user_id', $authUser->user_id)->exists();
                return $post;
            });
    
        return response()->json($posts);
    }
    
    public function show($id)
    {
        $authUser = Auth::user();
        $post = Post::with(['user', 'tags', 'mentions'])
            ->withCount(['likes', 'comments'])
            ->findOrFail($id);
    
        $owner = $post->user;
    
        $canView = !$owner->is_private ||
            ($authUser && (
                $authUser->user_id === $owner->user_id ||
                $owner->followers()
                    ->where('follower_id', $authUser->user_id)
                    ->where('status', 'accepted')
                    ->exists()
            ));
    
        if (!$canView) {
            return response()->json([
                'message' => 'Post ini hanya bisa dilihat oleh followers yang telah diterima.'
            ], 403);
        }
    
        $post->is_liked = $post->likes()->where('user_id', $authUser->user_id)->exists();
    
        return response()->json($post);
    }
    
    
    public function explore(Request $request)
    {
        $query = Post::with(['user', 'tags'])
            ->withCount(['likes', 'comments']) // ✅ Tambahkan count
            ->where('is_archived', false);
    
        if ($request->filled('tag')) {
            $tagName = $request->tag;
            $query->whereHas('tags', function ($q) use ($tagName) {
                $q->where('tag_name', $tagName);
            });
        }
    
        $sort = $request->input('sort', 'random');
    
        if ($sort === 'popular') {
            $query->orderByDesc('likes_count');
        } elseif ($sort === 'newest') {
            $query->orderByDesc('created_at');
        } else {
            $query->inRandomOrder();
        }
    
        $posts = $query->take(20)->get();
    
        return response()->json($posts);
    }
    
    
    

    // 🆕 Buat post baru (media upload pakai file)
    public function store(Request $request)
    {
        $request->validate([
            'caption'     => 'nullable|string',
            'media'       => 'required|file|mimes:jpg,jpeg,png,mp4,mov,webm|max:51200',
            'location'    => 'nullable|string',
            'is_archived' => 'nullable|boolean',
            'is_video'    => 'nullable|boolean',
        ]);

        // 🗂 Simpan file ke storage
        $mediaPath = $request->file('media')->store('uploads/posts', 'public');
        $mediaUrl = asset('storage/' . $mediaPath);

        $post = Post::create([
            'user_id'     => Auth::id(),
            'caption'     => $request->caption,
            'media_url'   => $mediaUrl,
            'location'    => $request->location,
            'is_archived' => $request->is_archived ?? false,
            'is_video'    => $request->is_video ?? false,
        ]);

        // 🎯 Tangani hashtag
        if ($request->filled('caption')) {
            preg_match_all('/#(\w+)/', $request->caption, $tags);
            foreach ($tags[1] as $tagName) {
                $tag = Tag::firstOrCreate(['tag_name' => $tagName]);
                $post->tags()->attach($tag->tag_id);
            }
        }

        // 👥 Tangani mention
        if ($request->filled('caption')) {
            preg_match_all('/@(\w+)/', $request->caption, $mentions);
            foreach ($mentions[1] as $username) {
                $mentionedUser = User::where('username', $username)->first();
                if ($mentionedUser && $mentionedUser->user_id !== Auth::id()) {
                    PostMention::create([
                        'post_id' => $post->post_id,
                        'mentioned_user_id' => $mentionedUser->user_id
                    ]);
                    Notification::create([
                        'recipient_id'     => $mentionedUser->user_id,
                        'type'             => 'mention',
                        'related_user_id'  => Auth::id(),
                        'related_post_id'  => $post->post_id,
                        'created_at'       => now(),
                        'is_read'          => false,
                    ]);
                }
            }
        }

        return response()->json([
            'message' => 'Post created',
            'post' => $post
        ], 201);
    }

    // ✏️ Edit post (media optional)
    public function update(Request $request, $id)
    {
        $post = Post::findOrFail($id);

        if ($post->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'caption'     => 'nullable|string',
            'media'       => 'nullable|file|mimes:jpg,jpeg,png,mp4,mov,webm|max:51200',
            'location'    => 'nullable|string',
            'is_archived' => 'nullable|boolean',
            'is_video'    => 'nullable|boolean',
        ]);

        // 🔄 Ganti file jika ada media baru
        if ($request->hasFile('media')) {
            // Hapus media lama (opsional)
            if ($post->media_url) {
                $path = str_replace(asset('storage') . '/', '', $post->media_url);
                Storage::disk('public')->delete($path);
            }
            $mediaPath = $request->file('media')->store('uploads/posts', 'public');
            $post->media_url = asset('storage/' . $mediaPath);
        }

        // Update field lainnya
        $post->caption = $request->caption ?? $post->caption;
        $post->location = $request->location ?? $post->location;
        $post->is_archived = $request->is_archived ?? $post->is_archived;
        $post->is_video = $request->is_video ?? $post->is_video;
        $post->save();

        return response()->json([
            'message' => 'Post updated',
            'post' => $post
        ]);
    }

    // 🗑 Hapus post
    public function destroy($id)
    {
        $post = Post::findOrFail($id);

        if ($post->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Hapus media
        if ($post->media_url) {
            $path = str_replace(asset('storage') . '/', '', $post->media_url);
            Storage::disk('public')->delete($path);
        }

        $post->delete();

        return response()->json(['message' => 'Post deleted']);
    }


}
