<?php

namespace App\Console\Commands;

use App\Models\ArchiveFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class GoogleDriveMigrate extends Command
{
    /**
     * Memindahkan file arsip dari disk lama (mis. local) ke disk baru (mis. google)
     * dan memperbarui file_path di database. File sumber TIDAK dihapus otomatis —
     * gunakan --delete-source kalau yakin hasilnya sudah benar.
     */
    protected $signature = 'gdrive:migrate
        {--from=local : Disk sumber}
        {--to= : Disk tujuan (default: disk arsip aktif)}
        {--delete-source : Hapus file sumber setelah berhasil dipindah}';

    protected $description = 'Pindahkan file arsip antar disk penyimpanan (local <-> Google Drive) dan update path di database';

    public function handle(): int
    {
        $from = (string) $this->option('from');
        $to   = (string) ($this->option('to') ?: config('filesystems.archive_disk', 'local'));

        if ($from === $to) {
            $this->error("Disk sumber dan tujuan sama ({$from}). Tidak ada yang perlu dilakukan.");

            return self::FAILURE;
        }

        $src = Storage::disk($from);
        $dst = Storage::disk($to);

        $files = ArchiveFile::query()->orderBy('id')->get();

        if ($files->isEmpty()) {
            $this->warn('Tidak ada file arsip di database.');

            return self::SUCCESS;
        }

        $this->info("Memindahkan {$files->count()} file: {$from} -> {$to}" . ($this->option('delete-source') ? ' (hapus sumber)' : ''));

        $moved = 0; $skipped = 0; $failed = 0;

        foreach ($files as $file) {
            $oldPath = $file->file_path;

            // Normalisasi: path lama bisa memakai backslash (hasil Windows) — Flysystem pakai "/".
            $normalized = str_replace('\\', '/', $oldPath);

            if (! $src->exists($normalized)) {
                // Sudah ada di tujuan dengan path sama? Anggap sudah dimigrasi sebelumnya.
                if ($dst->exists($normalized)) {
                    $skipped++;
                    $this->line("  = #{$file->id} sudah ada di {$to}, lewati: {$normalized}");
                } else {
                    $skipped++;
                    $this->warn("  ? #{$file->id} tidak ditemukan di {$from} DAN {$to}: {$normalized}");
                }
                continue;
            }

            try {
                $stream = $src->readStream($normalized);
                $dst->writeStream($normalized, $stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }

                // Verifikasi ukuran di tujuan sebelum menyentuh sumber/database.
                if ((int) $dst->size($normalized) !== (int) $file->file_size) {
                    throw new \RuntimeException('Ukuran file di tujuan tidak sama dengan database.');
                }

                if ($this->option('delete-source')) {
                    $src->delete($normalized);
                }

                $moved++;
                $this->info("  ✔ #{$file->id} [arsip {$file->archive_id}] {$normalized}");
            } catch (\Throwable $e) {
                $failed++;
                $this->error("  ✘ #{$file->id} {$normalized}: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("Selesai. Dipindah: {$moved}, dilewati: {$skipped}, gagal: {$failed}");

        if ($failed > 0) {
            $this->warn('Ada file yang gagal — jalankan ulang command ini (aman diulang, file yang sudah pindah akan dilewati).');

            return self::FAILURE;
        }

        if ($moved > 0 && $to === 'google' && ! $this->option('delete-source')) {
            $this->line('Catatan: file sumber masih di disk lama. Setelah yakin, jalankan ulang dengan --delete-source untuk membersihkan.');
        }

        return self::SUCCESS;
    }
}
