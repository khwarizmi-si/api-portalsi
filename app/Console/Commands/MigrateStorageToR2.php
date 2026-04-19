<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MigrateStorageToR2 extends Command
{
    protected $signature = 'storage:migrate-to-r2
                            {--dry-run : Simulasi saja, tidak upload sungguhan}
                            {--folder= : Hanya migrasi folder tertentu (contoh: profile_pictures)}
                            {--force : Skip konfirmasi}';

    protected $description = 'Migrasi semua file dari local public storage ke Cloudflare R2';

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $folder   = $this->option('folder');

        if ($isDryRun) {
            $this->warn('🔍 MODE DRY-RUN — tidak ada file yang benar-benar diupload.');
        }

        // Pastikan R2 bisa diakses
        if (!$isDryRun) {
            try {
                Storage::disk('r2')->put('_migration_test.txt', 'ok');
                Storage::disk('r2')->delete('_migration_test.txt');
            } catch (\Throwable $e) {
                $this->error('❌ Tidak bisa connect ke R2: ' . $e->getMessage());
                $this->line('Pastikan .env sudah diisi dan config:clear sudah dijalankan.');
                return self::FAILURE;
            }
            $this->info('✅ Koneksi ke R2 berhasil.');
        }

        // Scan semua file di public disk (storage/app/public)
        $allFiles = Storage::disk('public')->allFiles($folder ?: '');

        $total   = count($allFiles);
        $success = 0;
        $skipped = 0;
        $failed  = 0;

        if ($total === 0) {
            $this->warn('Tidak ada file ditemukan di storage/app/public/' . ($folder ?: ''));
            return self::SUCCESS;
        }

        $this->info("📦 Ditemukan {$total} file" . ($folder ? " di folder [{$folder}]" : '') . '.');

        if (!$isDryRun && !$this->option('force')) {
            if (!$this->confirm("Lanjutkan upload {$total} file ke R2?")) {
                $this->line('Dibatalkan.');
                return self::SUCCESS;
            }
        }

        $bar = $this->output->createProgressBar($total);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% — %message%');
        $bar->start();

        foreach ($allFiles as $relativePath) {
            $bar->setMessage($relativePath);

            // Skip file sistem tersembunyi
            if (Str::startsWith(basename($relativePath), '.')) {
                $skipped++;
                $bar->advance();
                continue;
            }

            if ($isDryRun) {
                $this->line("\n  [DRY-RUN] akan upload: {$relativePath}");
                $success++;
                $bar->advance();
                continue;
            }

            try {
                // Cek apakah sudah ada di R2 (skip jika sudah)
                if (Storage::disk('r2')->exists($relativePath)) {
                    $skipped++;
                    $bar->advance();
                    continue;
                }

                // Baca dari local, upload ke R2
                $contents = Storage::disk('public')->get($relativePath);
                $mimeType = Storage::disk('public')->mimeType($relativePath);

                Storage::disk('r2')->put($relativePath, $contents, [
                    'visibility'  => 'public',
                    'ContentType' => $mimeType ?: 'application/octet-stream',
                ]);

                $success++;
            } catch (\Throwable $e) {
                $failed++;
                $this->newLine();
                $this->error("  ❌ Gagal: {$relativePath} — " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Ringkasan
        $this->table(
            ['Status', 'Jumlah'],
            [
                ['✅ Berhasil diupload', $success],
                ['⏭️  Di-skip (sudah ada / hidden)', $skipped],
                ['❌ Gagal', $failed],
                ['📦 Total file', $total],
            ]
        );

        if ($failed > 0) {
            $this->warn("Ada {$failed} file yang gagal. Coba jalankan ulang perintah ini — file yang sudah ada di R2 akan di-skip otomatis.");
        }

        if (!$isDryRun && $success > 0) {
            $this->info('🎉 Migrasi selesai! URL lama di database perlu diupdate juga.');
            $this->line('   Gunakan fitur Find & Replace di phpMyAdmin:');
            $this->line('   Cari  : https://api.portalsi.com/storage/');
            $this->line('   Ganti : ' . rtrim(config('filesystems.disks.r2.url'), '/') . '/');
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
