@extends('layouts.app')
@section('title', 'Tambah Arsip')

@section('content')
<div class="card p-4" style="max-width:700px;">
    <form method="POST" action="{{ route('archives.store') }}" enctype="multipart/form-data">
        @csrf
        <div class="mb-3">
            <label class="form-label">Judul / Nama Dokumen</label>
            <input type="text" name="title" class="form-control" required>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Kategori</label>
                <select name="category_id" class="form-select" required>
                    <option value="">-- pilih --</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Jenis Dokumen</label>
                <select name="type_id" class="form-select" required>
                    <option value="">-- pilih kategori dulu --</option>
                </select>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Nomor Surat/Dokumen</label>
                <input type="text" name="document_number" class="form-control">
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">Tanggal Dokumen</label>
                <input type="date" name="document_date" class="form-control">
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">Tahun</label>
                <input type="number" name="year" class="form-control" value="{{ date('Y') }}" required>
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label">Keterangan</label>
            <textarea name="description" class="form-control" rows="3"></textarea>
        </div>
        <div class="mb-3">
            <label class="form-label">File Dokumen (PDF, DOC/DOCX, XLS/XLSX, JPG, PNG - maks 20MB)</label>
            <input type="file" name="files[]" class="form-control" multiple>
        </div>
        <button class="btn btn-primary">Simpan</button>
        <a href="{{ route('archives.index') }}" class="btn btn-outline-secondary">Batal</a>
    </form>
</div>

@push('scripts')
<script>
// Filter jenis dokumen sesuai kategori dipilih (data kategori beserta types-nya sudah di-inject dari server)
const categories = @json($categories);
document.querySelector('select[name=category_id]').addEventListener('change', function () {
    const typeSelect = document.querySelector('select[name=type_id]');
    const cat = categories.find(c => c.id == this.value);
    typeSelect.innerHTML = '<option value="">-- pilih --</option>';
    (cat?.types || []).forEach(t => {
        typeSelect.innerHTML += `<option value="${t.id}">${t.name}</option>`;
    });
});
</script>
@endpush
@endsection
