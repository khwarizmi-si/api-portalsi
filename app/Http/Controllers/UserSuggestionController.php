<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
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
            $users = User::whereIn('user_id', $userIds)
                ->orderByRaw("FIELD(user_id, " . implode(',', $userIds->toArray()) . ")")
                ->get();

            $suggestions = $suggestions->merge($users);
        }

        // kalau masih < 10 → isi random
        if ($suggestions->count() < 10) {
            $need = 10 - $suggestions->count();

            $randomUsers = User::where('user_id', '!=', $authUser->user_id)
                ->whereNotIn('user_id', $followingIds)
                ->whereNotIn('user_id', $suggestions->pluck('user_id'))
                ->inRandomOrder()
                ->take($need)
                ->get();

            $suggestions = $suggestions->merge($randomUsers);
        }

        return response()->json([
            'count' => $suggestions->count(),
            'users' => $suggestions->values()
        ]);
    }
}
