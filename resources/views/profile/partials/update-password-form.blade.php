<section>
    <h5 class="mb-1">Ganti Password</h5>
    <p class="text-muted small">Pastikan akun memakai password yang panjang dan acak agar tetap aman.</p>

    @if (session('status') === 'password-updated')
        <div class="alert alert-success py-2 small">Password berhasil diperbarui.</div>
    @endif

    <form method="post" action="{{ route('password.update') }}">
        @csrf
        @method('put')

        <div class="mb-3">
            <label for="update_password_current_password" class="form-label">Password Saat Ini</label>
            <input id="update_password_current_password" name="current_password" type="password" class="form-control @error('current_password') is-invalid @enderror" autocomplete="current-password">
            @error('current_password')
                <div class="text-danger small mt-1">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="update_password_password" class="form-label">Password Baru</label>
            <input id="update_password_password" name="password" type="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password">
            @error('password')
                <div class="text-danger small mt-1">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="update_password_password_confirmation" class="form-label">Konfirmasi Password Baru</label>
            <input id="update_password_password_confirmation" name="password_confirmation" type="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password">
        </div>

        <button type="submit" class="btn btn-primary">Simpan</button>
    </form>
</section>
