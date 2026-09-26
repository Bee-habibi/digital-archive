<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        // Klasifikasi organisasi bertingkat untuk dropdown pendaftaran:
        // unit induk (Bapenda + 5 UPTD) dan sub unitnya (Sub Bagian/Seksi/Bidang).
        $roots = Unit::where('level', 1)->where('is_active', true)->orderBy('name')->get();
        $children = Unit::where('level', 2)->where('is_active', true)->orderBy('name')->get();

        return view('auth.register', compact('roots', 'children'));
    }

    /**
     * Handle an incoming registration request.
     *
     * Pendaftar memilih unit induknya (Bapenda atau UPTD), lalu sub unit di
     * bawahnya (Sub Bagian Umum / Seksi / Bidang). Role otomatis "admin"
     * (data tetap terisolasi per unit; verifikasi tetap lewat Super User/Admin).
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'parent_unit' => ['required', 'integer', Rule::exists('units', 'id')->where('level', 1)->where('is_active', true)],
            // Wajib dipilih bila pendaftar berada di sub unit (Sub Bagian/Seksi/Bidang).
            'child_unit' => ['nullable', 'integer', Rule::exists('units', 'id')->where('level', 2)->where('is_active', true)],
        ]);

        $parent = Unit::findOrFail($request->input('parent_unit'));
        $child = $request->filled('child_unit') ? Unit::findOrFail($request->input('child_unit')) : null;

        // Sub unit hanya sah kalau memang anak dari unit induk yang dipilih.
        if ($child && (int) $child->parent_id !== (int) $parent->id) {
            throw ValidationException::withMessages([
                'child_unit' => 'Sub unit yang dipilih tidak berada di bawah unit induk tersebut.',
            ]);
        }

        $unitId = $child?->id ?? $parent->id;
        $adminRole = Role::where('name', Role::ADMIN)->firstOrFail();

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role_id' => $adminRole->id,
            'unit_id' => $unitId,
            'is_active' => true,
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
