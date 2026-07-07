<?php

namespace App\Http\Controllers;

use App\Events\NotificationCreated;
use App\Models\Notification;
use App\Models\User;
use App\Notifications\ConfirmEmailChange;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
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

        // ✅ Update field lain (email TIDAK termasuk — ganti email lewat alur konfirmasi terpisah)
        $user->fill($request->only([
            'username',
            'full_name',
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

    // 📧 Minta ganti email — kirim tautan konfirmasi ke email BARU, dibatasi sekali per hari.
    public function requestEmailChange(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'email' => 'required|email|max:255|unique:users,email,'.$user->user_id.',user_id',
        ], [
            'email.required' => 'Email baru wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah dipakai akun lain.',
        ]);

        $newEmail = strtolower(trim($request->email));

        if ($newEmail === strtolower(trim((string) $user->email))) {
            throw ValidationException::withMessages([
                'email' => ['Email baru sama dengan email Anda sekarang.'],
            ]);
        }

        // ⏳ Batasi sekali per hari.
        $limitKey = 'email_change_last:'.$user->user_id;
        if (Cache::has($limitKey)) {
            $expiresAt = Cache::get($limitKey.':until');
            $retry = $expiresAt ? max(0, Carbon::parse($expiresAt)->diffInSeconds(now())) : 86400;

            return response()->json([
                'message' => 'Anda hanya dapat mengganti email sekali dalam 24 jam. Coba lagi nanti.',
                'retry_after_seconds' => $retry,
            ], 429);
        }

        $token = Str::random(48);
        Cache::put('email_change:'.$token, [
            'user_id' => $user->user_id,
            'email' => $newEmail,
        ], now()->addHour());

        $until = now()->addDay();
        Cache::put($limitKey, $newEmail, $until);
        Cache::put($limitKey.':until', $until->toIso8601String(), $until);

        $confirmUrl = URL::temporarySignedRoute(
            'account.email.confirm',
            now()->addHour(),
            ['token' => $token]
        );

        try {
            NotificationFacade::route('mail', $newEmail)
                ->notify(new ConfirmEmailChange($confirmUrl, $user->full_name ?: $user->username, $newEmail));
        } catch (\Throwable $e) {
            Log::error('Failed to send email-change confirmation: '.$e->getMessage(), [
                'user_id' => $user->user_id,
                'new_email' => $newEmail,
            ]);
            // Batalkan rate-limit supaya user bisa mencoba lagi.
            Cache::forget($limitKey);
            Cache::forget($limitKey.':until');
            Cache::forget('email_change:'.$token);

            return response()->json([
                'message' => 'Email konfirmasi gagal dikirim. Coba lagi nanti.',
            ], 500);
        }

        return response()->json([
            'message' => 'Tautan konfirmasi telah dikirim ke '.$newEmail.'. Buka email tersebut untuk menyelesaikan perubahan.',
            'pending_email' => $newEmail,
        ]);
    }

    // ✅ Konfirmasi ganti email (dari tautan bertanda tangan di email baru).
    public function confirmEmailChange(Request $request, string $token)
    {
        $frontend = rtrim(config('app.frontend_url', 'https://portalsi.com'), '/');
        $payload = Cache::get('email_change:'.$token);

        if (! $payload || empty($payload['user_id']) || empty($payload['email'])) {
            return redirect($frontend.'/verified-success?email=invalid');
        }

        $user = User::find($payload['user_id']);
        if (! $user) {
            Cache::forget('email_change:'.$token);

            return redirect($frontend.'/verified-success?email=invalid');
        }

        $newEmail = strtolower(trim($payload['email']));
        $taken = User::where('user_id', '!=', $user->user_id)
            ->whereRaw('LOWER(TRIM(email)) = ?', [$newEmail])
            ->exists();
        if ($taken) {
            Cache::forget('email_change:'.$token);

            return redirect($frontend.'/verified-success?email=taken');
        }

        $user->email = $newEmail;
        $user->email_verified_at = now();
        $user->save();
        event(new Verified($user));

        Cache::forget('email_change:'.$token);

        return redirect($frontend.'/verified-success?email=changed');
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
