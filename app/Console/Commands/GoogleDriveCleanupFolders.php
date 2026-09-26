<?php

namespace App\Console\Commands;

use App\Models\Unit;
use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Illuminate\Console\Command;

/**
 * Merapikan folder unit lama di Google Drive setelah struktur berubah.
 *
 * Aturan (post-order, dari folder terdalam):
 *  - Folder unit terdaftar (units.google_folder_id) TIDAK disentuh.
 *  - Folder "archives" berisi file TIDAK disentuh. Tanpa --delete, folder
 *    "archives" apa pun juga dilindungi; dengan --delete, folder "archives"
 *    yang benar-benar kosong boleh dibersihkan (sisa arsip terhapus).
 *  - Folder lain yang kosong  -> dihapus permanen (dengan --delete)
 *                                atau dipindah ke "Lama (Struktur Tua)".
 *  - Folder lain berisi konten -> dipindah utuh ke "Lama (Struktur Tua)".
 *
 * Aman dijalankan berulang.
 */
class GoogleDriveCleanupFolders extends Command
{
    protected $signature = 'gdrive:cleanup-folders
                            {--delete : Hapus permanen folder kosong (tanpa ini dipindahkan ke "Lama (Struktur Tua)")}
                            {--dry-run : Tampilkan rencana tanpa melakukan apa pun}';

    protected $description = 'Rapikan folder unit lama di Drive setelah perubahan struktur organisasi';

    private Drive $drive;
    private string $rootId;
    private int $deleted = 0;
    private int $moved = 0;
    private int $kept = 0;

    private const FOLDER_MIME = 'application/vnd.google-apps.folder';
    private const TRASH_FOLDER_NAME = 'Lama (Struktur Tua)';

    public function handle(): int
    {
        $config = config('filesystems.disks.google');

        if (empty($config['clientId']) || empty($config['clientSecret']) || empty($config['refreshToken'])) {
            $this->error('Kredensial Google Drive belum lengkap di .env.');

            return 1;
        }

        $this->drive = $this->makeDrive($config);
        $this->rootId = $config['folderId'] ?: $this->findFolder($config['folder'] ?: 'Arsip Digital', 'root');

        if (! $this->rootId) {
            $this->error("Folder root \"{$config['folder']}\" tidak ditemukan di Drive.");

            return 1;
        }

        // ID folder unit terdaftar (termasuk unit nonaktif - jangan disentuh).
        $validIds = Unit::whereNotNull('google_folder_id')->pluck('google_folder_id')->all();

        $this->cleanTree($this->rootId, $validIds, 0);

        $this->newLine();
        $this->info("Selesai. Dipertahankan: {$this->kept}, dihapus: {$this->deleted}, dipindahkan: {$this->moved}.");

        return 0;
    }

    private function cleanTree(string $parentId, array $validIds, int $depth, bool $insideArchives = false): void
    {
        if ($depth > 5) {
            return;
        }

        $dry = (bool) $this->option('dry-run');

        foreach ($this->listFolders($parentId) as $id => $name) {
            if ($name === self::TRASH_FOLDER_NAME) {
                continue;
            }

            // Di dalam folder "archives": jangan sentuh apa pun (tempat file arsip hidup).
            if ($insideArchives) {
                $this->kept++;

                continue;
            }

            // Folder unit terdaftar: pertahankan; telusuri isinya tanpa status archives.
            if (in_array($id, $validIds, true)) {
                $this->kept++;
                $this->line("✓ {$name}  [folder unit aktif]");
                $this->cleanTree($id, $validIds, $depth + 1, false);

                continue;
            }

            // Folder "archives": tempat file arsip hidup.
            // - Berisi apa pun  -> JANGAN disentuh.
            // - Kosong + --delete -> boleh dibersihkan (sisa arsip terhapus).
            // - Kosong tanpa --delete -> dilindungi.
            if ($name === 'archives') {
                if (! $this->isEmpty($id) || ! $this->option('delete')) {
                    $this->kept++;
                    $this->line("🛡  {$name}  [folder arsip - dilindungi]");

                    continue;
                }
            }

            $this->cleanTree($id, $validIds, $depth + 1, false);

            $empty = $this->isEmpty($id);

            if ($dry) {
                $this->line("[dry] {$name} → ".($empty ? 'HAPUS (kosong)' : 'PINDAH ke "'.self::TRASH_FOLDER_NAME.'"'));

                continue;
            }

            try {
                if ($empty && $this->option('delete')) {
                    $this->drive->files->delete($id, ['supportsAllDrives' => true]);
                    $this->line("🗑  {$name} — folder kosong dihapus");
                    $this->deleted++;

                    continue;
                }

                $this->moveToTrashFolder($id, $name, $parentId);
                $this->moved++;
            } catch (\Throwable $e) {
                $this->warn('! '.$name.': '.substr($e->getMessage(), 0, 180));
            }
        }
    }

    /** @return array<string, string> id => nama */
    private function listFolders(string $parentId): array
    {
        $out = [];
        $page = null;

        do {
            $res = $this->drive->files->listFiles([
                'q' => "'{$parentId}' in parents and mimeType = '".self::FOLDER_MIME."' and trashed = false",
                'fields' => 'nextPageToken,files(id,name)',
                'pageSize' => 100,
                'pageToken' => $page,
                'supportsAllDrives' => true,
                'includeItemsFromAllDrives' => true,
            ]);

            foreach ($res->getFiles() as $f) {
                $out[$f->getId()] = $f->getName();
            }

            $page = $res->getNextPageToken();
        } while ($page);

        return $out;
    }

    private function isEmpty(string $folderId): bool
    {
        $res = $this->drive->files->listFiles([
            'q' => "'{$folderId}' in parents and trashed = false",
            'fields' => 'files(id)',
            'pageSize' => 1,
            'supportsAllDrives' => true,
            'includeItemsFromAllDrives' => true,
        ]);

        return $res->count() === 0;
    }

    private function moveToTrashFolder(string $folderId, string $name, string $currentParentId): void
    {
        $trashId = $this->ensureFolder(self::TRASH_FOLDER_NAME, $this->rootId);

        // API Drive v3: pemindahan dilakukan lewat parameter addParents/removeParents,
        // bukan field parents di body.
        $this->drive->files->update($folderId, new DriveFile(), [
            'addParents' => $trashId,
            'removeParents' => $currentParentId,
            'fields' => 'id,parents',
            'supportsAllDrives' => true,
        ]);
        $this->line("📦 {$name} — dipindahkan ke \"".self::TRASH_FOLDER_NAME.'"');
    }

    private function findFolder(string $name, string $parentId): ?string
    {
        $res = $this->drive->files->listFiles([
            'q' => "name = '".str_replace("'", "\\'", $name)."' and '".str_replace("'", "\\'", $parentId)."' in parents "
                .'and mimeType = \''.self::FOLDER_MIME."' and trashed = false",
            'fields' => 'files(id)',
            'pageSize' => 1,
            'supportsAllDrives' => true,
            'includeItemsFromAllDrives' => true,
        ]);

        return $res->count() > 0 ? $res->getFiles()[0]->getId() : null;
    }

    private function ensureFolder(string $name, string $parentId): string
    {
        if ($id = $this->findFolder($name, $parentId)) {
            return $id;
        }

        $file = new DriveFile();
        $file->setName($name);
        $file->setMimeType(self::FOLDER_MIME);
        $file->setParents([$parentId]);

        return $this->drive->files->create($file, ['fields' => 'id', 'supportsAllDrives' => true])->getId();
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
}
