# Panduan Google Drive untuk Penyimpanan Arsip

Sejak integrasi ini, semua file arsip (upload, download, hapus) bisa disimpan di **Google Drive** sebagai pengganti disk server lokal. Tidak ada kode yang perlu diubah — cukup ganti konfigurasi di `.env`.

## Cara kerja singkat

- Disk yang dipakai ditentukan oleh `FILESYSTEM_ARCHIVE_DISK` di `.env` (`local` atau `google`).
- Saat `google` aktif, file tersimpan di struktur folder mengikuti unit kerja, contoh:

  ```
  Arsip Digital/                     <- folder root di Google Drive
  └── UPTD Tarakan/
      └── Kasi Pendataan/
          └── archives/
              └── 12/                <- per arsip
                  └── <uuid>.pdf
  ```

- Folder dibuat otomatis oleh adapter — tidak perlu bikin manual di Drive.
- Download dari aplikasi tetap mengalir lewat server (file tidak pernah publik), kecuali Anda memakai link viewer yang bisa diaktifkan secara opsional (lihat bawah).

## Langkah 1 — Buat project & OAuth Client di Google Cloud

1. Buka <https://console.cloud.google.com/> dan login dengan akun Google yang akan jadi **pemilik penyimpanan** (akun instansi lebih disarankan daripada akun pribadi).
2. Buat project baru, misalnya `arsip-digital` (nama bebas).
3. Buka menu **APIs & Services → Library**, cari **"Google Drive API"**, klik **Enable**.
4. Buka **APIs & Services → OAuth consent screen**:
   - User type: **Internal** (kalau punya Google Workspace instansi) atau **External**.
   - Isi nama aplikasi & email, scope cukup `.../auth/drive`.
   - Kalau External, tambahkan email Anda sendiri sebagai **Test user** (cukup untuk penggunaan internal).
5. Buka **APIs & Services → Credentials → Create credentials → OAuth client ID**:
   - Application type: **Web application**.
   - **Authorized redirect URIs**: tambahkan `https://developers.google.com/oauthplayground`
6. Simpan **Client ID** dan **Client Secret** yang muncul.

## Langkah 2 — Dapatkan Refresh Token

1. Buka <https://developers.google.com/oauthplayground>.
2. Klik ikon gerigi (OAuth 2.0 configuration) di kanan atas → centang **"Use your own OAuth credentials"** → isi Client ID & Client Secret dari Langkah 1.
3. Di daftar scope di kiri, masukkan (kolom "Input your own scopes"): `https://www.googleapis.com/auth/drive`
4. Klik **Authorize APIs** → pilih akun Google → setujui peringatan (kalau muncul "unverified app", klik *Continue*).
5. Kembali di Playground, klik **Exchange authorization code for tokens**.
6. Salin **refresh_token** yang muncul. Token ini yang dipakai aplikasi untuk akses jangka panjang — jangan sampai bocor.

> Refresh token ini tidak kedaluwarsa selama tidak dipakai ulang untuk grant yang sama terlalu sering dan aplikasi masih dalam mode testing (untuk External). Kalau suatu saat token ditolak (`invalid_grant`), ulangi Langkah 2.

## Langkah 3 — Isi `.env`

```env
FILESYSTEM_ARCHIVE_DISK=google
GOOGLE_DRIVE_CLIENT_ID=xxxx.apps.googleusercontent.com
GOOGLE_DRIVE_CLIENT_SECRET=GOCSPX-xxxx
GOOGLE_DRIVE_REFRESH_TOKEN=1//0gxxxxx

# (Opsional) Simpan di folder tertentu: ambil ID dari URL Drive
# https://drive.google.com/drive/folders/1AbCdEf...  ->  GOOGLE_DRIVE_FOLDER_ID=1AbCdEf...
GOOGLE_DRIVE_FOLDER_ID=
GOOGLE_DRIVE_FOLDER="Arsip Digital"
```

- Tanpa `GOOGLE_DRIVE_FOLDER_ID`: aplikasi memakai/membuat folder **"Arsip Digital"** di root *My Drive*.
- Dengan `GOOGLE_DRIVE_FOLDER_ID`: aplikasi menulis di folder tersebut (misalnya folder *Shared Drive* instansi — lebih rapi untuk pengelolaan).

## Langkah 4 — Tes koneksi

```bash
php artisan gdrive:test
```

Command ini mengupload file kecil ke Drive, membaca balik, lalu menghapusnya. Kalau semua centang ✔ muncul, storage siap. Cek juga di Google Drive: folder `Arsip Digital` akan muncul.

Setelah itu, **restart Apache** (atau `php artisan serve`) supaya `.env` baru terbaca, lalu upload arsip dari aplikasi seperti biasa — file akan muncul di Drive.

## Arsip lama yang masih di server lokal

File yang sudah terlanjur di `storage/app/private` tidak dipindah otomatis. Dua pilihan:

1. Biarkan tetap lokal (download tetap jalan karena path-nya sudah tercatat di database per-file — tapi perhatikan: `downloadFile` memakai disk aktif dari config, jadi kalau disk diganti, file lama lokal tidak akan ketemu), atau
2. Minta saya buatkan command migrasi `gdrive:migrate` untuk memindahkan file lokal ke Drive satu per satu dan meng-update path di database.

## Catatan keamanan

- Kredensial di `.env` setara akses penuh ke Drive akun tersebut — jangan di-commit ke git.
- Aplikasi **tidak** membuat file publik; semua akses tetap melewati otorisasi login + policy per unit.
- Aktifitas upload/download/hapus tetap tercatat di activity log seperti sebelumnya.
