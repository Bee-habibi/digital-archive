<x-guest-layout>
    <x-slot:auth_title>Lupa Password</x-slot:auth_title>
    <x-slot:auth_sub>Masukkan email Anda dan kami akan mengirim link untuk membuat password baru.</x-slot:auth_sub>
    <x-slot:auth_top_right>
        <a href="{{ route('login') }}">Kembali ke halaman masuk</a>
    </x-slot:auth_top_right>

    @if (session('status'))
        <div class="auth-alert success">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="auth-form">
        @csrf

        <div class="field">
            <label class="field-label" for="email">Email</label>
            <input id="email" type="email" name="email" class="input @error('email') is-invalid @enderror" value="{{ old('email') }}" required autofocus placeholder="nama@instansi.go.id">
            @error('email')
                <div class="field-error">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit" class="btn btn--primary auth-submit" style="justify-content:center">Kirim Link Reset Password</button>
    </form>
</x-guest-layout>
