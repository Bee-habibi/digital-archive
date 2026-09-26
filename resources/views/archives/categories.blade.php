@extends('layouts.app')
@section('title', 'Kategori Arsip')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Kategori & Jenis Dokumen</h5>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#tambahJenis">Tambah Jenis Dokumen</button>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tambahKategori">Tambah Kategori</button>
    </div>
</div>

<div class="row g-3">
@forelse($categories as $cat)
    <div class="col-md-6">
        <div class="card p-3">
            <div class="d-flex justify-content-between">
                <h6>{{ $cat->name }} <code class="small">{{ $cat->code }}</code></h6>
                <span class="text-muted small">{{ $cat->archives_count }} arsip</span>
            </div>
            <ul class="list-unstyled mb-0 small">
                @forelse($cat->types as $type)
                    <li class="border-bottom py-1"><i class="bi bi-file-earmark-text"></i> {{ $type->name }}</li>
                @empty
                    <li class="text-muted">Belum ada jenis dokumen.</li>
                @endforelse
            </ul>
        </div>
    </div>
@empty
    <p class="text-muted">Belum ada kategori.</p>
@endforelse
</div>

<div class="modal fade" id="tambahKategori">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('archive-categories.store') }}">
                @csrf
                <div class="modal-header"><h6 class="modal-title">Tambah Kategori</h6></div>
                <div class="modal-body">
                    <div class="mb-2"><label class="form-label">Nama Kategori</label><input type="text" name="name" class="form-control" required></div>
                    <div class="mb-2"><label class="form-label">Kode (unik)</label><input type="text" name="code" class="form-control" required></div>
                    <div class="mb-2"><label class="form-label">Deskripsi</label><textarea name="description" class="form-control"></textarea></div>
                </div>
                <div class="modal-footer"><button class="btn btn-primary">Simpan</button></div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="tambahJenis">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('archive-types.store') }}">
                @csrf
                <div class="modal-header"><h6 class="modal-title">Tambah Jenis Dokumen</h6></div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label">Kategori</label>
                        <select name="category_id" class="form-select" required>
                            @foreach($categories as $cat)<option value="{{ $cat->id }}">{{ $cat->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="mb-2"><label class="form-label">Nama Jenis Dokumen</label><input type="text" name="name" class="form-control" required></div>
                    <div class="mb-2"><label class="form-label">Deskripsi</label><textarea name="description" class="form-control"></textarea></div>
                </div>
                <div class="modal-footer"><button class="btn btn-primary">Simpan</button></div>
            </form>
        </div>
    </div>
</div>
@endsection
