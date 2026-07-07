<?php

namespace App\Http\Controllers;

use App\Models\LoginHistory;
use Illuminate\Http\Request;
use Jenssegers\Agent\Agent;
use Laravel\Sanctum\PersonalAccessToken;

class LoginHistoryController extends Controller
{
    // List tanpa pagination (menampilkan semua field sesuai struktur tabel)
    public function index(Request $request)
    {
        $histories = LoginHistory::select(
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
            'updated_at'
        )
            ->where('user_id', $request->user()->user_id)
            ->orderByDesc('login_at')
            ->get()
            ->map(function (LoginHistory $history) use ($request) {
                if (in_array((string) $history->device, ['', '0', 'unknown'], true)
                    || in_array((string) $history->browser, ['', '0', 'unknown'], true)
                    || in_array((string) $history->platform, ['', '0', 'unknown'], true)) {
                    $agent = new Agent;
                    $agent->setUserAgent($history->user_agent ?: '');
                    $history->device = $agent->device() ?: ($agent->isDesktop() ? 'Komputer' : 'Perangkat tidak dikenal');
                    $history->browser = $agent->browser() ?: 'Browser tidak dikenal';
                    $history->platform = $agent->platform() ?: 'Sistem tidak dikenal';
                }

                $history->is_current = (int) $history->token_id === (int) optional($request->user()->currentAccessToken())->id;

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
