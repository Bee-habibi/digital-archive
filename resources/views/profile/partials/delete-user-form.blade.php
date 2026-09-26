<section>
    <h5 class="mb-1 text-danger">Hapus Akun</h5>
    <p class="text-muted small">
        Jika akun dihapus, semua data terkait akan hilang permanen. Sebelum menghapus,
        unduh terlebih dahulu data/informasi yang ingin disimpan.
    </p>

    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#confirm-user-deletion">
        Hapus Akun
    </button>
</section>

<div class="modal fade" id="confirm-user-deletion" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="{{ route('profile.destroy') }}">
                @csrf
                @method('delete')
                <div class="modal-header">
                    <h6 class="modal-title">Yakin ingin menghapus akun?</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted">
                        Setelah akun dihapus, semua resource dan datanya akan dihapus permanen.
                        Masukkan password Anda untuk mengonfirmasi.
                    </p>
                    <label for="password" class="form-label">Password</label>
                    <input id="password" name="password" type="password" class="form-control @error('password') is-invalid @enderror" autocomplete="current-password" autofocus>
                    @error('password')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">Hapus Akun</button>
                </div>
            </form>
        </div>
    </div>
</div>
