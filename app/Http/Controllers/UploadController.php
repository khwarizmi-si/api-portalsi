<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Presigned upload langsung ke R2 (tanpa file melewati Laravel).
 * Frontend: minta URL di sini → PUT file langsung ke R2 → kirim `media_key` ke /api/posts.
 */
class UploadController extends Controller
{
    private const IMAGE_EXT = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    private const VIDEO_EXT = ['mp4', 'mov', 'webm', 'avi', '3gp', 'mkv', 'm4v'];

    public function presign(Request $request)
    {
        if (! Auth::user()) {
            return response()->json(['message' => 'Sesi tidak tersedia.'], 401);
        }

        $data = $request->validate([
            'extension' => ['required', 'string', 'max:10'],
            'content_type' => ['required', 'string', 'max:120'],
            'kind' => ['nullable', 'in:post,story'],
        ]);

        $ext = strtolower(preg_replace('/[^a-z0-9]/i', '', $data['extension']));
        if (! in_array($ext, array_merge(self::IMAGE_EXT, self::VIDEO_EXT), true)) {
            return response()->json(['message' => 'Format media tidak didukung.'], 422);
        }
        // Content-Type harus image/* atau video/* (dipakai saat menandatangani PUT).
        if (! preg_match('#^(image|video)/[a-z0-9.+-]+$#i', $data['content_type'])) {
            return response()->json(['message' => 'Tipe konten tidak valid.'], 422);
        }

        $folder = ($data['kind'] ?? 'post') === 'story' ? 'uploads/stories' : 'uploads/posts';
        $key = $folder.'/'.Str::uuid()->toString().'.'.$ext;

        try {
            $disk = Storage::disk('r2');
            $client = $disk->getClient();
            $command = $client->getCommand('PutObject', [
                'Bucket' => config('filesystems.disks.r2.bucket'),
                'Key' => $key,
                'ContentType' => $data['content_type'],
            ]);
            $presigned = $client->createPresignedRequest($command, '+15 minutes');

            return response()->json([
                'upload_url' => (string) $presigned->getUri(),
                'key' => $key,
                'public_url' => $disk->url($key),
                'content_type' => $data['content_type'],
            ]);
        } catch (\Throwable $e) {
            Log::error('Presign R2 gagal', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Tidak dapat menyiapkan unggahan langsung.'], 503);
        }
    }
}
