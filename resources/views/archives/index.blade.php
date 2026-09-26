@extends('layouts.app')
@section('title', 'Data Arsip')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <form class="d-flex gap-2" method="GET">
        <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Cari nomor arsip / judul...">
        <select name="status" class="form-select">
            <option value="">Semua Status</option>
            @foreach(['draft','menunggu_verifikasi','terverifikasi','perlu_perbaikan','diarsipkan'] as $s)
                <option value="{{ $s }}" @selected(request('status')==$s)>{{ ucwords(str_replace('_',' ',$s)) }}</option>
            @endforeach
        </select>
        <button class="btn btn-outline-secondary"><i class="bi bi-search"></i></button>
    </form>
    <a href="{{ route('archives.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Tambah Arsip</a>
</div>

<div class="card">
    <div class="table-scroll">
    <table class="table mb-0 align-middle">
        <thead class="table-light">
            <tr>
                <th>No. Arsip</th><th>Judul</th><th>Kategori</th>
                @if(auth()->user()->canAccessAllUnits())<th>Unit</th>@endif
                <th>Tahun</th><th>Status</th><th></th>
            </tr>
        </thead>
        <tbody>
        @forelse($archives as $archive)
            <tr>
                <td>{{ $archive->archive_number }}</td>
                <td>{{ $archive->title }}</td>
                <td>{{ $archive->category->name }}</td>
                @if(auth()->user()->canAccessAllUnits())<td>{{ $archive->unit->name }}</td>@endif
                <td>{{ $archive->year }}</td>
                <td><span class="badge badge-status-{{ $archive->status }}">{{ ucwords(str_replace('_',' ',$archive->status)) }}</span></td>
                <td><a href="{{ route('archives.show', $archive) }}" class="btn btn-sm btn-outline-primary">Detail</a></td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-center text-muted py-4">Belum ada arsip.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
</div>
<div class="mt-3">{{ $archives->links() }}</div>
@endsection
