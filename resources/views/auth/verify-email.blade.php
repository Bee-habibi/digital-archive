<x-guest-layout>
    <x-slot:auth_title>Verifikasi Email</x-slot:auth_title>
    <x-slot:auth_sub>Terima kasih sudah mendaftar! Klik link yang kami kirim ke email Anda untuk mengaktifkan akun.</x-slot:auth_sub>
    <x-slot:auth_top_right></x-slot:auth_top_right>

    @if (session('status') == 'verification-link-sent')
        <div class="auth-alert success">Link verifikasi baru telah dikirim ke email yang Anda daftarkan.</div>
    @endif

    <div class="auth-form">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit" class="btn btn--primary auth-submit">Kirim Ulang Email Verifikasi</button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn--ghost">Keluar</button>
        </form>
    </div>

    <div class="auth-divider">butuh bantuan?</div>
    <div class="auth-main-bottom" style="margin-top:0">
        Email belum sampai? Periksa folder spam atau kirim ulang melalui tombol di atas.
    </div>
</x-guest-layout>
