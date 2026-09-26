@extends('layouts.app')
@section('title', 'Edit Arsip')

@section('content')
<div class="card p-4" style="max-width:700px;">
    <form method="POST" action="{{ route('archives.update', $archive) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        <div class="mb-3">
            <label class="form-label">Judul / Nama Dokumen</label>
            <input type="text" name="title" class="form-control" value="{{ old('title', $archive->title) }}" required>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Kategori</label>
                <select name="category_id" id="category_id" class="form-select" required>
                    <option value="">-- pilih --</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" @selected($archive->category_id == $cat->id)>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Jenis Dokumen</label>
                <select name="type_id" id="type_id" class="form-select" required>
                    @foreach($categories as $cat)
                        @if($cat->id == $archive->category_id)
                            @foreach($cat->types as $type)
                                <option value="{{ $type->id }}" @selected($archive->type_id == $type->id)>{{ $type->name }}</option>
                            @endforeach
                        @endif
                    @endforeach
                </select>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Nomor Surat/Dokumen</label>
                <input type="text" name="document_number" class="form-control" value="{{ old('document_number', $archive->document_number) }}">
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">Tanggal Dokumen</label>
                <input type="date" name="document_date" class="form-control" value="{{ old('document_date', $archive->document_date?->format('Y-m-d')) }}">
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">Tahun</label>
                <input type="number" name="year" class="form-control" value="{{ old('year', $archive->year) }}" required>
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label">Keterangan</label>
            <textarea name="description" class="form-control" rows="3">{{ old('description', $archive->description) }}</textarea>
        </div>

        @if($archive->files->isNotEmpty())
        <div class="mb-3">
            <label class="form-label">File Saat Ini</label>
            <ul class="list-group">
                @foreach($archive->files as $file)
                    <li class="list-group-item small">
                        <i class="bi bi-file-earmark"></i> {{ $file->original_name }} ({{ $file->humanSize() }})
                    </li>
                @endforeach
            </ul>
        </div>
        @endif

        <div class="mb-3">
            <label class="form-label">Tambah File Baru (opsional - file lama tidak akan terhapus)</label>
            <input type="file" name="files[]" class="form-control" multiple>
        </div>

        <button class="btn btn-primary">Simpan Perubahan</button>
        <a href="{{ route('archives.show', $archive) }}" class="btn btn-outline-secondary">Batal</a>
    </form>
</div>

@push('scripts')
<script>
const categories = @json($categories);
document.getElementById('category_id').addEventListener('change', function () {
    const typeSelect = document.getElementById('type_id');
    const cat = categories.find(c => c.id == this.value);
    typeSelect.innerHTML = '<option value="">-- pilih --</option>';
    (cat?.types || []).forEach(t => {
        typeSelect.innerHTML += `<option value="${t.id}">${t.name}</option>`;
    });
});
</script>
@endpush
@endsection
