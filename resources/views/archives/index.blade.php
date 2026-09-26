@extends('layouts.app')
@section('title', 'Data Arsip')

@section('content')
{{-- Langkah 2 PANDUAN-FRONTEND.md: tabel arsip dinamis via Livewire.
     Komponen ArsipTable menangani search, filter (status/kategori/unit/tahun),
     dan pagination — semuanya tanpa reload halaman. --}}
<livewire:arsip-table />
@endsection
