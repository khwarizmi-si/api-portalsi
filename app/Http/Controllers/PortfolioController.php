<?php

namespace App\Http\Controllers;

use App\Models\Portfolio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PortfolioController extends Controller
{
    private function mediaDisk(): string
    {
        return config('filesystems.default', 'public');
    }

    private function storagePathFromUrl(string $url): string
    {
        $path = ltrim(parse_url($url, PHP_URL_PATH) ?? $url, '/');
        return preg_replace('#^storage/#', '', $path);
    }
    // 🔹 Tampilkan semua portfolio dengan filter & search
    public function index(Request $request)
    {
        $query = Portfolio::with('user');

        // 🔹 Filter aspect → random jika filter digunakan
        if ($request->has('aspect')) {
            $query->where('aspect', $request->aspect)
                  ->inRandomOrder();
        }

        // 🔹 Filter user_id
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // 🔹 Filter tahun
        if ($request->has('year')) {
            $query->where('year', $request->year);
        }

        // 🔹 Search by title or description
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%$search%")
                  ->orWhere('description', 'like', "%$search%");
            });
        }

        // 🔹 Sorting (default created_at desc)
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');

        if ($sortBy === 'user_name') {
            $query->join('users', 'users.user_id', '=', 'portfolios.user_id')
                  ->orderBy('users.name', $sortDir)
                  ->select('portfolios.*');
        } else {
            if (!$request->has('aspect')) {
                $query->orderBy($sortBy, $sortDir);
            }
        }

        $portfolios = $query->get();

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

    // 🔹 Tambah portfolio
    public function store(Request $request)
    {
        $this->authorizePortfolioAccess();

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,user_id',
            'aspect' => 'required|in:quran,it,bahasa,karakter',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'media' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:51200',
            'year' => 'nullable|integer|min:2000|max:' . date('Y'),
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $mediaUrl = null;
        if ($request->hasFile('media')) {
            $disk = $this->mediaDisk();
            $path = $request->file('media')->store('portfolio-media', $disk);
            $mediaUrl = Storage::disk($disk)->url($path);
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

    // 🔹 Update portfolio
    public function update(Request $request, Portfolio $portfolio)
    {
        $this->authorizePortfolioAccess();

        $validator = Validator::make($request->all(), [
            'aspect' => 'nullable|in:quran,it,bahasa,karakter',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'media' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:51200',
            'year' => 'nullable|integer|min:2000|max:' . date('Y'),
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        if ($request->hasFile('media')) {
            if ($portfolio->media_url) {
                Storage::disk($this->mediaDisk())->delete(
                    $this->storagePathFromUrl($portfolio->media_url)
                );
            }

            $disk = $this->mediaDisk();
            $path = $request->file('media')->store('portfolio-media', $disk);
            $portfolio->media_url = Storage::disk($disk)->url($path);
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
        $this->authorizePortfolioAccess();

        if ($portfolio->media_url) {
            Storage::disk($this->mediaDisk())->delete(
                $this->storagePathFromUrl($portfolio->media_url)
            );
        }

        $portfolio->delete();

        return response()->json([
            'message' => 'Portfolio berhasil dihapus.'
        ]);
    }

    // 🔐 Validasi role teacher/dev atau user is_verified
    protected function authorizePortfolioAccess()
    {
        if (!Auth::check() || 
            !(in_array(Auth::user()->role, ['teacher', 'dev']) || Auth::user()->is_verified == 1)
        ) {
            abort(403, 'Hanya teacher, dev, atau user terverifikasi yang diizinkan.');
        }
    }
}
