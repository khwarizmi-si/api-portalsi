<?php

namespace App\Http\Controllers;

use App\Models\LoginHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Jenssegers\Agent\Agent;
use Laravel\Sanctum\PersonalAccessToken;

class LoginHistoryController extends Controller
{
    // List tanpa pagination (menampilkan semua field sesuai struktur tabel)
    public function index(Request $request)
    {
        $columns = collect([
            'id',
            'user_id',
            'token_id',
            'ip_address',
            'user_agent',
            'device',
            'browser',
            'platform',
            'location',
            'login_at',
            'created_at',
            'updated_at',
        ])->filter(fn ($column) => Schema::hasColumn('login_histories', $column))->values()->all();

        $histories = LoginHistory::select($columns)
            ->where('user_id', $request->user()->user_id)
            ->orderByDesc(Schema::hasColumn('login_histories', 'login_at') ? 'login_at' : 'created_at')
            ->get();

        // "Sedang aktif" = token-nya baru digunakan (last_used_at) dalam 10 menit terakhir.
        // Token yang hanya ada tapi lama tak dipakai (device sudah ditutup) TIDAK dihitung aktif.
        $activeThreshold = now()->subMinutes(10);
        $tokenIds = Schema::hasColumn('login_histories', 'token_id')
            ? $histories->pluck('token_id')->filter()->all()
            : [];
        $lastUsedByToken = empty($tokenIds)
            ? collect()
            : PersonalAccessToken::whereIn('id', $tokenIds)->pluck('last_used_at', 'id');
        $currentTokenId = (int) optional($request->user()->currentAccessToken())->id;

        $histories = $histories->map(function (LoginHistory $history) use ($lastUsedByToken, $currentTokenId, $activeThreshold) {
            if (in_array((string) $history->device, ['', '0', 'unknown'], true)
                || in_array((string) $history->browser, ['', '0', 'unknown'], true)
                || in_array((string) $history->platform, ['', '0', 'unknown'], true)) {
                $agent = new Agent;
                $agent->setUserAgent($history->user_agent ?: '');
                $history->device = $agent->device() ?: ($agent->isDesktop() ? 'Komputer' : 'Perangkat tidak dikenal');
                $history->browser = $agent->browser() ?: 'Browser tidak dikenal';
                $history->platform = $agent->platform() ?: 'Sistem tidak dikenal';
            }

            $history->is_current = (int) $history->token_id === $currentTokenId;
            $lastUsed = $history->token_id !== null ? ($lastUsedByToken[$history->token_id] ?? null) : null;
            // Sesi saat ini selalu aktif; lainnya aktif bila last_used_at masih baru.
            $history->is_active = $history->is_current
                || ($lastUsed !== null && \Illuminate\Support\Carbon::parse($lastUsed)->greaterThanOrEqualTo($activeThreshold));

            return $history;
        });

        return response()->json($histories);
    }

    // Hapus history tertentu — hanya boleh jika sudah >= 7 hari
    public function destroy(Request $request, $id)
    {
        $history = LoginHistory::where('user_id', $request->user()->user_id)
            ->where('id', $id)
            ->firstOrFail();

        // Jika punya token_id, hapus personal access token juga (revoke session)
        if ($history->token_id) {
            PersonalAccessToken::find($history->token_id)?->delete();
        }

        $history->delete();

        return response()->json(['message' => 'Riwayat login berhasil dihapus.']);
    }

    public function destroyAll(Request $request)
    {
        $userId = $request->user()->user_id;
        $tokenIds = LoginHistory::where('user_id', $userId)->whereNotNull('token_id')->pluck('token_id');

        PersonalAccessToken::whereIn('id', $tokenIds)->delete();
        $request->user()->tokens()->delete();
        LoginHistory::where('user_id', $userId)->delete();

        return response()->json(['message' => 'Semua sesi berhasil dicabut.']);
    }
}
