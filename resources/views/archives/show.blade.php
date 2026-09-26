@extends('layouts.app')
@section('title', 'Detail Arsip')

@section('content')
<div class="card p-4">
    <div class="d-flex justify-content-between">
        <h5>{{ $archive->title }}</h5>
        <span class="badge badge-status-{{ $archive->status }} align-self-start">{{ ucwords(str_replace('_',' ',$archive->status)) }}</span>
    </div>
    <table class="table table-borderless w-auto">
        <tr><th>No. Arsip</th><td>{{ $archive->archive_number }}</td></tr>
        <tr><th>No. Surat</th><td>{{ $archive->document_number ?? '-' }}</td></tr>
        <tr><th>Kategori / Jenis</th><td>{{ $archive->category->name }} / {{ $archive->type->name }}</td></tr>
        <tr><th>Unit Kerja</th><td>{{ $archive->unit->name }}</td></tr>
        <tr><th>Tahun</th><td>{{ $archive->year }}</td></tr>
        <tr><th>Diinput oleh</th><td>{{ $archive->creator->name }} - {{ $archive->created_at }}</td></tr>
        <tr><th>Keterangan</th><td>{{ $archive->description ?? '-' }}</td></tr>
    </table>

    <h6 class="mt-3">File Dokumen</h6>
    <ul class="list-group mb-3">
        @foreach($archive->files as $file)
            <li class="list-group-item d-flex justify-content-between">
                <span><i class="bi bi-file-earmark"></i> {{ $file->original_name }} ({{ $file->humanSize() }})</span>
                <a href="{{ route('archives.files.download', $file) }}" class="btn btn-sm btn-outline-primary">Download</a>
            </li>
        @endforeach
    </ul>

    @if($archive->verifications->isNotEmpty())
    <h6>Riwayat Verifikasi</h6>
    <ul class="list-group mb-3">
        @foreach($archive->verifications as $v)
            <li class="list-group-item">
                <strong>{{ $v->user->name }}</strong> - {{ ucwords(str_replace('_',' ',$v->status)) }}
                <div class="text-muted small">{{ $v->notes }}</div>
                <div class="text-muted small">{{ $v->created_at }}</div>
            </li>
        @endforeach
    </ul>
    @endif

    <div class="d-flex gap-2">
        @can('update', $archive)
            <a href="{{ route('archives.edit', $archive) }}" class="btn btn-outline-secondary">Edit</a>
        @endcan
        @can('delete', $archive)
            <form method="POST" action="{{ route('archives.destroy', $archive) }}" onsubmit="return confirm('Yakin hapus arsip ini?')">
                @csrf @method('DELETE')
                <button class="btn btn-outline-danger">Hapus</button>
            </form>
        @endcan
    </div>
</div>
@endsection
