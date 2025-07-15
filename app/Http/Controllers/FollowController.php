<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Notification;
use Carbon\Carbon;

class FollowController extends Controller
{
    // ✅ FOLLOW USER
    public function follow($id)
    {
        $userToFollow = User::findOrFail($id);
        $authUser = Auth::user();

        // 🚫 Cegah follow diri sendiri
        if ($authUser->user_id == $userToFollow->user_id) {
            return response()->json(['message' => 'Tidak bisa follow diri sendiri.'], 403);
        }

        // 🔍 Cek apakah sudah follow
        if ($authUser->following()->where('followed_id', $id)->exists()) {
            return response()->json(['message' => 'Sudah di-follow.'], 409);
        }

        // ➕ Lakukan follow
        $authUser->following()->attach($userToFollow->user_id, [
            'followed_at' => now(),
            'status' => 'pending'
        ]);

        // 🔔 Cek delay notifikasi (maks 1x per 60 detik)
        $lastNotif = Notification::where('recipient_id', $userToFollow->user_id)
            ->where('related_user_id', $authUser->user_id)
            ->where('type', 'follow')
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
            Notification::create([
                'recipient_id'     => $userToFollow->user_id,
                'type'             => 'follow',
                'related_user_id'  => $authUser->user_id,
                'related_post_id'  => null,
                'created_at'       => now(),
                'is_read'          => false,
            ]);
        }

        return response()->json(['message' => 'Berhasil follow user.'], 201);
    }

    // ✅ UNFOLLOW USER
    public function unfollow($id)
    {
        $userToUnfollow = User::findOrFail($id);
        $authUser = Auth::user();

        if (!$authUser->following()->where('followed_id', $id)->exists()) {
            return response()->json(['message' => 'Belum di-follow.'], 404);
        }

        $authUser->following()->detach($userToUnfollow->user_id);

        return response()->json(['message' => 'Berhasil unfollow user.'], 200);
    }

    // ✅ LIHAT FOLLOWERS
    public function followers($id)
    {
        $user = User::findOrFail($id);
        $followers = $user->followers()
            ->select('users.user_id', 'username', 'full_name')
            ->get();

        return response()->json([
            'followers_count' => $followers->count(),
            'followers' => $followers
        ]);
    }

    // ✅ LIHAT FOLLOWING
    public function following($id)
    {
        $user = User::findOrFail($id);
        $following = $user->following()
            ->select('users.user_id', 'username', 'full_name')
            ->get();

        return response()->json([
            'following_count' => $following->count(),
            'following' => $following
        ]);
    }
}
