<div>
<div class="card">
    {{-- Filter bar: semua wire:model.live — berubah langsung tanpa reload --}}
    <div class="d-flex flex-wrap gap-2 mb-3 livewire-filters">
        <input type="search" class="form-control flex-grow-1" style="min-width:220px"
               placeholder="Cari no. arsip / no. dokumen / judul…"
               wire:model.live.debounce.350ms="q">

        <select class="form-select" style="max-width:190px" wire:model.live="status">
            <option value="">Semua Status</option>
            @foreach($statuses as $val => $label)
                <option value="{{ $val }}" @selected($status === $val)>{{ $label }}</option>
            @endforeach
        </select>

        <select class="form-select" style="max-width:190px" wire:model.live="kategori">
            <option value="">Semua Kategori</option>
            @foreach($categories as $c)
                <option value="{{ $c->id }}">{{ $c->name }}</option>
            @endforeach
        </select>

        @if(auth()->user()->canAccessAllUnits())
            <select class="form-select" style="max-width:220px" wire:model.live="unit">
                <option value="">Semua Unit</option>
                @foreach($units as $u)
                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                @endforeach
            </select>
        @endif

        <select class="form-select" style="max-width:120px" wire:model.live="tahun">
            <option value="">Tahun</option>
            @foreach($years as $y)
                <option value="{{ $y }}">{{ $y }}</option>
            @endforeach
        </select>

        <div class="d-flex gap-2 ms-auto filter-actions">
            <button type="button" class="btn btn-outline-secondary" wire:click="resetFilter"
                    title="Bersihkan semua filter">↺</button>

            <a href="{{ route('archives.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg"></i> Tambah Arsip
            </a>
        </div>
    </div>

    {{-- Indikator loading: muncul saat Livewire sedang refresh --}}
    <div wire:loading.delay class="small text-muted mb-2">
        <span class="spinner-border spinner-border-sm"></span> Memuat…
    </div>

    {{-- Di layar sempit (≤767px) tabel ini berubah jadi kartu via CSS (layout/app.blade.php);
         atribut data-label dipakai sebagai label tiap baris kartu. --}}
    <div class="table-scroll">
        <table class="table table-card mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>No. Arsip</th><th>Judul</th><th>Kategori</th>
                    @if(auth()->user()->canAccessAllUnits())<th>Unit</th>@endif
                    <th>Tahun</th><th>Status</th><th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($archives as $archive)
                <tr wire:key="arsip-{{ $archive->id }}">
                    <td class="text-nowrap" data-label="No. Arsip">{{ $archive->archive_number }}</td>
                    <td data-label="Judul">{{ $archive->title }}</td>
                    <td data-label="Kategori">{{ $archive->category?->name }}</td>
                    @if(auth()->user()->canAccessAllUnits())<td data-label="Unit">{{ $archive->unit?->name }}</td>@endif
                    <td data-label="Tahun">{{ $archive->year }}</td>
                    <td data-label="Status"><span class="badge badge-status-{{ $archive->status }}">{{ ucwords(str_replace('_',' ',$archive->status)) }}</span></td>
                    <td class="td-actions"><a href="{{ route('archives.show', $archive) }}" class="btn btn-sm btn-outline-primary">Detail</a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">
                    @if($q || $status || $kategori || $unit || $tahun)
                        Tidak ada arsip yang cocok dengan filter.
                    @else
                        Belum ada arsip.
                    @endif
                </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span class="small text-muted">Total: {{ $archives->total() }} arsip</span>
    {{ $archives->links() }}
</div>
</div>
