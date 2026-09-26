<x-guest-layout>
    <x-slot:auth_title>Masuk ke Akun</x-slot:auth_title>
    <x-slot:auth_sub>Selamat datang kembali. Masuk untuk melanjutkan ke Arsip Digital.</x-slot:auth_sub>
    <x-slot:auth_top_right></x-slot:auth_top_right>

    <form method="POST" action="{{ route('login') }}" class="auth-form">
        @csrf

        <div class="field">
            <label class="field-label" for="email">Email</label>
            <input id="email" type="email" name="email" class="input @error('email') is-invalid @enderror"
                value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="habibi@gmail.com">
            @error('email')
                <div class="field-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="field">
            <div class="field-row" style="width:100%">
                <label class="field-label" for="password">Password</label>
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}">Lupa password?</a>
                @endif
            </div>
            <input id="password" type="password" name="password" class="input @error('password') is-invalid @enderror"
                required autocomplete="current-password" placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;">
            @error('password')
                <div class="field-error">{{ $message }}</div>
            @enderror
        </div>

        <label class="check">
            <input type="checkbox" name="remember">
            <span class="box"></span>
            Ingat saya
        </label>

        <button type="submit" class="btn btn--primary auth-submit" style="justify-content:center">Masuk</button>
    </form>

    <div class="auth-divider">atau</div>
    <div class="auth-main-bottom" style="margin-top:0">
        Belum terdaftar? Hubungi Super Admin instansi untuk mendapatkan akun.
    </div>
</x-guest-layout>
