<section>
    <h5 class="mb-1">Informasi Profile</h5>
    <p class="text-muted small">Perbarui nama dan alamat email akun Anda.</p>

    @if (session('status') === 'profile-updated')
        <div class="alert alert-success py-2 small">Perubahan berhasil disimpan.</div>
    @endif

    <form method="post" action="{{ route('profile.update') }}">
        @csrf
        @method('patch')

        <div class="mb-3">
            <label for="name" class="form-label">Nama</label>
            <input id="name" name="name" type="text" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $user->name) }}" required autofocus autocomplete="name">
            @error('name')
                <div class="text-danger small mt-1">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input id="email" name="email" type="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $user->email) }}" required autocomplete="username">
            @error('email')
                <div class="text-danger small mt-1">{{ $message }}</div>
            @enderror

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div class="small mt-2">
                    Email Anda belum terverifikasi.

                    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
                        @csrf
                    </form>

                    <button type="submit" form="send-verification" class="btn btn-link btn-sm p-0 align-baseline">
                        Kirim ulang email verifikasi
                    </button>

                    @if (session('status') === 'verification-link-sent')
                        <div class="text-success small mt-1">Link verifikasi baru telah dikirim ke email Anda.</div>
                    @endif
                </div>
            @endif
        </div>

        <button type="submit" class="btn btn-primary">Simpan</button>
    </form>
</section>
