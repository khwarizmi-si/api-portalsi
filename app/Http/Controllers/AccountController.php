<?php

namespace App\Http\Controllers;

use App\Events\NotificationCreated;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
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

    // 🔧 UPDATE AKUN
    public function update(Request $request)
    {
        $user = Auth::user();
        $previousBio = (string) $user->bio;

        $request->validate([
            'username' => [
                'nullable',
                'string',
                'regex:/^[a-zA-Z0-9._]+$/',
                'unique:users,username,'.$user->user_id.',user_id',
            ],
            'full_name' => 'nullable|string',
            'email' => 'nullable|email|unique:users,email,'.$user->user_id.',user_id',
            'bio' => 'nullable|string',
            'is_private' => 'nullable|boolean',
            'profile_picture' => 'nullable|image|max:10240', // max 10MB
            'banner' => 'nullable|file|mimes:jpg,jpeg,png,webp,gif|max:20480', // animated GIF is preserved
        ], [
            'username.regex' => 'Username hanya boleh berisi huruf, angka, titik, dan underscore tanpa spasi atau simbol lain.',
        ]);

        // ✅ Upload profile picture
        if ($request->hasFile('profile_picture')) {
            $disk = $this->mediaDisk();
            if ($user->profile_picture_url) {
                $oldPath = $this->storagePathFromUrl($user->profile_picture_url);
                Storage::disk($disk)->delete($oldPath);
            }

            $path = $request->file('profile_picture')->store('profile_pictures', $disk);
            $user->profile_picture_url = Storage::disk($disk)->url($path);
        }

        // ✅ Upload banner
        if ($request->hasFile('banner')) {
            $disk = $this->mediaDisk();
            if ($user->banner_url) {
                $oldPath = $this->storagePathFromUrl($user->banner_url);
                Storage::disk($disk)->delete($oldPath);
            }

            $path = $request->file('banner')->store('banners', $disk);
            $user->banner_url = Storage::disk($disk)->url($path);
        }

        // ✅ Update field lain
        $user->fill($request->only([
            'username',
            'full_name',
            'email',
            'bio',
            'is_private',
        ]));

        $user->save();

        if ($request->has('bio') && $previousBio !== (string) $user->bio) {
            preg_match_all('/@([A-Za-z0-9._]+)/', (string) $user->bio, $newMatches);
            preg_match_all('/@([A-Za-z0-9._]+)/', $previousBio, $oldMatches);
            $newUsernames = array_diff(array_unique($newMatches[1] ?? []), array_unique($oldMatches[1] ?? []));
            foreach (User::whereIn('username', $newUsernames)->where('user_id', '!=', $user->user_id)->get() as $mentioned) {
                $notification = Notification::create([
                    'recipient_id' => $mentioned->user_id,
                    'type' => 'bio_mention',
                    'related_user_id' => $user->user_id,
                    'created_at' => now(),
                    'is_read' => false,
                ]);
                broadcast(new NotificationCreated($notification));
            }
        }

        return response()->json([
            'message' => 'Akun berhasil diperbarui',
            'user' => $user,
        ]);
    }

    // 🔒 Ganti password
    public function updatePassword(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:6',
        ]);

        if (! Hash::check($request->current_password, $user->password_hash)) {
            throw ValidationException::withMessages([
                'current_password' => ['Password saat ini salah'],
            ]);
        }

        $user->password_hash = bcrypt($request->new_password);
        $user->save();

        return response()->json(['message' => 'Password berhasil diganti']);
    }

    // ❌ Hapus akun
    public function destroy(Request $request)
    {
        $user = Auth::user();

        // Hapus profile picture jika ada
        if ($user->profile_picture_url) {
            $oldPath = $this->storagePathFromUrl($user->profile_picture_url);
            Storage::disk($this->mediaDisk())->delete($oldPath);
        }

        // Hapus banner jika ada
        if ($user->banner_url) {
            $oldPath = $this->storagePathFromUrl($user->banner_url);
            Storage::disk($this->mediaDisk())->delete($oldPath);
        }

        $user->delete();

        return response()->json(['message' => 'Akun telah dihapus']);
    }

    // 🔍 Cek status private user login
    public function checkPrivateStatus()
    {
        $user = auth()->user();

        if (! $user) {
            return response()->json([
                'message' => 'User belum login',
            ], 401); // 401 Unauthorized
        }

        return response()->json($user->is_private == 1 ? 1 : 0);
    }
}
