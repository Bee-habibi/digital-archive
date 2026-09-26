@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card p-3">
                <div class="text-muted small">Total Arsip</div>
                <div class="fs-3 fw-bold">{{ $stats['total'] }}</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card p-3">
                <div class="text-muted small">Menunggu Verifikasi</div>
                <div class="fs-3 fw-bold text-warning">{{ $stats['menunggu_verifikasi'] }}</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card p-3">
                <div class="text-muted small">Terverifikasi</div>
                <div class="fs-3 fw-bold text-success">{{ $stats['terverifikasi'] }}</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card p-3">
                <div class="text-muted small">Perlu Perbaikan</div>
                <div class="fs-3 fw-bold text-danger">{{ $stats['perlu_perbaikan'] }}</div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <div class="card p-3">
                <h6>Arsip per Kategori</h6>
                <table class="table table-sm mb-0">
                    @foreach ($stats['per_kategori'] as $row)
                        <tr>
                            <td>{{ $row->category->name ?? '-' }}</td>
                            <td class="text-end">{{ $row->total }}</td>
                        </tr>
                    @endforeach
                </table>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card p-3">
                <h6>Arsip per Tahun</h6>
                <table class="table table-sm mb-0">
                    @foreach ($stats['per_tahun'] as $row)
                        <tr>
                            <td>{{ $row->year }}</td>
                            <td class="text-end">{{ $row->total }}</td>
                        </tr>
                    @endforeach
                </table>
            </div>
        </div>

        @if (isset($stats['per_unit']))
            <div class="col-md-6">
                <div class="card p-3">
                    <h6>Arsip per Unit Kerja</h6>
                    <table class="table table-sm mb-0">
                        @foreach ($stats['per_unit'] as $row)
                            <tr>
                                <td>{{ $row->unit->name ?? '-' }}</td>
                                <td class="text-end">{{ $row->total }}</td>
                            </tr>
                        @endforeach
                    </table>
                </div>
            </div>
        @endif

        @if (isset($stats['aktivitas_terbaru']))
            <div class="col-md-6">
                <div class="card p-3">
                    <h6>Aktivitas Terbaru</h6>
                    <ul class="list-unstyled mb-0 small">
                        @foreach ($stats['aktivitas_terbaru'] as $log)
                            <li class="border-bottom py-1">
                                <strong>{{ $log->user->name ?? 'system' }}</strong> - {{ $log->action }}
                                ({{ $log->module }})
                                <span class="text-muted float-end">{{ $log->created_at->diffForHumans() }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif
    </div>
@endsection
