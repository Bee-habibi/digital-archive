<x-guest-layout>
    <x-slot:auth_title>Aktifkan Verifikasi Dua Langkah</x-slot:auth_title>
    <x-slot:auth_sub>Satu langkah lagi untuk mengamankan akun Anda.</x-slot:auth_sub>

    <div class="auth-form">
        <ol class="setup-steps" style="padding-left:1.2rem;font-size:.92rem;line-height:1.7">
            <li>Install aplikasi authenticator: <strong>Google Authenticator</strong>, Authy, atau Microsoft Authenticator.</li>
            <li>Pindai QR di bawah ini, atau masukkan kunci secara manual.</li>
            <li>Masukkan 6 digit kode yang muncul di aplikasi untuk konfirmasi.</li>
        </ol>

        <div style="text-align:center;margin:1rem 0">
            <div style="display:inline-block;background:#fff;padding:.75rem;border-radius:.5rem;border:1px solid #dee2e6">
                {!! $qrSvg !!}
            </div>
        </div>

        <div class="field">
            <label class="field-label" for="secret">Kunci manual (jika tidak bisa memindai)</label>
            <input id="secret" type="text" class="input" value="{{ $secret }}" readonly
                onclick="this.select()" style="font-family:monospace;letter-spacing:1px">
        </div>

        <form method="POST" action="{{ route('two-factor.confirm') }}">
            @csrf
            <div class="field">
                <label class="field-label" for="code">Kode konfirmasi</label>
                <input id="code" type="text" name="code" class="input @error('code') is-invalid @enderror"
                    inputmode="numeric" autocomplete="one-time-code" required autofocus placeholder="123456">
                @error('code')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <button type="submit" class="btn btn--primary auth-submit" style="justify-content:center">Konfirmasi &amp; Aktifkan</button>
        </form>
    </div>

    <div class="auth-main-bottom" style="margin-top:1rem">
        Setelah aktif, setiap login akan meminta kode dari aplikasi ini.
        Simpan baik-baik kode pemulihan yang akan diberikan setelah langkah ini.
    </div>
</x-guest-layout>
