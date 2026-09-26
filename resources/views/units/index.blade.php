@extends('layouts.app')
@section('title', 'Unit Kerja')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Daftar Unit Kerja</h5>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tambahUnit"><i class="bi bi-plus-lg"></i> Tambah Unit</button>
</div>

<div class="card">
    <div class="table-scroll">
    <table class="table mb-0 align-middle">
        <thead class="table-light">
            <tr><th>Nama</th><th>Kode</th><th>Level</th><th>Induk</th><th>Jml Arsip</th><th>Jml User</th><th>Status</th></tr>
        </thead>
        <tbody>
        @forelse($units as $unit)
            <tr>
                <td>{{ $unit->name }}</td>
                <td><code>{{ $unit->code }}</code></td>
                <td>{{ $unit->level }}</td>
                <td>{{ $unit->parent->name ?? '-' }}</td>
                <td>{{ $unit->archives_count }}</td>
                <td>{{ $unit->users_count }}</td>
                <td>
                    @if($unit->is_active)
                        <span class="badge bg-success">Aktif</span>
                    @else
                        <span class="badge bg-secondary">Nonaktif</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-center text-muted py-4">Belum ada unit kerja.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
</div>

<div class="modal fade" id="tambahUnit">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('units.store') }}">
                @csrf
                <div class="modal-header"><h6 class="modal-title">Tambah Unit Kerja</h6></div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label">Nama Unit</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Kode Unit (unik, huruf kapital, tanpa spasi)</label>
                        <input type="text" name="code" class="form-control" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Unit Induk (opsional, kosongkan jika level Badan)</label>
                        <select name="parent_id" class="form-select">
                            <option value="">-- tidak ada (level Badan) --</option>
                            @foreach($units as $u)
                                <option value="{{ $u->id }}">{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Level</label>
                        <select name="level" class="form-select" required>
                            <option value="1">1 - Badan</option>
                            <option value="2">2 - Bidang / UPTD</option>
                            <option value="3">3 - Kasi / Seksi</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Deskripsi</label>
                        <textarea name="description" class="form-control"></textarea>
                    </div>
                </div>
                <div class="modal-footer"><button class="btn btn-primary">Simpan</button></div>
            </form>
        </div>
    </div>
</div>
@endsection
