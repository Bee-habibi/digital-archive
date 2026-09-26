<x-guest-layout>
    <x-slot:auth_title>Kode Pemulihan</x-slot:auth_title>
    <x-slot:auth_sub>
        @if($codes)
            {{ $regenerated ? 'Kode pemulihan BARU Anda (yang lama hangus).' : 'Simpan kode ini SEKARANG — hanya ditampilkan sekali.' }}
        @endif
    </x-slot:auth_sub>

    @if($codes)
        <div class="auth-form">
            <div style="background:#fff8e1;border:1px solid #ffe082;border-radius:.5rem;padding:1rem;margin-bottom:1rem;font-size:.9rem">
                ⚠️ Setiap kode hanya bisa dipakai <strong>satu kali</strong>, sebagai pengganti kode
                authenticator saat HP hilang. Simpan di tempat aman (catatan/cetak).
            </div>

            <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:.5rem;font-family:monospace;font-size:1.05rem">
                @foreach($codes as $code)
                    <div style="background:#f8f9fa;border:1px solid #dee2e6;border-radius:.375rem;padding:.5rem;text-align:center">{{ $code }}</div>
                @endforeach
            </div>

            <button type="button" class="btn btn--primary auth-submit" style="justify-content:center;margin-top:1.25rem"
                onclick="window.location='{{ route('dashboard') }}'">
                Saya sudah menyimpan kode — Lanjutkan
            </button>
        </div>
    @else
        <div class="auth-form">
            <p style="font-size:.92rem">
                Halaman ini hanya menampilkan kode pemulihan pada saat kode dibuat.
                Untuk mendapatkan kode baru, buka <strong>Profil → Verifikasi Dua Langkah</strong>
                lalu pilih <em>Regenerasi kode pemulihan</em>.
            </p>
            <button type="button" class="btn btn--primary auth-submit" style="justify-content:center"
                onclick="window.location='{{ route('two-factor.profile') }}'">
                Ke Pengaturan 2FA
            </button>
        </div>
    @endif
</x-guest-layout>
