<?php

namespace App\Livewire;

use App\Models\Archive;
use App\Models\ArchiveCategory;
use App\Models\Unit;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Tabel arsip dinamis (Langkah 2 PANDUAN-FRONTEND.md):
 * search + filter status/kategori/unit/tahun + pagination — semuanya tanpa reload.
 *
 * KEAMANAN: scope `visibleTo($user)` dipakai sama persis seperti
 * ArchiveController@index — admin biasa tetap terkunci ke unitnya sendiri
 * di level query, bukan cuma disembunyikan di tampilan.
 */
class ArsipTable extends Component
{
    use WithPagination;

    /** Pagination gaya Bootstrap (app ini bukan Tailwind-first). */
    protected string $paginationTheme = 'bootstrap';

    #[Url(history: true)]
    public string $q = '';

    #[Url(history: true)]
    public string $status = '';

    #[Url(history: true)]
    public string $kategori = '';

    #[Url(history: true)]
    public string $unit = '';

    #[Url(history: true)]
    public string $tahun = '';

    /** Jumlah baris per halaman. */
    public int $perPage = 15;

    public function updatedQ(): void        { $this->resetPage(); }
    public function updatedStatus(): void   { $this->resetPage(); }
    public function updatedKategori(): void { $this->resetPage(); }
    public function updatedUnit(): void     { $this->resetPage(); }
    public function updatedTahun(): void    { $this->resetPage(); }

    public function resetFilter(): void
    {
        $this->reset('q', 'status', 'kategori', 'unit', 'tahun');
    }

    public function render()
    {
        $user = Auth::user();

        $query = Archive::query()
            ->visibleTo($user)
            ->with(['unit', 'category', 'type', 'creator']);

        if ($this->q !== '') {
            $s = '%' . $this->q . '%';
            $query->where(function ($sub) use ($s) {
                $sub->where('archive_number', 'like', $s)
                    ->orWhere('document_number', 'like', $s)
                    ->orWhere('title', 'like', $s);
            });
        }

        if ($this->status !== '')   $query->where('status', $this->status);
        if ($this->kategori !== '') $query->where('category_id', $this->kategori);
        if ($this->tahun !== '')    $query->where('year', $this->tahun);
        if ($this->unit !== '' && $user->canAccessAllUnits()) {
            $query->where('unit_id', $this->unit);
        }

        $years = Archive::query()->visibleTo($user)
            ->selectRaw('DISTINCT year')
            ->orderByDesc('year')
            ->pluck('year');

        // Komponen biasa (bukan full-page): dirender di dalam archives/index
        // yang sudah pakai layouts.app (@yield('content'), bukan $slot).
        return view('livewire.arsip-table', [
            'archives'   => $query->latest()->paginate($this->perPage),
            'categories' => ArchiveCategory::where('is_active', true)->orderBy('name')->get(),
            'units'      => $user->canAccessAllUnits()
                ? Unit::where('is_active', true)->orderBy('name')->get(['id', 'name'])
                : collect(),
            'years'      => $years,
            'statuses'   => [
                Archive::STATUS_DRAFT                => 'Draft',
                Archive::STATUS_MENUNGGU_VERIFIKASI  => 'Menunggu Verifikasi',
                Archive::STATUS_TERVERIFIKASI        => 'Terverifikasi',
                Archive::STATUS_PERLU_PERBAIKAN      => 'Perlu Perbaikan',
                Archive::STATUS_DIARSIPKAN           => 'Diarsipkan',
            ],
        ]);
    }
}
