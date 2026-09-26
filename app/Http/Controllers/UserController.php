<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Traits\LogsActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserController extends Controller
{
    use LogsActivity;

    // Seluruh route di controller ini WAJIB dikunci middleware role:super_admin
    // (Super User tidak boleh membuat/mengubah Super Admin, Admin sama sekali tidak boleh akses modul ini)

    public function index()
    {
        $users = User::with(['role', 'unit'])->latest()->paginate(20);
        $roles = Role::all();
        $units = Unit::where('is_active', true)->with('parent')->orderBy('name')->get();

        return view('users.index', compact('users', 'roles', 'units'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role_id' => ['required', 'exists:roles,id'],
            'unit_id' => ['required', 'exists:units,id'],
        ]);

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role_id' => $data['role_id'],
            'unit_id' => $data['unit_id'],
            'is_active' => true,
        ]);

        $this->logActivity('create', 'users', "Menambah user {$data['email']}");

        return back()->with('success', 'User berhasil dibuat. Minta pengguna mengganti password setelah login pertama.');
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'role_id' => ['required', 'exists:roles,id'],
            'unit_id' => ['required', 'exists:units,id'],
            'is_active' => ['boolean'],
        ]);

        // Super User tidak boleh membuat/mengubah user jadi Super Admin -
        // guard tambahan ini seharusnya juga dicek via Policy jika Super User yang login.
        if (Auth::user()->isSuperUser()) {
            $targetRole = Role::find($data['role_id']);
            if ($targetRole && $targetRole->name === Role::SUPER_ADMIN) {
                abort(403, 'Super User tidak dapat menetapkan role Super Admin.');
            }
        }

        $user->update($data);
        $this->logActivity('update', 'users', "Mengubah data user {$user->email}");

        return back()->with('success', 'User berhasil diperbarui.');
    }

    public function resetPassword(Request $request, User $user)
    {
        $data = $request->validate(['password' => ['required', 'string', 'min:8']]);
        $user->update(['password' => Hash::make($data['password'])]);

        $this->logActivity('update', 'users', "Reset password user {$user->email}");

        return back()->with('success', 'Password berhasil direset.');
    }

    public function deactivate(User $user)
    {
        $user->update(['is_active' => false]);
        $this->logActivity('update', 'users', "Menonaktifkan user {$user->email}");

        return back()->with('success', 'User dinonaktifkan.');
    }

    /**
     * Hapus (soft delete) user lain yang role-nya DI BAWAH role penghapus.
     * - Super Admin boleh menghapus Super User & Admin.
     * - Tidak boleh menghapus diri sendiri (pakai nonaktifkan).
     * - User yang punya arsip TIDAK dihapus (dipertahankan jejak auditnya).
     */
    public function destroy(Request $request, User $user)
    {
        $actor = $request->user();

        if ($actor->id === $user->id) {
            return back()->with('error', 'Anda tidak dapat menghapus akun Anda sendiri.');
        }

        if (! $this->roleRank($actor->role?->name) || $this->roleRank($actor->role?->name) <= $this->roleRank($user->role?->name)) {
            abort(403, 'Anda hanya dapat menghapus user dengan role di bawah role Anda.');
        }

        if ($user->archives()->exists()) {
            return back()->with('error',
                "User {$user->email} masih memiliki {$user->archives()->count()} arsip. "
                .'Nonaktifkan saja, atau pindahkan arsipnya terlebih dahulu.');
        }

        $email = $user->email;
        $user->delete(); // soft delete

        $this->logActivity('delete', 'users', "Menghapus user {$email}");

        return back()->with('success', "User {$email} berhasil dihapus.");
    }

    /** Peringkat role untuk hierarki penghapusan (makin besar makin tinggi). */
    private function roleRank(?string $roleName): int
    {
        return match ($roleName) {
            Role::SUPER_ADMIN => 3,
            Role::SUPER_USER => 2,
            Role::ADMIN => 1,
            default => 0,
        };
    }
}
