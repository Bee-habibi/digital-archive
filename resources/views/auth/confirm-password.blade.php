<x-guest-layout>
    <x-slot:auth_title>Konfirmasi Password</x-slot:auth_title>
    <x-slot:auth_sub>Ini area aman aplikasi. Masukkan password Anda untuk melanjutkan.</x-slot:auth_sub>
    <x-slot:auth_top_right></x-slot:auth_top_right>

    <form method="POST" action="{{ route('password.confirm') }}" class="auth-form">
        @csrf

        <div class="field">
            <label class="field-label" for="password">Password</label>
            <input id="password" type="password" name="password" class="input @error('password') is-invalid @enderror" required autocomplete="current-password" placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;">
            @error('password')
                <div class="field-error">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit" class="btn btn--primary auth-submit" style="justify-content:center">Konfirmasi</button>
    </form>
</x-guest-layout>
