<?php

namespace App\Http\Controllers;

use App\Models\Portfolio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PortfolioController extends Controller
{
    // 🔹 Tampilkan semua portfolio (opsional filter by aspek / user)
    public function index(Request $request)
    {
        $query = Portfolio::with('user');
    
        // 🔹 Filter
        if ($request->has('aspect')) {
            $query->where('aspect', $request->aspect);
        }
    
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }
    
        if ($request->has('title')) {
            $query->where('title', 'like', '%' . $request->title . '%');
        }
    
        // 🔹 Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
    
        if ($sortBy === 'user_name') {
            $query->join('users', 'users.user_id', '=', 'portfolios.user_id')
                  ->orderBy('users.name', $sortDir)
                  ->select('portfolios.*');
        } else {
            $query->orderBy($sortBy, $sortDir);
        }
    
        $portfolios = $query->get();
    
        // 🔹 Format respon
        $result = $portfolios->map(function ($item) {
            return [
                'id' => $item->id,
                'user_id' => $item->user_id,
                'user_name' => $item->user->username ?? null,
                'aspect' => $item->aspect,
                'title' => $item->title,
                'description' => $item->description,
                'media_url' => $item->media_url,
                'year' => $item->year,
                'created_at' => $item->created_at,
            ];
        });
    
        return response()->json([
            'portfolios' => $result
        ]);
    }
    

    // 🔹 Tambah portfolio (hanya admin)
    public function store(Request $request)
    {
        $this->authorizeAdmin();

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,user_id',
            'aspect' => 'required|in:quran,it,bahasa,karakter',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'media' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'year' => 'nullable|integer|min:2000|max:' . date('Y'),
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $mediaUrl = null;
        if ($request->hasFile('media')) {
            $path = $request->file('media')->store('portfolio-media', 'public');
            $mediaUrl = asset(Storage::url($path));
        }

        $portfolio = Portfolio::create([
            'user_id' => $request->user_id,
            'aspect' => $request->aspect,
            'title' => $request->title,
            'description' => $request->description,
            'media_url' => $mediaUrl,
            'year' => $request->year,
        ]);

        return response()->json([
            'message' => 'Portfolio berhasil ditambahkan.',
            'portfolio' => $portfolio
        ]);
    }

    // 🔹 Update portfolio (pakai POST agar support form-data)
    public function update(Request $request, Portfolio $portfolio)
    {
        $this->authorizeAdmin();

        $validator = Validator::make($request->all(), [
            'aspect' => 'nullable|in:quran,it,bahasa,karakter',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'media' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'year' => 'nullable|integer|min:2000|max:' . date('Y'),
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // Ganti media jika ada file baru
        if ($request->hasFile('media')) {
            if ($portfolio->media_url) {
                $oldPath = str_replace('/storage/', '', parse_url($portfolio->media_url, PHP_URL_PATH));
                Storage::disk('public')->delete($oldPath);
            }

            $path = $request->file('media')->store('portfolio-media', 'public');
            $portfolio->media_url = asset(Storage::url($path));
        }

        $portfolio->fill($request->only(['aspect', 'title', 'description', 'year']))->save();

        return response()->json([
            'message' => 'Portfolio berhasil diperbarui.',
            'portfolio' => $portfolio
        ]);
    }

    // 🔹 Hapus portfolio
    public function destroy(Portfolio $portfolio)
    {
        $this->authorizeAdmin();

        if ($portfolio->media_url) {
            $path = str_replace('/storage/', '', parse_url($portfolio->media_url, PHP_URL_PATH));
            Storage::disk('public')->delete($path);
        }

        $portfolio->delete();

        return response()->json([
            'message' => 'Portfolio berhasil dihapus.'
        ]);
    }

    // 🔐 Validasi admin (gunakan is_verified sebagai indikator)
    protected function authorizeAdmin()
    {
        if (!Auth::check() || !Auth::user()->is_verified) {
            abort(403, 'Hanya admin yang diizinkan.');
        }
    }
}
