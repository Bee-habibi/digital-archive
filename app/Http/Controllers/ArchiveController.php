<?php

namespace App\Http\Controllers;

use App\Models\Archive;
use App\Models\ArchiveCategory;
use App\Models\ArchiveFile;
use App\Models\ArchiveType;
use App\Traits\LogsActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ArchiveController extends Controller
{
    use LogsActivity;

    public function index(Request $request)
    {
        $user = Auth::user();

        // scopeVisibleTo mengunci hasil ke unit_id user kalau dia admin biasa.
        // Ini jalan di level query database, BUKAN disembunyikan di frontend.
        $query = Archive::query()->visibleTo($user)->with(['unit', 'category', 'type', 'creator']);

        if ($request->filled('q')) {
            $q = $request->string('q');
            $query->where(function ($sub) use ($q) {
                $sub->where('archive_number', 'like', "%{$q}%")
                    ->orWhere('document_number', 'like', "%{$q}%")
                    ->orWhere('title', 'like', "%{$q}%");
            });
        }
        if ($request->filled('category_id')) $query->where('category_id', $request->category_id);
        if ($request->filled('type_id')) $query->where('type_id', $request->type_id);
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('year')) $query->where('year', $request->year);
        if ($request->filled('unit_id') && $user->canAccessAllUnits()) $query->where('unit_id', $request->unit_id);
        if ($request->filled('date_from')) $query->whereDate('document_date', '>=', $request->date_from);
        if ($request->filled('date_to')) $query->whereDate('document_date', '<=', $request->date_to);

        $archives = $query->latest()->paginate(15)->withQueryString();

        $categories = ArchiveCategory::where('is_active', true)->get();

        return view('archives.index', compact('archives', 'categories'));
    }

    public function create()
    {
        $categories = ArchiveCategory::where('is_active', true)->with('types')->get();
        return view('archives.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        $data = $request->validate([
            'document_number' => ['nullable', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'exists:archive_categories,id'],
            'type_id' => ['required', 'exists:archive_types,id'],
            'document_date' => ['nullable', 'date'],
            'year' => ['required', 'integer', 'min:1900', 'max:' . (date('Y') + 1)],
            'description' => ['nullable', 'string'],
            'files' => ['nullable', 'array'],
            'files.*' => ['file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png'], // 20MB, MIME divalidasi
        ]);

        // Admin HANYA bisa input untuk unitnya sendiri, unit_id TIDAK boleh diambil dari request.
        $unitId = $user->canAccessAllUnits() ? $request->input('unit_id', $user->unit_id) : $user->unit_id;

        $archive = Archive::create([
            ...$data,
            'unit_id' => $unitId,
            'archive_number' => $this->generateArchiveNumber($unitId),
            'status' => Archive::STATUS_MENUNGGU_VERIFIKASI,
            'created_by' => $user->id,
        ]);

        $this->storeFiles($archive, $request);

        $this->logActivity('create', 'archives', "Menambah arsip #{$archive->archive_number}");

        return redirect()->route('archives.show', $archive)->with('success', 'Arsip berhasil disimpan dan menunggu verifikasi.');
    }

    public function show(Archive $archive)
    {
        $this->authorize('view', $archive);
        $archive->load(['unit', 'category', 'type', 'files', 'verifications.user', 'creator', 'updater', 'verifier']);
        return view('archives.show', compact('archive'));
    }

    public function edit(Archive $archive)
    {
        $this->authorize('update', $archive);
        $categories = ArchiveCategory::where('is_active', true)->with('types')->get();
        return view('archives.edit', compact('archive', 'categories'));
    }

    public function update(Request $request, Archive $archive)
    {
        $this->authorize('update', $archive);

        $data = $request->validate([
            'document_number' => ['nullable', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'exists:archive_categories,id'],
            'type_id' => ['required', 'exists:archive_types,id'],
            'document_date' => ['nullable', 'date'],
            'year' => ['required', 'integer', 'min:1900', 'max:' . (date('Y') + 1)],
            'description' => ['nullable', 'string'],
            'files' => ['nullable', 'array'],
            'files.*' => ['file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png'],
        ]);

        // Kalau tadinya "perlu_perbaikan" dan diperbaiki, statusnya balik "menunggu_verifikasi"
        $newStatus = $archive->status === Archive::STATUS_PERLU_PERBAIKAN
            ? Archive::STATUS_MENUNGGU_VERIFIKASI
            : $archive->status;

        $archive->update([
            ...$data,
            'status' => $newStatus,
            'updated_by' => Auth::id(),
        ]);

        $this->storeFiles($archive, $request);

        $this->logActivity('update', 'archives', "Mengubah arsip #{$archive->archive_number}");

        return redirect()->route('archives.show', $archive)->with('success', 'Arsip berhasil diperbarui.');
    }

    public function destroy(Archive $archive)
    {
        $this->authorize('delete', $archive);

        $disk = config('filesystems.archive_disk', 'local');
        foreach ($archive->files as $file) {
            Storage::disk($disk)->delete($file->file_path);
        }

        // Hapus juga folder arsipnya di disk (Drive/local) supaya tidak menyisakan
        // folder kosong "archives/{id} - {judul}". Jika gagal, arsip tetap terhapus —
        // folder sisa bisa dirapikan lewat gdrive:cleanup-folders.
        try {
            $this->deleteArchiveDirectory($disk, $archive);
        } catch (\Throwable $e) {
            report($e);
        }

        $number = $archive->archive_number;
        $archive->delete(); // soft delete

        $this->logActivity('delete', 'archives', "Menghapus arsip #{$number}");

        return redirect()->route('archives.index')->with('success', 'Arsip berhasil dihapus.');
    }

    /**
     * Hapus folder arsip di disk aktif. Di Google Drive, adapter memindahkan
     * item yang dihapus ke Trash — jadi masih bisa dikembalikan bila keliru.
     */
    private function deleteArchiveDirectory(string $disk, Archive $archive): void
    {
        $dir = $archive->unit->driveFolderPath().'/archives/'.$archive->ensureFolderName();

        foreach (Storage::disk($disk)->allFiles($dir) as $f) {
            Storage::disk($disk)->delete($f);
        }
        foreach (Storage::disk($disk)->allDirectories($dir) as $d) {
            Storage::disk($disk)->deleteDirectory($d);
        }
        Storage::disk($disk)->deleteDirectory($dir);
    }

    public function downloadFile(ArchiveFile $file)
    {
        $this->authorize('view', $file->archive);

        $this->logActivity('download', 'archives', "Mengunduh file arsip #{$file->archive->archive_number} ({$file->original_name})");

        $disk = config('filesystems.archive_disk', 'local');
        return Storage::disk($disk)->download($file->file_path, $file->original_name);
    }

    private function storeFiles(Archive $archive, Request $request): void
    {
        if (! $request->hasFile('files')) return;

        // Disk ditentukan lewat .env (FILESYSTEM_ARCHIVE_DISK=google atau local).
        // Ganti disk ini kapan saja tanpa mengubah kode saat pindah dari akun pribadi
        // ke Google Workspace instansi, atau nanti pindah ke VPS/local storage.
        $disk = config('filesystems.archive_disk', 'local');

        // Folder arsip yang manusiawi: ".../archives/{id} - {judul arsip}"
        // (nama disimpan sekali di DB agar stabil walau judul kemudian diedit).
        $folderPath = $archive->unit->driveFolderPath() . '/archives/' . $archive->ensureFolderName();

        foreach ($request->file('files') as $uploaded) {
            // Nama file di disk = nama asli file (mudah dikenali). Konteks judul
            // arsip sudah ada di nama foldernya, jadi tidak perlu diduplikasi.
            $ext = $uploaded->getClientOriginalExtension();
            $base = pathinfo($uploaded->getClientOriginalName(), PATHINFO_FILENAME);
            $base = mb_substr(trim(preg_replace('/[\\\\\/\:\*\?\"\<\>\|]+/', '-', $base), " -") ?: 'file', 0, 120);

            // Hindari menimpa file yang sudah ada: tambahkan akhiran (2), (3), dst.
            $storage = Storage::disk($disk);
            $storedName = $base . ($ext !== '' ? '.' . $ext : '');
            $n = 2;
            while ($storage->exists($folderPath.'/'.$storedName)) {
                $storedName = $base.' ('.$n++.')'.($ext !== '' ? '.'.$ext : '');
            }

            $path = $uploaded->storeAs($folderPath, $storedName, $disk);

            ArchiveFile::create([
                'archive_id' => $archive->id,
                'original_name' => $uploaded->getClientOriginalName(),
                'stored_name' => $storedName,
                'file_path' => $path,
                'mime_type' => $uploaded->getClientMimeType(),
                'file_size' => $uploaded->getSize(),
                'uploaded_by' => Auth::id(),
            ]);

            $this->logActivity('upload', 'archives', "Upload file untuk arsip #{$archive->archive_number}");
        }
    }

    private function generateArchiveNumber(int $unitId): string
    {
        $year = date('Y');
        $count = Archive::withTrashed()->where('unit_id', $unitId)
            ->whereYear('created_at', $year)->count() + 1;
        return sprintf('ARS/%d/%03d/%d', $unitId, $count, $year);
    }
}
