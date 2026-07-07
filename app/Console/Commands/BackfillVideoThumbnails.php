<?php

namespace App\Console\Commands;

use App\Jobs\GenerateVideoThumbnail;
use App\Models\Post;
use Illuminate\Console\Command;

/**
 * Scan semua video dan buatkan thumbnail untuk yang belum punya (atau thumbnail-nya 404).
 * Job akan otomatis melewati video yang sudah punya thumbnail valid.
 *
 *   php artisan thumbnails:backfill          # kirim ke queue (diproses worker di belakang)
 *   php artisan thumbnails:backfill --sync   # proses langsung sekarang (tanpa queue)
 */
class BackfillVideoThumbnails extends Command
{
    protected $signature = 'thumbnails:backfill {--sync : Proses langsung, bukan lewat queue}';

    protected $description = 'Buat thumbnail untuk video lama yang belum memilikinya.';

    public function handle(): int
    {
        $sync = (bool) $this->option('sync');
        $count = 0;

        Post::query()
            ->where('is_video', true)
            ->orderBy('post_id')
            ->chunkById(200, function ($posts) use (&$count, $sync) {
                foreach ($posts as $post) {
                    if ($sync) {
                        GenerateVideoThumbnail::dispatchSync($post->post_id);
                    } else {
                        GenerateVideoThumbnail::dispatch($post->post_id);
                    }
                    $count++;
                }
                $this->info("Diproses {$count} video…");
            });

        $this->info($sync
            ? "Selesai. {$count} video diproses."
            : "Selesai. {$count} video dikirim ke queue — pastikan 'php artisan queue:work' berjalan.");

        return self::SUCCESS;
    }
}
