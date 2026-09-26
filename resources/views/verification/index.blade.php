@extends('layouts.app')
@section('title', 'Verifikasi Arsip')

@section('content')
<div class="card">
    <div class="table-scroll">
    <table class="table mb-0 align-middle">
        <thead class="table-light">
            <tr><th>No. Arsip</th><th>Judul</th><th>Unit</th><th>Diinput</th><th>Aksi</th></tr>
        </thead>
        <tbody>
        @forelse($archives as $archive)
            <tr>
                <td>{{ $archive->archive_number }}</td>
                <td>{{ $archive->title }}</td>
                <td>{{ $archive->unit->name }}</td>
                <td>{{ $archive->creator->name }}</td>
                <td>
                    <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#verif-{{ $archive->id }}">Periksa</button>
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-center text-muted py-4">Tidak ada arsip menunggu verifikasi.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
</div>
<div class="mt-3">{{ $archives->links() }}</div>

@foreach($archives as $archive)
<div class="modal fade" id="verif-{{ $archive->id }}">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('verification.process', $archive) }}">
                @csrf
                <div class="modal-header"><h6 class="modal-title">Periksa Arsip</h6></div>
                <div class="modal-body">
                    <h6>{{ $archive->title }} ({{ $archive->archive_number }})</h6>
                    <div class="mb-2">
                        <label class="form-label">Keputusan</label>
                        <select name="decision" class="form-select" required>
                            <option value="terverifikasi">Terverifikasi</option>
                            <option value="perlu_perbaikan">Perlu Perbaikan</option>
                        </select>
                    </div>
                    <textarea name="notes" class="form-control" placeholder="Catatan (opsional)"></textarea>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach
@endsection
