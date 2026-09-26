<x-guest-layout>
    <x-slot:auth_title>Verifikasi Dua Langkah</x-slot:auth_title>
    <x-slot:auth_sub>Masukkan 6 digit kode dari aplikasi authenticator Anda.</x-slot:auth_sub>

    <form method="POST" action="{{ route('two-factor.verify') }}" class="auth-form">
        @csrf

        <div class="field">
            <label class="field-label" for="code">Kode verifikasi</label>
            <input id="code" type="text" name="code" class="input @error('code') is-invalid @enderror"
                inputmode="numeric" autocomplete="one-time-code" required autofocus
                placeholder="123456 atau kode pemulihan">
            @error('code')
                <div class="field-error">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit" class="btn btn--primary auth-submit" style="justify-content:center">Verifikasi</button>
    </form>

    <div class="auth-divider">atau</div>
    <div class="auth-main-bottom" style="margin-top:0">
        Hilang akses ke aplikasi authenticator? Gunakan salah satu
        <strong>kode pemulihan</strong> Anda (format <code>xxxx-xxxx</code>).
        Kehabisan semuanya? Hubungi Super Admin untuk reset 2FA.
    </div>
</x-guest-layout>
