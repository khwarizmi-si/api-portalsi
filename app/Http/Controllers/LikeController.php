<?php

namespace App\Http\Controllers;

use App\Models\Like;
use App\Models\Post;
use App\Models\Notification;
use App\Events\LikeCreated;
use App\Events\NotificationCreated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\Follow; // pastikan ada model Follow

class LikeController extends Controller
{
    public function toggle(Request $request, $post_id)
    {
        $user_id = Auth::id();

        if (!$user_id) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'User ID not found. Are you logged in and sending token correctly?'
            ], 401);
        }

        $post = Post::find($post_id);
        if (!$post) {
            return response()->json(['error' => 'Post not found'], 404);
        }

        $like = Like::where('user_id', $user_id)
            ->where('post_id', $post_id)
            ->first();

        if ($like) {
            $like->delete();
            return response()->json(['message' => 'Post unliked']);
        } else {
            $like = Like::create([
                'user_id' => $user_id,
                'post_id' => $post_id,
            ]);

            // Broadcast like event.
            // This is for real-time updates on the post's like count.
            broadcast(new LikeCreated($like))->toOthers();

            // Kirim notifikasi HANYA jika user bukan pemilik post
            if ($post->user_id != $user_id) {
                // Cek notifikasi terakhir untuk like
                $lastNotif = Notification::where('recipient_id', $post->user_id)
                    ->where('related_user_id', $user_id)
                    ->where('related_post_id', $post_id)
                    ->where('type', 'like')
                    ->latest()
                    ->first();

                $allowNotify = true;

                if ($lastNotif) {
                    $lastCreated = Carbon::parse($lastNotif->created_at);
                    $diff = now()->diffInSeconds($lastCreated);
                    if ($diff < 60) {
                        $allowNotify = false;
                    }
                }

                if ($allowNotify) {
                    $notification = Notification::create([
                        'recipient_id' => $post->user_id,
                        'type' => 'like',
                        'related_user_id' => $user_id,
                        'related_post_id' => $post_id,
                        'created_at' => now(),
                        'is_read' => false,
                    ]);

                    // Broadcast notification event
                    // This is for real-time updates to the notification bell
                    broadcast(new NotificationCreated($notification));
                }
            }

            return response()->json(['message' => 'Post liked']);
        }
    }

public function index($post_id)
{
    $authUser = Auth::user();

    $followingIds = [];
    if ($authUser) {
        $followingIds = \App\Models\Follow::where('follower_id', $authUser->id)
            ->where('status', 'accepted')
            ->pluck('followed_id')
            ->toArray();
    }

    $likes = Like::where('post_id', $post_id)
        ->with('user')
        ->get()
        ->map(function ($like) use ($followingIds) {
            return [
                'like_id' => $like->like_id,
                'post_id' => $like->post_id,
                'user' => $like->user,
                'created_at' => $like->created_at,
                'is_following_status' => in_array($like->user_id, $followingIds),
            ];
        });

    return response()->json($likes);
}

}
