<?php

namespace App\Jobs;

use App\Models\Post;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Membuat thumbnail untuk satu video (post) yang belum punya thumbnail valid.
 * Berjalan di belakang layar lewat queue. WAJIB ffmpeg terpasang di server.
 */
class GenerateVideoThumbnail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 240;

    public function __construct(public int $postId)
    {
    }

    public function handle(): void
    {
        $post = Post::find($this->postId);
        if (! $post || empty($post->media_url) || ! $post->is_video) {
            return;
        }

        $disk = config('filesystems.default', 'public');
        $videoPath = $this->relativePath($post->media_url);
        if (! $videoPath) {
            return;
        }

        // Sudah punya thumbnail yang filenya benar-benar ada? lewati.
        if (! empty($post->thumbnail_url)) {
            $existing = $this->relativePath($post->thumbnail_url);
            if ($existing && $this->safeExists($disk, $existing)) {
                return;
            }
        }

        $ffmpeg = trim((string) @shell_exec('command -v ffmpeg 2>/dev/null'));
        if ($ffmpeg === '' || ! @is_executable($ffmpeg)) {
            Log::warning('GenerateVideoThumbnail: ffmpeg tidak tersedia di server, dilewati.');
            return;
        }

        $ext = pathinfo($videoPath, PATHINFO_EXTENSION) ?: 'mp4';
        $tmpVideo = tempnam(sys_get_temp_dir(), 'psi_vid_').'.'.$ext;
        $tmpThumb = tempnam(sys_get_temp_dir(), 'psi_thumb_').'.jpg';

        try {
            $stream = Storage::disk($disk)->readStream($videoPath);
            if (! $stream) {
                return;
            }
            $out = fopen($tmpVideo, 'wb');
            stream_copy_to_stream($stream, $out);
            fclose($out);
            if (is_resource($stream)) {
                fclose($stream);
            }

            // Ambil 1 frame pada detik ke-1, lebar 640px (jaga rasio).
            $cmd = sprintf(
                '%s -y -ss 1 -i %s -frames:v 1 -vf %s -q:v 3 %s 2>/dev/null',
                escapeshellarg($ffmpeg),
                escapeshellarg($tmpVideo),
                escapeshellarg('scale=640:-2'),
                escapeshellarg($tmpThumb)
            );
            @shell_exec($cmd);

            if (! file_exists($tmpThumb) || filesize($tmpThumb) < 100) {
                Log::warning('GenerateVideoThumbnail: ffmpeg gagal', ['post_id' => $post->post_id]);
                return;
            }

            // Konvensi path yang dikenali generateThumbnailUrl di controller.
            $nameOnly = pathinfo($videoPath, PATHINFO_FILENAME);
            $thumbPath = "uploads/posts/thumbnails/{$nameOnly}.jpg";
            Storage::disk($disk)->put($thumbPath, file_get_contents($tmpThumb), 'public');

            $post->thumbnail_url = Storage::disk($disk)->url($thumbPath);
            $post->save();

            Log::info('GenerateVideoThumbnail: berhasil', ['post_id' => $post->post_id]);
        } catch (\Throwable $e) {
            Log::error('GenerateVideoThumbnail error', [
                'post_id' => $this->postId,
                'error' => $e->getMessage(),
            ]);
        } finally {
            @unlink($tmpVideo);
            @unlink($tmpThumb);
        }
    }

    private function safeExists(string $disk, string $path): bool
    {
        try {
            return Storage::disk($disk)->exists($path);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function relativePath(string $url): ?string
    {
        if (preg_match('#https?://[^/]+/(.*)$#', $url, $m)) {
            return $m[1];
        }
        if (str_starts_with($url, '/storage/')) {
            return substr($url, 9);
        }

        return ltrim($url, '/') ?: null;
    }
}
