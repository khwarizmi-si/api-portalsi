<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Notification;
use Carbon\Carbon;
use App\Events\Followed; 
use App\Events\NotificationCreated;
use App\Events\UserUnfollowed;
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

        // 🟡 Tentukan status follow berdasarkan privasi user
        $status = $userToFollow->is_private ? 'pending' : 'accepted';

        // ➕ Lakukan follow dengan status
        $authUser->following()->attach($userToFollow->user_id, [
            'followed_at' => now(),
            'status' => $status
        ]);

        // 🔔 Kirim notifikasi hanya jika statusnya accepted
        if ($status === 'accepted') {


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
                broadcast(new Followed($authUser, $userToFollow))->toOthers();
            }
            
        }

        return response()->json([
            'message' => $status === 'accepted'
                ? 'Berhasil follow user.'
                : 'Permintaan follow dikirim. Menunggu konfirmasi.',
            'status' => $status
        ], 201);
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
        broadcast(new UserUnfollowed($authUser, $userToUnfollow));

        return response()->json(['message' => 'Berhasil unfollow user.'], 200);
    }

    // ✅ LIHAT FOLLOWERS
    public function followers($id)
    {
        $user = User::findOrFail($id);
        $followers = $user->followers()
            ->wherePivot('status', 'accepted')
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
            ->wherePivot('status', 'accepted')
            ->select('users.user_id', 'username', 'full_name')
            ->get();

        return response()->json([
            'following_count' => $following->count(),
            'following' => $following
        ]);
    }

    // ✅ TERIMA PERMINTAAN FOLLOW
public function acceptFollowRequest($followerId)
{
    $authUser = Auth::user();

    // Hanya akun private yang boleh melakukan aksi ini
    if (!$authUser->is_private) {
        return response()->json(['message' => 'Akun Anda bukan private.'], 403);
    }

    // Cek apakah ada request follow pending
    $exists = $authUser->followers()
        ->wherePivot('follower_id', $followerId)
        ->wherePivot('status', 'pending')
        ->exists();

    if (!$exists) {
        return response()->json(['message' => 'Tidak ada permintaan follow yang pending dari user ini.'], 404);
    }

    // Update status ke accepted
    $authUser->followers()->updateExistingPivot($followerId, ['status' => 'accepted']);

    // Kirim notifikasi ke follower bahwa follow sudah diterima
    Notification::create([
        'recipient_id'     => $followerId,
        'type'             => 'follow_accepted',
        'related_user_id'  => $authUser->user_id,
        'related_post_id'  => null,
        'created_at'       => now(),
        'is_read'          => false,
    ]);
        $notification = Notification::create([
        'recipient_id'      => $followerId,
        'type'              => 'follow_accepted',
        'related_user_id'   => $authUser->user_id,
        'related_post_id'   => null,
        'created_at'        => now(),
        'is_read'           => false,
    ]);
    broadcast(new NotificationCreated($notification)); // Menggunakan event notifikasi umum
    
    // ✨ Pemicu event real-time untuk memberitahu user yang menerima
    $follower = User::find($followerId);
    broadcast(new Followed($follower, $authUser)); // Menggunakan event follow khusus
    

    return response()->json(['message' => 'Permintaan follow diterima.'], 200);
}

// ❌ TOLAK PERMINTAAN FOLLOW
public function rejectFollowRequest($followerId)
{
    $authUser = Auth::user();

    if (!$authUser->is_private) {
        return response()->json(['message' => 'Akun Anda bukan private.'], 403);
    }

    // Cek apakah ada request follow pending
    $exists = $authUser->followers()
        ->wherePivot('follower_id', $followerId)
        ->wherePivot('status', 'pending')
        ->exists();

    if (!$exists) {
        return response()->json(['message' => 'Tidak ada permintaan follow yang pending dari user ini.'], 404);
    }

    // Hapus dari tabel follows
    $authUser->followers()->detach($followerId);

    return response()->json(['message' => 'Permintaan follow ditolak.'], 200);
}

public function pendingFollowRequests()
{
    $authUser = Auth::user();

    if (!$authUser->is_private) {
        return response()->json(['message' => 'Akun Anda bukan private.'], 403);
    }

    $pending = $authUser->followers()
        ->wherePivot('status', 'pending')
        ->select('users.user_id', 'users.username', 'users.full_name')
        ->get();

    return response()->json([
        'pending_requests_count' => $pending->count(),
        'pending_requests' => $pending
    ]);
}


}
