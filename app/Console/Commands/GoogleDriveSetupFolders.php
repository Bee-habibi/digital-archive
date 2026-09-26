<?php

namespace App\Console\Commands;

use App\Models\Unit;
use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Illuminate\Console\Command;

/**
 * Membuat & memetakan struktur folder Google Drive sesuai hierarki unit kerja:
 *
 *   Arsip Digital/                          (root, dari .env GOOGLE_DRIVE_FOLDER)
 *   ├── Bapenda Provinsi Kalimantan Utara/  (level 1)
 *   │   ├── Sub Bagian Umum/                (level 2)
 *   │   ├── Bidang Perencanaan/
 *   │   ├── Bidang Pengelolaan/
 *   │   └── Bidang Evaluasi/
 *   ├── UPTD Bapenda Bulungan/              (level 1, setara Bapenda)
 *   │   ├── Sub Bagian Umum/                (level 2)
 *   │   ├── Seksi Penagihan/
 *   │   └── Seksi Pendataan/
 *   └── UPTD Bapenda Tarakan / Nunukan / Malinau / Tana Tidung (pola sama)
 *
 * Folder yang sudah ada TIDAK diduplikasi (dicari dulu per nama + parent).
 * ID folder setiap unit disimpan ke units.google_folder_id.
 */
class GoogleDriveSetupFolders extends Command
{
    protected $signature = 'gdrive:setup-folders
                            {--unit= : Hanya unit dengan kode ini}
                            {--dry-run : Tampilkan rencana tanpa membuat folder}';

    protected $description = 'Buat/petakan folder Google Drive untuk semua unit kerja (sesuai hierarki organisasi)';

    private Drive $drive;
    private string $rootId;
    private int $created = 0;
    private int $existing = 0;
    private array $errors = [];

    private const FOLDER_MIME = 'application/vnd.google-apps.folder';

    public function handle(): int
    {
        $config = config('filesystems.disks.google');

        if (empty($config['clientId']) || empty($config['clientSecret']) || empty($config['refreshToken'])) {
            $this->error('Kredensial Google Drive belum lengkap di .env (GOOGLE_DRIVE_CLIENT_ID/SECRET/REFRESH_TOKEN).');

            return 1;
        }

        $this->drive = $this->makeDrive($config);

        // Tentukan root: folder ID dari .env, atau folder bernama GOOGLE_DRIVE_FOLDER di My Drive.
        $this->rootId = $config['folderId']
            ?: $this->ensureFolder($config['folder'] ?: 'Arsip Digital', 'root');

        $this->info("Root folder ID: {$this->rootId}");

        $units = Unit::where('level', '>=', 1)->where('is_active', true)->orderBy('level')->orderBy('name')->get();

        if ($only = $this->option('unit')) {
            $units = $units->where('code', $only);
        }

        foreach ($units as $unit) {
            $this->processUnit($unit);
        }

        $this->newLine();
        $this->info("Selesai. Folder baru dibuat: {$this->created}, sudah ada: {$this->existing}.");

        if ($this->errors !== []) {
            $this->warn('Ada kegagalan:');
            foreach ($this->errors as $e) {
                $this->line("  - {$e}");
            }

            return 1;
        }

        return 0;
    }

    private function processUnit(Unit $unit): void
    {
        $dry = (bool) $this->option('dry-run');

        try {
            // Jalur folder = rantai nama unit dari level 1 sampai unit ini
            // (folder root "Arsip Digital" dari .env berada di atasnya).
            $segments = [];
            $cursor = $unit;
            while ($cursor !== null && $cursor->level >= 1) {
                array_unshift($segments, $cursor->name);
                $cursor = $cursor->parent;
            }

            $path = implode('/', array_map(fn ($s) => $this->sanitize($s), $segments));

            if ($dry) {
                $this->line("[dry] {$path}  [akan dibuat/dipetakan]");

                return;
            }

            $parentId = $this->rootId;
            foreach ($segments as $segment) {
                $parentId = $this->ensureFolder($this->sanitize($segment), $parentId);
            }

            // Simpan ID folder unit ini (yang paling dalam).
            $unit->forceFill(['google_folder_id' => $parentId])->save();

            $this->line("✓ {$path}  [".substr($parentId, 0, 12).'...]');
        } catch (\Throwable $e) {
            $this->errors[] = "{$unit->name}: {$e->getMessage()}";
        }
    }

    /** Cari folder berdasarkan nama + parent; buat bila belum ada. */
    private function ensureFolder(string $name, string $parentId): string
    {
        $query = "name = '".str_replace("'", "\\'", $name)."' "
            ."and '".str_replace("'", "\\'", $parentId)."' in parents "
            ."and mimeType = '".self::FOLDER_MIME."' and trashed = false";

        $res = $this->drive->files->listFiles([
            'q' => $query,
            'fields' => 'files(id,name)',
            'pageSize' => 5,
            'supportsAllDrives' => true,
            'includeItemsFromAllDrives' => true,
        ]);

        if ($res->count() > 0) {
            $this->existing++;

            return $res->getFiles()[0]->getId();
        }

        $file = new DriveFile();
        $file->setName($name);
        $file->setMimeType(self::FOLDER_MIME);
        $file->setParents([$parentId]);

        $created = $this->drive->files->create($file, [
            'fields' => 'id',
            'supportsAllDrives' => true,
        ]);

        $this->created++;

        return $created->getId();
    }

    private function makeDrive(array $config): Drive
    {
        $client = new Client();
        $client->setClientId($config['clientId']);
        $client->setClientSecret($config['clientSecret']);
        $client->refreshToken($config['refreshToken']);
        $client->setApplicationName(config('app.name', 'Arsip Digital'));

        return new Drive($client);
    }

    /** Konsisten dengan Unit::sanitizeFolderName(). */
    private function sanitize(string $name): string
    {
        return trim(str_replace(['/', '\\'], '-', $name));
    }
}
