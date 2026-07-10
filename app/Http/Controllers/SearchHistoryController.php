<?php

namespace App\Http\Controllers;

use App\Models\SearchHistory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SearchHistoryController extends Controller
{
    public function index(Request $request)
    {
        $limit = max(1, min(30, (int) $request->input('limit', 10)));

        $histories = SearchHistory::query()
            ->with(['targetUser' => fn ($query) => $query->select(
                'user_id',
                'username',
                'full_name',
                'profile_picture_url',
                'role',
                'is_verified',
                'is_private'
            )])
            ->where('user_id', $request->user()->user_id)
            ->latest('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (SearchHistory $history) => $this->serialize($history));

        return response()->json(['data' => $histories]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'query' => ['nullable', 'string', 'max:120'],
            'type' => ['nullable', 'in:keyword,user'],
            'target_user_id' => ['nullable', 'integer', 'exists:users,user_id'],
        ]);

        $targetUser = null;
        if (! empty($data['target_user_id'])) {
            $targetUser = User::query()
                ->select('user_id', 'username', 'full_name', 'profile_picture_url', 'role', 'is_verified', 'is_private')
                ->find($data['target_user_id']);
        }

        $type = $targetUser ? 'user' : ($data['type'] ?? 'keyword');
        $query = trim((string) ($data['query'] ?? ''));
        if ($targetUser && $query === '') {
            $query = $targetUser->full_name ?: $targetUser->username;
        }
        if (Str::length($query) < 2) {
            return response()->json(['message' => 'Query pencarian minimal 2 karakter.'], 422);
        }

        $queryKey = Str::lower($query);
        $lookup = SearchHistory::withTrashed()
            ->where('user_id', $request->user()->user_id)
            ->when($targetUser, fn ($q) => $q->where('target_user_id', $targetUser->user_id))
            ->when(! $targetUser, fn ($q) => $q->where('type', 'keyword')->where('query_key', $queryKey))
            ->first();

        $history = $lookup ?: new SearchHistory(['user_id' => $request->user()->user_id]);
        $history->fill([
            'target_user_id' => $targetUser?->user_id,
            'type' => $type,
            'query' => $query,
            'query_key' => $queryKey,
        ]);
        if ($history->trashed()) {
            $history->restore();
        }
        $history->updated_at = now();
        $history->save();
        $history->setRelation('targetUser', $targetUser);

        return response()->json(['history' => $this->serialize($history)], $lookup ? 200 : 201);
    }

    public function destroy(Request $request, int $id)
    {
        $history = SearchHistory::where('user_id', $request->user()->user_id)->findOrFail($id);
        $history->delete();

        return response()->json(['message' => 'Riwayat pencarian disembunyikan.']);
    }

    public function destroyAll(Request $request)
    {
        SearchHistory::where('user_id', $request->user()->user_id)->delete();

        return response()->json(['message' => 'Semua riwayat pencarian disembunyikan.']);
    }

    private function serialize(SearchHistory $history): array
    {
        return [
            'id' => $history->id,
            'type' => $history->type,
            'query' => $history->query,
            'target_user_id' => $history->target_user_id,
            'target_user' => $history->targetUser,
            'created_at' => optional($history->created_at)->toISOString(),
            'updated_at' => optional($history->updated_at)->toISOString(),
        ];
    }
}
