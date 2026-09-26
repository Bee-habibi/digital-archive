<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class GoogleDriveTest extends Command
{
    /**
     * Koneksi Google Drive butuh file wajib database/session di lokasi fisik,
     * jadi tes ini sengaja TIDAK memakai Storage::fake dan selalu memakai disk asli.
     */
    protected $signature = 'gdrive:test {--disk= : Disk yang dites (default: disk arsip aktif)}';

    protected $description = 'Tes koneksi, upload, dan hapus file di disk arsip (Google Drive / local)';

    public function handle(): int
    {
        $diskName = $this->option('disk') ?: config('filesystems.archive_disk', 'local');
        $disk = Storage::disk($diskName);

        $this->info("Menguji disk: {$diskName}");

        if ($diskName === 'google' && (! config("filesystems.disks.google.clientId")
            || ! config("filesystems.disks.google.clientSecret")
            || ! config("filesystems.disks.google.refreshToken"))) {
            $this->error('Kredensial Google Drive belum lengkap di .env:');
            $this->line('  GOOGLE_DRIVE_CLIENT_ID, GOOGLE_DRIVE_CLIENT_SECRET, GOOGLE_DRIVE_REFRESH_TOKEN');
            $this->line('Lihat GOOGLE-DRIVE-SETUP.md untuk panduan mendapatkannya.');

            return self::FAILURE;
        }

        $path = '_gdrive_test/'.now()->format('Ymd_His').'-'.bin2hex(random_bytes(4)).'.txt';
        $body = 'Tes koneksi Arsip Digital - '.now()->toDateTimeString();

        try {
            $start = microtime(true);
            $disk->put($path, $body);
            $uploadMs = (int) ((microtime(true) - $start) * 1000);
            $this->info("✔ Upload OK ({$uploadMs} ms): {$path}");

            if ($disk->get($path) !== $body) {
                $this->error('✘ Isi file yang terbaca TIDAK sama dengan yang diupload.');

                return self::FAILURE;
            }
            $this->info('✔ Baca kembali OK, isi file cocok.');

            $size = $disk->size($path);
            $this->info("✔ Metadata OK (ukuran {$size} bytes)");

            if ($diskName === 'google' && $disk->url($path)) {
                $this->line('  Link viewer: '.$disk->url($path));
            }
        } catch (\Throwable $e) {
            $this->error('✘ GAGAL: '.$e->getMessage());
            $this->line('Pastikan kredensial benar, folder root ada, dan server bisa akses internet.');

            return self::FAILURE;
        }

        try {
            $disk->delete($path);
            $this->info('✔ Hapus file tes OK (dibersihkan dari Drive).');
        } catch (\Throwable $e) {
            $this->warn("! Upload berhasil tapi hapus file tes gagal: {$e->getMessage()}");
            $this->line("  Hapus manual di Drive: {$path}");
        }

        $this->newLine();
        $this->info("Disk '{$diskName}' SIAP dipakai untuk arsip.");

        return self::SUCCESS;
    }
}
