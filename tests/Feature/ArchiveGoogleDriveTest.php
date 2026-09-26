<?php

namespace Tests\Feature;

use App\Models\Archive;
use App\Models\ArchiveCategory;
use App\Models\ArchiveType;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Memastikan alur upload/download/hapus file arsip benar-benar lewat disk yang
 * dikonfigurasi (FILESYSTEM_ARCHIVE_DISK), termasuk disk "google" (Google Drive).
 *
 * Koneksi internet & kredensial Google tidak diperlukan: disk "google" di-fake,
 * yang tetap membuktikan seluruh wiring (resolve disk 'google' dari config,
 * path folder unit, controller, metadata ArchiveFile) bekerja.
 */
class ArchiveGoogleDriveTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Unit $unit;

    private ArchiveCategory $category;

    private ArchiveType $type;

    protected function setUp(): void
    {
        parent::setUp();

        config(['filesystems.disks.google' => [
            'driver' => 'google',
            'clientId' => 'test-client-id',
            'clientSecret' => 'test-secret',
            'refreshToken' => 'test-refresh-token',
            'folderId' => null,
            'folder' => 'Arsip Digital',
        ]]);

        $role = Role::create(['name' => Role::ADMIN]);
        $unit = Unit::create(['name' => 'UPTD Testing', 'code' => 'UPT-TEST']);
        $category = ArchiveCategory::create(['name' => 'Keuangan', 'code' => 'KEU']);
        $type = ArchiveType::create(['category_id' => $category->id, 'name' => 'SPJ']);

        $this->user = User::factory()->create([
            'role_id' => $role->id,
            'unit_id' => $unit->id,
        ]);

        $this->unit = $unit;
        $this->category = $category;
        $this->type = $type;
    }

    public function test_store_uploads_files_to_configured_archive_disk(): void
    {
        Storage::fake('google');
        config(['filesystems.archive_disk' => 'google']);

        $response = $this->actingAs($this->user)->post(route('archives.store'), [
            'title' => 'Arsip Drive Test',
            'category_id' => $this->category->id,
            'type_id' => $this->type->id,
            'year' => now()->year,
            'files' => [UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf')],
        ]);

        $response->assertRedirect();
        $archive = Archive::where('title', 'Arsip Drive Test')->firstOrFail();

        // Folder arsip manusiawi: "archives/{id} - {judul}"
        $this->assertSame($archive->id.' - Arsip Drive Test', $archive->folder_name);
        Storage::disk('google')->assertExists($archive->unit->driveFolderPath().'/archives/'.$archive->folder_name);

        $file = $archive->files()->firstOrFail();
        Storage::disk('google')->assertExists($file->file_path);
        $this->assertSame('dokumen.pdf', $file->original_name);
        // Nama file di disk = nama asli file (judul arsip ada di nama foldernya).
        $this->assertSame('dokumen.pdf', $file->stored_name);
    }

    public function test_duplicate_file_names_are_not_overwritten(): void
    {
        Storage::fake('google');
        config(['filesystems.archive_disk' => 'google']);

        $this->actingAs($this->user)->post(route('archives.store'), [
            'title' => 'Arsip Nama Kembar',
            'category_id' => $this->category->id,
            'type_id' => $this->type->id,
            'year' => now()->year,
            'files' => [
                UploadedFile::fake()->create('dokumen.pdf', 50, 'application/pdf'),
                UploadedFile::fake()->create('dokumen.pdf', 60, 'application/pdf'),
            ],
        ]);

        $archive = Archive::where('title', 'Arsip Nama Kembar')->firstOrFail();
        $names = $archive->files()->pluck('stored_name')->all();

        $this->assertEqualsCanonicalizing(['dokumen.pdf', 'dokumen (2).pdf'], $names);
        foreach ($archive->files as $f) {
            Storage::disk('google')->assertExists($f->file_path);
        }
    }

    public function test_download_streams_from_configured_archive_disk(): void
    {
        Storage::fake('google');
        config(['filesystems.archive_disk' => 'google']);

        $archive = Archive::create([
            'archive_number' => 'ARS/TEST/001/'.now()->year,
            'title' => 'Arsip Download Test',
            'category_id' => $this->category->id,
            'type_id' => $this->type->id,
            'unit_id' => $this->unit->id,
            'year' => now()->year,
            'status' => Archive::STATUS_MENUNGGU_VERIFIKASI,
            'created_by' => $this->user->id,
        ]);

        $fake = Storage::fake('google');
        $path = $archive->unit->driveFolderPath().'/archives/'.$archive->id.'/aaa.pdf';
        $fake->put($path, 'PDFCONTENT');

        $fileRecord = $archive->files()->create([
            'original_name' => 'laporan.pdf',
            'stored_name' => 'aaa.pdf',
            'file_path' => $path,
            'mime_type' => 'application/pdf',
            'file_size' => 9,
            'uploaded_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('archives.files.download', $fileRecord));

        $response->assertOk();
        $response->assertDownload('laporan.pdf');
        $this->assertSame('PDFCONTENT', $response->streamedContent());
    }

    public function test_delete_removes_files_and_folder_from_configured_archive_disk(): void
    {
        Storage::fake('google');
        config(['filesystems.archive_disk' => 'google']);

        $archive = Archive::create([
            'archive_number' => 'ARS/TEST/002/'.now()->year,
            'title' => 'Arsip Delete Test',
            'category_id' => $this->category->id,
            'type_id' => $this->type->id,
            'unit_id' => $this->unit->id,
            'year' => now()->year,
            'status' => Archive::STATUS_MENUNGGU_VERIFIKASI,
            'created_by' => $this->user->id,
        ]);

        $fake = Storage::fake('google');
        $folder = $archive->unit->driveFolderPath().'/archives/'.$archive->ensureFolderName();
        $path = $folder.'/bbb.pdf';
        $fake->put($path, 'PDFCONTENT');

        $fileRecord = $archive->files()->create([
            'original_name' => 'hapus.pdf',
            'stored_name' => 'bbb.pdf',
            'file_path' => $path,
            'mime_type' => 'application/pdf',
            'file_size' => 9,
            'uploaded_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->delete(route('archives.destroy', $archive));

        $response->assertRedirect();
        Storage::disk('google')->assertMissing($path);

        // Folder arsip harus ikut hilang (listing kosong / folder tidak ada).
        $remaining = [];
        try {
            $remaining = Storage::disk('google')->allFiles($folder);
        } catch (\Throwable $e) {
            $remaining = [];
        }
        $this->assertCount(0, $remaining);
        $this->assertSoftDeleted($archive);
    }
}
