<x-guest-layout>
    <x-slot:auth_title>Atur Password Baru</x-slot:auth_title>
    <x-slot:auth_sub>Buat password baru untuk akun Anda.</x-slot:auth_sub>
    <x-slot:auth_top_right>
        <a href="{{ route('login') }}">Kembali ke halaman masuk</a>
    </x-slot:auth_top_right>

    <form method="POST" action="{{ route('password.store') }}" class="auth-form">
        @csrf

        {{-- Password Reset Token --}}
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div class="field">
            <label class="field-label" for="email">Email</label>
            <input id="email" type="email" name="email" class="input @error('email') is-invalid @enderror" value="{{ old('email', $request->email) }}" required autofocus autocomplete="username">
            @error('email')
                <div class="field-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="field">
            <label class="field-label" for="password">Password Baru</label>
            <input id="password" type="password" name="password" class="input @error('password') is-invalid @enderror" required autocomplete="new-password" placeholder="Min. 8 karakter">
            @error('password')
                <div class="field-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="field">
            <label class="field-label" for="password_confirmation">Konfirmasi Password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" class="input @error('password') is-invalid @enderror" required autocomplete="new-password" placeholder="Ulangi password">
        </div>

        <button type="submit" class="btn btn--primary auth-submit" style="justify-content:center">Simpan Password Baru</button>
    </form>
</x-guest-layout>
