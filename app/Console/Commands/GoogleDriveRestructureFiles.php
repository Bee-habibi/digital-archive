<?php

namespace App\Console\Commands;

use App\Models\ArchiveFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Memindahkan file arsip lama ke struktur folder unit yang baru
 * (setelah restrukturisasi nama unit). Idempoten: file yang sudah
 * berada di jalur target dilewati. Jalur di database diperbarui
 * setiap kali pemindahan sukses.
 */
class GoogleDriveRestructureFiles extends Command
{
    protected $signature = 'gdrive:restructure-files
                            {--dry-run : Tampilkan rencana pemindahan tanpa memindahkan}';

    protected $description = 'Pindahkan file arsip lama ke folder unit baru (pemetaan terbaru) di disk arsip aktif';

    public function handle(): int
    {
        $disk = Storage::disk(config('filesystems.archive_disk', 'local'));
        $dry = (bool) $this->option('dry-run');

        $moved = 0;
        $skipped = 0;
        $failed = 0;

        $files = ArchiveFile::with('archive.unit')->get();

        foreach ($files as $file) {
            $archive = $file->archive;

            if (! $archive || ! $archive->unit) {
                $this->warn("? archive_file #{$file->id} tanpa arsip/unit - dilewati");
                $failed++;

                continue;
            }

            $target = $archive->unit->driveFolderPath().'/archives/'.$archive->ensureFolderName().'/'.$file->stored_name;

            if ($file->file_path === $target) {
                $skipped++;

                continue;
            }

            if (! $disk->exists($file->file_path)) {
                $this->warn("! {$file->file_path} tidak ditemukan di disk - dilewati");
                $failed++;

                continue;
            }

            if ($dry) {
                $this->line("[dry] {$file->file_path}");
                $this->line("   -> {$target}");
                $moved++;

                continue;
            }

            try {
                if (! $disk->move($file->file_path, $target)) {
                    throw new \RuntimeException('move() mengembalikan false');
                }

                // Verifikasi file benar-benar ada di target sebelum update DB.
                if (! $disk->exists($target)) {
                    throw new \RuntimeException('file tidak ditemukan di target setelah move');
                }

                $file->forceFill(['file_path' => $target])->save();

                $this->line("✓ {$archive->unit->name}: {$file->original_name}");
                $moved++;
            } catch (\Throwable $e) {
                $this->error("! gagal memindahkan {$file->file_path}: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->newLine();
        $this->info("Selesai. Dipindahkan: {$moved}, sudah benar: {$skipped}, gagal: {$failed}.");

        return $failed > 0 ? 1 : 0;
    }
}
