@extends('layouts.app')
@section('title', 'User Management')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Daftar Pengguna</h5>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tambahUser"><i class="bi bi-plus-lg"></i> Tambah User</button>
</div>

<div class="card">
    <div class="table-scroll">
    <table class="table mb-0 align-middle">
        <thead class="table-light">
            <tr><th>Nama</th><th>Email</th><th>Role</th><th>Unit</th><th>Login Terakhir</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
        @forelse($users as $user)
            <tr>
                <td>{{ $user->name }}</td>
                <td>{{ $user->email }}</td>
                <td><span class="badge bg-secondary">{{ $user->role->name ?? '-' }}</span></td>
                <td>{{ $user->unit->name ?? '-' }}</td>
                <td>{{ $user->last_login_at?->diffForHumans() ?? 'Belum pernah' }}</td>
                <td>
                    @if($user->is_active)<span class="badge bg-success">Aktif</span>
                    @else<span class="badge bg-secondary">Nonaktif</span>@endif
                </td>
                <td>
                    <div class="d-flex gap-1">
                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#edit-{{ $user->id }}">Edit</button>
                        @if($user->two_factor_confirmed_at)
                        <form method="POST" action="{{ route('two-factor.admin-reset', $user) }}"
                            onsubmit="return confirm('Reset 2FA user ini? User akan diminta setup ulang saat login berikutnya.')">
                            @csrf
                            <button class="btn btn-sm btn-outline-warning" title="Reset 2FA (mis. HP hilang)">Reset 2FA</button>
                        </form>
                        @endif
                        @if($user->is_active)
                        <form method="POST" action="{{ route('users.deactivate', $user) }}" onsubmit="return confirm('Nonaktifkan user ini?')">
                            @csrf
                            <button class="btn btn-sm btn-outline-danger">Nonaktifkan</button>
                        </form>
                        @endif
                        @php
                            $actorRank = match(auth()->user()->role?->name) { \App\Models\Role::SUPER_ADMIN => 3, \App\Models\Role::SUPER_USER => 2, \App\Models\Role::ADMIN => 1, default => 0 };
                            $targetRank = match($user->role?->name) { \App\Models\Role::SUPER_ADMIN => 3, \App\Models\Role::SUPER_USER => 2, \App\Models\Role::ADMIN => 1, default => 0 };
                        @endphp
                        @if(auth()->id() !== $user->id && $actorRank > $targetRank)
                        <form method="POST" action="{{ route('users.destroy', $user) }}" onsubmit="return confirm('Hapus permanen user {{ $user->email }}?\n\nCatatan: user yang masih memiliki arsip tidak dapat dihapus.')">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger" title="Hapus user (soft delete)">Hapus</button>
                        </form>
                        @endif
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-center text-muted py-4">Belum ada user.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
</div>
<div class="mt-3">{{ $users->links() }}</div>

@foreach($users as $user)
<div class="modal fade" id="edit-{{ $user->id }}">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('users.update', $user) }}">
                @csrf @method('PUT')
                <div class="modal-header"><h6 class="modal-title">Edit {{ $user->name }}</h6></div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label">Nama</label>
                        <input type="text" name="name" class="form-control" value="{{ $user->name }}" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Role</label>
                        <select name="role_id" class="form-select" required>
                            @foreach($roles as $role)
                                <option value="{{ $role->id }}" @selected($user->role_id == $role->id)>{{ $role->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Unit Kerja</label>
                        <select name="unit_id" class="form-select" required>
                            @php
                                $unitsByRoot = $units->sortBy('name')->groupBy(fn ($u) => $u->level == 1 ? $u->name : ($u->parent?->name ?? 'Lainnya'));
                            @endphp
                            @foreach($unitsByRoot as $rootName => $group)
                                <optgroup label="{{ $rootName }}">
                                    @foreach($group as $unit)
                                        <option value="{{ $unit->id }}" @selected($user->unit_id == $unit->id)>{{ $unit->name }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-check mb-2">
                        <input type="checkbox" name="is_active" value="1" id="is_active_{{ $user->id }}" class="form-check-input" @checked($user->is_active)>
                        <label class="form-check-label" for="is_active_{{ $user->id }}">Aktif</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary">Simpan</button>
                </div>
            </form>
            <form method="POST" action="{{ route('users.reset-password', $user) }}" class="p-3 border-top">
                @csrf
                <label class="form-label small">Reset Password</label>
                <div class="input-group">
                    <input type="password" name="password" class="form-control" placeholder="Password baru (min 8 karakter)" required>
                    <button class="btn btn-outline-warning">Reset</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach

<div class="modal fade" id="tambahUser">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('users.store') }}">
                @csrf
                <div class="modal-header"><h6 class="modal-title">Tambah User</h6></div>
                <div class="modal-body">
                    <div class="mb-2"><label class="form-label">Nama</label><input type="text" name="name" class="form-control" required></div>
                    <div class="mb-2"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
                    <div class="mb-2"><label class="form-label">Password Awal</label><input type="password" name="password" class="form-control" required></div>
                    <div class="mb-2">
                        <label class="form-label">Role</label>
                        <select name="role_id" class="form-select" required>
                            @foreach($roles as $role)<option value="{{ $role->id }}">{{ $role->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Unit Kerja</label>
                        <select name="unit_id" class="form-select" required>
                            @php
                                $unitsByRoot = $units->sortBy('name')->groupBy(fn ($u) => $u->level == 1 ? $u->name : ($u->parent?->name ?? 'Lainnya'));
                            @endphp
                            @foreach($unitsByRoot as $rootName => $group)
                                <optgroup label="{{ $rootName }}">
                                    @foreach($group as $unit)
                                        <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer"><button class="btn btn-primary">Simpan</button></div>
            </form>
        </div>
    </div>
</div>
@endsection
