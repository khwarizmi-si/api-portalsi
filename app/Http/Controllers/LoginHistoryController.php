<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LoginHistory;
use Carbon\Carbon;
use Laravel\Sanctum\PersonalAccessToken;

class LoginHistoryController extends Controller
{
    // List (paginated)
    public function index(Request $request)
    {
        $perPage = (int) $request->query('perPage', 15);
        $histories = LoginHistory::where('user_id', $request->user()->id)
            ->orderByDesc('login_at')
            ->paginate($perPage);

        return response()->json($histories);
    }

    // Hapus history tertentu — hanya boleh jika sudah >= 7 hari
    public function destroy(Request $request, $id)
    {
        $history = LoginHistory::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->firstOrFail();

        // Jika entry lebih baru dari 7 hari => tidak boleh dihapus
        if ($history->login_at->gt(now()->subDays(7))) {
            return response()->json([
                'message' => 'Riwayat login yang kurang dari 7 hari tidak dapat dihapus.'
            ], 403);
        }

        // Jika punya token_id, hapus personal access token juga (revoke session)
        if ($history->token_id) {
            PersonalAccessToken::find($history->token_id)?->delete();
        }

        $history->delete();

        return response()->json(['message' => 'Riwayat login berhasil dihapus.']);
    }
}
