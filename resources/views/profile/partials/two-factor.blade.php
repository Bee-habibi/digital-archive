<div class="card p-4" id="two-factor-settings">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h5 class="mb-0">Verifikasi Dua Langkah</h5>
        <span class="badge {{ $enabled ? 'bg-success' : 'bg-secondary' }}">
            {{ $enabled ? 'Aktif' : 'Belum aktif' }}
        </span>
    </div>

    <p class="text-muted small mb-3">
        Lapisan keamanan kedua: selain password, login meminta 6 digit kode dari aplikasi
        authenticator (Google Authenticator, Authy, dan sejenisnya).
    </p>

    @if($enabled)
        <p class="small mb-1">Sisa kode pemulihan: <strong>{{ $remaining }}</strong> dari 8</p>

        @if(session('success'))
            <div class="alert alert-success py-2 small">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger py-2 small">{{ $errors->first() }}</div>
        @endif

        <div class="d-flex flex-wrap gap-2">
            <form method="POST" action="{{ route('two-factor.recovery.regenerate') }}">
                @csrf
                <button class="btn btn-outline-primary btn-sm"
                    onclick="return confirm('Buat kode pemulihan baru? Semua kode lama hangus.')">
                    Regenerasi Kode Pemulihan
                </button>
            </form>

            <form method="POST" action="{{ route('two-factor.disable') }}" class="d-flex gap-2 align-items-start">
                @csrf
                <input type="text" name="code" class="form-control form-control-sm" style="max-width:140px"
                    placeholder="Kode authenticator" required>
                <button class="btn btn-outline-danger btn-sm"
                    onclick="return confirm('Nonaktifkan verifikasi dua langkah?')">
                    Nonaktifkan
                </button>
            </form>
        </div>
    @else
        <a href="{{ route('two-factor.setup') }}" class="btn btn-primary btn-sm">Aktifkan Sekarang</a>
    @endif
</div>
