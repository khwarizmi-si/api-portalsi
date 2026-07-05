<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Story;
use DB;

class UserSuggestionController extends Controller
{
    public function index()
    {
        $authUser = Auth::user();
        $followingIds = $authUser->following()->pluck('followed_id');
        $suggestions = collect();

        if ($followingIds->isNotEmpty()) {
            // mutual follow (teman dari teman)
            $mutuals = DB::table('follows')
                ->select('followed_id', DB::raw('COUNT(*) as mutual_count'))
                ->whereIn('follower_id', $followingIds)
                ->whereNotIn('followed_id', $followingIds)
                ->where('followed_id', '!=', $authUser->user_id)
                ->groupBy('followed_id')
                ->orderByDesc('mutual_count')
                ->take(10)
                ->get();

            $userIds = $mutuals->pluck('followed_id');

            // cek dulu apakah ada userIds, kalau kosong jangan pakai FIELD()
            $users = $userIds->isNotEmpty()
                ? User::select('user_id', 'username', 'full_name', 'profile_picture_url', 'is_verified', 'is_private')
                    ->whereIn('user_id', $userIds)
                    ->orderByRaw("FIELD(user_id, " . implode(',', $userIds->toArray()) . ")")
                    ->get()
                : collect();

            $suggestions = $suggestions->merge($users);
        }

        // kalau masih < 10 → isi random
        if ($suggestions->count() < 10) {
            $need = 10 - $suggestions->count();

            $randomUsers = User::select('user_id', 'username', 'full_name', 'profile_picture_url', 'is_verified', 'is_private')
                ->where('user_id', '!=', $authUser->user_id)
                ->whereNotIn('user_id', $followingIds)
                ->whereNotIn('user_id', $suggestions->pluck('user_id'))
                ->inRandomOrder()
                ->take($need)
                ->get();

            $suggestions = $suggestions->merge($randomUsers);
        }

        // Tandai user yang punya story aktif (untuk ring story di kartu "temukan teman").
        // Hanya akun publik: akun privat butuh follow accepted, jadi tak diberi ring di sini.
        $suggestionIds = $suggestions->pluck('user_id')->all();
        $storyUserIds = ! empty($suggestionIds)
            ? Story::whereIn('user_id', $suggestionIds)
                ->where('expires_at', '>', now())
                ->distinct()
                ->pluck('user_id')
                ->flip()
            : collect();

        $suggestions = $suggestions->map(function ($user) use ($storyUserIds) {
            $user->is_verified = (bool) $user->is_verified;
            $user->is_private = (bool) $user->is_private;
            $user->has_story = $storyUserIds->has($user->user_id) && ! $user->is_private;
            return $user;
        });

        return response()->json([
            'count' => $suggestions->count(),
            'users' => $suggestions->values()
        ]);
    }
}