<?php
/**
 * gdrive-backup.php — upload dump database ke Google Drive (Buffy/Codebuff).
 *
 * Dipanggil otomatis oleh sync-out.bat (mode auto) setelah dump selesai,
 * atau manual:  php gdrive-backup.php
 *
 * - Pakai kredensial Google Drive yang SUDAH ADA di .env proyek (disk 'google')
 *   → tidak perlu install Google Drive Desktop / rclone / login ulang.
 * - Folder tujuan: [root proyek]/BACKUP-DATABASE (dibuat otomatis).
 * - Retensi: file backup di Drive lebih tua dari 30 hari dihapus otomatis.
 * - Aman: kalau Drive/network gagal, backup LOKAL di E:\sync tetap ada
 *   (script ini hanya pelengkap, kegagalannya tidak menggagalkan backup utama).
 */

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Storage;

$localDump = 'E:/sync/digital_archive.sql';
$folder    = 'BACKUP-DATABASE';
$retainDays = 30;

if (! is_file($localDump)) {
    fwrite(STDERR, "[gdrive-backup] dump lokal tidak ditemukan: $localDump\n");
    exit(1);
}

try {
    $disk  = Storage::disk('google');
    $stamp = date('Y-m-d_His');
    $remotePath = "$folder/digital_archive_$stamp.sql";

    // --- 1) Pastikan folder tujuan ada (flysystem-google-drive-ext membuat
    //        folder otomatis saat put, tapi kita cek eksplisit agar rapi) ---
    if (! in_array($folder, $disk->directories('/'), true)) {
        $disk->makeDirectory($folder);
        echo "[gdrive-backup] folder '$folder' dibuat di root Drive\n";
    }

    // --- 2) Upload dump (streamed; dump biasanya < 1 MB) ---
    $contents = file_get_contents($localDump);
    $disk->put($remotePath, $contents);
    echo "[gdrive-backup] ter-upload: $remotePath (" . strlen($contents) . " bytes)\n";

    // --- 3) Ikutkan log sync (kalau ada) untuk audit trail di Drive ---
    $localLog = 'E:/sync/logs/sync-out.log';
    if (is_file($localLog)) {
        $disk->put("$folder/sync-out.log", file_get_contents($localLog));
    }

    // --- 4) Retensi: hapus backup Drive lebih tua dari 30 hari ---
    $cutoff = strtotime("-{$retainDays} days");
    $deleted = 0;
    foreach ($disk->files($folder) as $file) {
        if (! str_ends_with($file, '.sql')) {
            continue; // jangan sentuh log/atribut lain
        }
        // nama file: digital_archive_YYYY-MM-DD_HHmmss.sql
        if (preg_match('/digital_archive_(\d{4}-\d{2}-\d{2})_/', basename($file), $m)) {
            if (strtotime($m[1]) < $cutoff) {
                $disk->delete($file);
                $deleted++;
            }
        }
    }
    echo "[gdrive-backup] retensi: $deleted file lama (>$retainDays hari) dihapus\n";

    // --- 5) Laporan singkat isi folder ---
    $files = $disk->files($folder);
    echo "[gdrive-backup] total backup di Drive sekarang: " . count($files) . " file\n";

    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, '[gdrive-backup] GAGAL: ' . substr($e->getMessage(), 0, 200) . "\n");
    exit(1);
}
