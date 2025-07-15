<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Tag;
use App\Models\PostMention;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PostController extends Controller
{
    public function index()
    {
        $posts = Post::with(['user', 'tags', 'mentions'])->latest()->get();
        return response()->json($posts);
    }

    public function show($id)
    {
        $post = Post::with(['user', 'tags', 'mentions'])->findOrFail($id);
        return response()->json($post);
    }

    public function store(Request $request)
    {
        $request->validate([
            'caption'     => 'nullable|string',
            'media_url'   => 'required|string',
            'location'    => 'nullable|string',
            'is_archived' => 'boolean',
            'is_video'    => 'boolean',
        ]);

        $post = Post::create([
            'user_id'     => Auth::id(),
            'caption'     => $request->caption,
            'media_url'   => $request->media_url,
            'location'    => $request->location,
            'is_archived' => $request->is_archived ?? false,
            'is_video'    => $request->is_video ?? false,
        ]);

        // ✅ Tangani tag dari caption (contoh: #hebat)
        if ($request->filled('caption')) {
            preg_match_all('/#(\w+)/', $request->caption, $tags);
            $tagNames = $tags[1];

            foreach ($tagNames as $tagName) {
                $tag = Tag::firstOrCreate(['tag_name' => $tagName]);
                $post->tags()->attach($tag->tag_id);
            }
        }

        // ✅ Tangani mention dari caption (contoh: @azzam)
        if ($request->filled('caption')) {
            preg_match_all('/@(\w+)/', $request->caption, $mentions);
            $usernames = $mentions[1];

            foreach ($usernames as $username) {
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

    public function update(Request $request, $id)
    {
        $post = Post::findOrFail($id);

        if ($post->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'caption' => 'nullable|string',
            'media_url' => 'nullable|string',
            'location' => 'nullable|string',
            'is_archived' => 'boolean',
            'is_video' => 'boolean',
        ]);

        $post->update($request->only('caption', 'media_url', 'location', 'is_archived', 'is_video'));

        return response()->json([
            'message' => 'Post updated',
            'post' => $post
        ]);
    }

    public function destroy($id)
    {
        $post = Post::findOrFail($id);

        if ($post->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $post->delete();

        return response()->json(['message' => 'Post deleted']);
    }

    // 📢 EXPLORE
    public function explore(Request $request)
    {
        $query = Post::with(['user', 'tags'])
            ->where('is_archived', false);

        // 🔎 Filter berdasarkan hashtag
        if ($request->filled('tag')) {
            $tagName = $request->tag;
            $query->whereHas('tags', function ($q) use ($tagName) {
                $q->where('tag_name', $tagName);
            });
        }

        // 🔀 Sorting: popular / random / newest
        $sort = $request->input('sort', 'random');

        if ($sort === 'popular') {
            $query->withCount('likes')->orderByDesc('likes_count');
        } elseif ($sort === 'newest') {
            $query->orderByDesc('created_at');
        } else {
            $query->inRandomOrder();
        }

        $posts = $query->take(20)->get();

        return response()->json($posts);
    }
}
