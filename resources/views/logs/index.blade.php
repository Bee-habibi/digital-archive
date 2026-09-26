@extends('layouts.app')
@section('title', 'Activity Log')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Activity Log</h5>
    @if(auth()->user()->isSuperAdmin())
        <div class="d-flex gap-2">
            <a href="{{ route('logs.download', 'csv') }}" class="btn btn-outline-secondary"><i class="bi bi-download"></i> CSV</a>
            <a href="{{ route('logs.download', 'xlsx') }}" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-excel"></i> Excel</a>
            <a href="{{ route('logs.download', 'json') }}" class="btn btn-outline-secondary"><i class="bi bi-filetype-json"></i> JSON</a>
        </div>
    @endif
</div>

<div class="card mb-3 p-3">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label small mb-1">Modul</label>
            <input type="text" name="module" value="{{ request('module') }}" class="form-control form-control-sm" placeholder="mis. archives">
        </div>
        <div class="col-md-3">
            <label class="form-label small mb-1">Aksi</label>
            <input type="text" name="action" value="{{ request('action') }}" class="form-control form-control-sm" placeholder="mis. create, update, download">
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-1">Dari Tanggal</label>
            <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control form-control-sm">
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-1">Sampai Tanggal</label>
            <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control form-control-sm">
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button class="btn btn-sm btn-primary"><i class="bi bi-funnel"></i> Filter</button>
            <a href="{{ route('logs.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
        </div>
    </form>
</div>

<div class="card">
    <div class="table-scroll">
    <table class="table mb-0 align-middle">
        <thead class="table-light">
            <tr>
                <th>Waktu</th>
                <th>User</th>
                <th>Aksi</th>
                <th>Modul</th>
                <th>Deskripsi</th>
                <th>IP Address</th>
            </tr>
        </thead>
        <tbody>
        @forelse($logs as $log)
            <tr>
                <td class="text-nowrap" title="{{ $log->created_at }}">{{ $log->created_at?->format('d M Y H:i') }}</td>
                <td>{{ $log->user?->name ?? 'system' }}</td>
                <td><span class="badge bg-secondary">{{ $log->action }}</span></td>
                <td>{{ $log->module }}</td>
                <td>{{ $log->description }}</td>
                <td><code class="small">{{ $log->ip_address }}</code></td>
            </tr>
        @empty
            <tr><td colspan="6" class="text-center text-muted py-4">Belum ada aktivitas tercatat.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
</div>
<div class="mt-3">{{ $logs->links() }}</div>
@endsection
