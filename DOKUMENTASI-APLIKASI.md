# Laporan Dokumentasi Aplikasi
## Sistem Informasi Arsip Digital Instansi

**Versi dokumen:** 1.6 · **Tanggal:** 24 September 2026
**Teknologi:** Laravel 13 (PHP 8.5) · MariaDB 10.4 · Apache 2.4 · Bootstrap/Adminator

---

## Daftar Isi

1. [Pendahuluan](#1-pendahuluan)
2. [Penjelasan Umum Aplikasi](#2-penjelasan-umum-aplikasi)
3. [Arsitektur & Teknologi](#3-arsitektur--teknologi)
4. [Struktur Pengguna (Klasifikasi User)](#4-struktur-pengguna-klasifikasi-user)
5. [Struktur Database (Klasifikasi Database)](#5-struktur-database-klasifikasi-database)
6. [Flowchart Proses Bisnis](#6-flowchart-proses-bisnis)
7. [Matriks Hak Akses (Role Permission Matrix)](#7-matriks-hak-akses)
8. [Keamanan Aplikasi](#8-keamanan-aplikasi)
9. [Penyimpanan File (Lokal & Google Drive)](#9-penyimpanan-file)
10. [Panduan Operasional Singkat](#10-panduan-operasional-singkat)
11. [Keterbatasan & Rencana Pengembangan](#11-keterbatasan--rencana-pengembangan)

---

## 1. Pendahuluan

### 1.1 Latar Belakang
Pengelolaan dokumen/arsip dinas secara manual (berkas fisik + folder komputer berkas-berkas) menimbulkan beberapa masalah: dokumen sulit dilacak, tidak ada riwayat siapa mengunggah/memverifikasi, risiko dokumen unit kerja lain terbaca oleh pihak yang tidak berwenang, dan tidak ada rekam jejak (audit trail) yang dapat dipertanggungjawabkan.

Aplikasi **Arsip Digital** dibangun untuk menjawab masalah tersebut: satu sistem terpusat berbasis web di mana setiap unit kerja mengelola arsipnya sendiri, dengan alur verifikasi berjenjang dan pencatatan aktivitas otomatis.

### 1.2 Tujuan
- Menyediakan penyimpanan arsip digital yang terpusat, aman, dan tertata per unit kerja.
- Menjamin **isolasi data antar unit kerja** — admin unit A tidak dapat mengakses arsip unit B meskipun mengubah ID di URL.
- Menerapkan alur verifikasi arsip sebelum diarsipkan (accountability).
- Merekam seluruh aktivitas pengguna sebagai bahan audit.

### 1.3 Ruang Lingkup
Modul yang tersedia: Dashboard, Data Arsip (CRUD + file lampiran), Verifikasi Arsip, Master Data (Unit Kerja, Kategori, Jenis Arsip), Manajemen Pengguna, dan Activity Log (dengan ekspor).

---

## 2. Penjelasan Umum Aplikasi

### 2.1 Gambaran Singkat
Aplikasi berbasis web yang berjalan di server lokal instansi (XAMPP: Apache + MariaDB + PHP). Pengguna mengakses melalui browser di alamat `http://localhost/digital-archive` (pengembangan) atau alamat server instansi (produksi).

Inti aplikasi adalah **arsip digital** yang memiliki:
- **Metadata** — nomor arsip (dibuat otomatis), nomor dokumen, judul, kategori, jenis, tahun, tanggal dokumen, deskripsi, status, dan unit kerja pemilik.
- **File lampiran** — berkas PDF/Word/Excel/gambar (maks. 20 MB per file, tervalidasi MIME) yang tersimpan di disk server **atau** Google Drive.
- **Status verifikasi** — arsip baru selalu berstatus *menunggu_verifikasi* sampai disetujui Super User/Super Admin.

### 2.2 Fitur Utama

| Modul | Fitur |
|---|---|
| **Dashboard** | Statistik arsip (total, menunggu verifikasi, terverifikasi, perlu perbaikan), rekap per kategori/tahun/unit, aktivitas terbaru (Super Admin) |
| **Data Arsip** | Tambah/ubah/hapus arsip, upload multi-file, pencarian (nomor/judul/nomor dokumen), filter kategori/jenis/status/tahun/unit/rentang tanggal, unduh file lampiran |
| **Verifikasi** | Daftar arsip menunggu verifikasi; setujui (*terverifikasi*) atau tolak (*perlu_perbaikan*) dengan catatan; riwayat verifikasi tersimpan |
| **Master Unit Kerja** | Struktur organisasi 2 tingkat (Unit Induk: Bapenda & UPTD → Sub Bagian/Seksi/Bidang) dengan induk-anak |
| **Master Kategori & Jenis** | Kategori arsip beserta jenisnya (contoh: Surat Menyurat → Surat Masuk/Surat Keluar/Nota Dinas) |
| **Manajemen Pengguna** | Buat/ubah user, atur role & unit, reset password, nonaktifkan user, hapus user dengan role di bawahnya (khusus Super Admin; user pemilik arsip tidak dapat dihapus demi jejak audit) |
| **Activity Log** | Catatan otomatis seluruh aktivitas (login, create, update, delete, upload, download, verify) dengan IP & user agent; filter; ekspor CSV/Excel/JSON (Super Admin) |
| **Profil** | Ubah profil & password sendiri, hapus akun (soft delete) |

### 2.3 Nomor Arsip Otomatis
Setiap arsip mendapat nomor unik format `ARS/{unit_id}/{urutan}/{tahun}`, contoh: `ARS/2/015/2026`. Nomor dihitung per unit per tahun sehingga tidak bertabrakan antar unit.

---

## 3. Arsitektur & Teknologi

### 3.1 Arsitektur
Aplikasi mengikuti pola **MVC (Model–View–Controller)** dengan pola tambahan:
- **Policy** (`ArchivePolicy`) — otorisasi per-object (apakah user boleh melihat/mengubah arsip *ini*).
- **Middleware** (`EnsureUserHasRole`) — pembatasan rute per role.
- **Trait** (`LogsActivity`) — pencatatan aktivitas seragam di semua controller.
- **Eloquent Scope** (`Archive::visibleTo()`) — penyaringan data per unit di level query database.

```
┌─────────────┐   HTTP    ┌──────────────────────────────────────────┐
│   Browser   │──────────▶│ Apache 2.4 (XAMPP)                       │
└─────────────┘           │  └─ PHP 8.5 (mod_php) ─ Laravel 13       │
                          │      ├─ Routing + Middleware (auth/role) │
                          │      ├─ Controller ── Policy             │
                          │      ├─ Model (Eloquent)                 │
                          │      └─ Blade View (Bootstrap/Adminator) │
                          └───────┬─────────────────────┬────────────┘
                                  │                     │
                          ┌───────▼────────┐    ┌───────▼─────────────┐
                          │ MariaDB 10.4   │    │ File Storage        │
                          │ (database)     │    │ local / Google Drive│
                          └────────────────┘    └─────────────────────┘
```

### 3.2 Komponen Teknis

| Komponen | Versi | Peran |
|---|---|---|
| PHP | 8.5.10 | Bahasa server (XAMPP) |
| Laravel Framework | 13.x | Kerangka aplikasi (routing, ORM, auth, validation) |
| Laravel Breeze | 13.x | Modul autentikasi (login, reset password, profil) |
| MariaDB | 10.4.32 | Database |
| Bootstrap + Adminator | — | Tampilan antarmuka |
| Flysystem Google Drive Adapter | masbug v2.5.0 | Integrasi penyimpanan Google Drive (opsional) |
| PHPUnit | 11.x | Pengujian otomatis (39 test, 125 assertion) |

### 3.3 Peta Modul Kode (app/)

| Path | Isi |
|---|---|
| `Http/Controllers/` | Logika tiap halaman (Archive, User, Unit, Verification, Dashboard, ActivityLog, Kategori/Jenis, Profile) |
| `Http/Middleware/EnsureUserHasRole.php` | Pembatas rute berdasarkan role |
| `Models/` | 11 model Eloquent (User, Role, Unit, Archive, ArchiveFile, dll.) |
| `Policies/ArchivePolicy.php` | Aturan hak akses per arsip |
| `Traits/LogsActivity.php` | Pencatat aktivitas otomatis |
| `Console/Commands/GoogleDrive*.php` | Diagnostik & pemetaan Google Drive (tes koneksi, setup folder, migrasi & restrukturasi file) |

---

## 4. Struktur Pengguna (Klasifikasi User)

### 4.1 Tiga Role Pengguna

| Role | Kode | Deskripsi |
|---|---|---|
| **Super Admin** | `super_admin` | Pengelola sistem. Akses penuh: semua arsip semua unit, master data, manajemen user, seluruh log, ekspor log |
| **Super User** | `super_user` | Penguji/verifikator lintas unit. Melihat semua arsip semua unit, memverifikasi, melihat log modul arsip. **Tidak** dapat mengelola user/master data |
| **Admin** | `admin` | Operator unit kerja. Input & kelola arsip **unitnya sendiri saja** |

### 4.2 Struktur Organisasi & Unit Kerja
Unit kerja tersusun 2 tingkat (tabel `units`, kolom `parent_id` & `level`): Bapenda dan 5 UPTD adalah root setara (level 1), semua sub unit berada di level 2:

```
Level 1  Bapenda Provinsi Kalimantan Utara   ← root
Level 2  ├── Sub Bagian Umum
         ├── Bidang Perencanaan
         ├── Bidang Pengelolaan
         └── Bidang Evaluasi
Level 1  UPTD Bapenda Bulungan   ← root setara Bapenda
Level 2  ├── Sub Bagian Umum
         ├── Seksi Penagihan
         └── Seksi Pendataan
Level 1  UPTD Bapenda Tarakan / Nunukan / Malinau / Tana Tidung (pola sama)
```

- **Pendaftaran mandiri dinonaktifkan** (sejak v1.6): tidak ada link/form Daftar di halaman login dan rute `/register` sudah dihapus. Akun **hanya dibuat oleh Super Admin** melalui menu **Users** — saat membuat akun, Super Admin memilih unit pengguna bertingkat: dulu **Unit Induk (Bapenda/UPTD)**, lalu **Sub Bagian/Seksi/Bidang**-nya.
- Setiap **Admin** terikat pada satu unit kerja; semua arsip milik unit itu.
- Super Admin/Super User tidak terikat unit (lintas unit).

### 4.3 Aturan Isolasi Data
Aturan paling penting dalam sistem: **Admin hanya melihat arsip unitnya sendiri**. Ini ditegakkan di tiga lapis:
1. **Query database** — semua pengambilan daftar arsip lewat scope `visibleTo()` yang menambahkan `WHERE unit_id = ...` (bukan sekadar disembunyikan di tampilan).
2. **Policy per objek** — akses ke satu arsip spesifik dicek `ArchivePolicy` (menguji skenario "ubah ID di URL" dan terbukti menolak dengan HTTP 403).
3. **Middleware role** — rute sensitif (users, units, log) diblokir di level routing.

---

## 5. Struktur Database (Klasifikasi Database)

Database: **`digital_archive`** (MariaDB) — 19 tabel.

### 5.1 Klasifikasi Tabel Berdasarkan Fungsi

**A. Tabel Inti Bisnis (data arsip)**

| Tabel | Fungsi | Kolom Kunci |
|---|---|---|
| `archives` | Data utama arsip | `archive_number` (unik), `unit_id`, `status` (enum: draft, menunggu_verifikasi, terverifikasi, perlu_perbaikan, diarsipkan), `created_by/updated_by/verified_by`, soft delete |
| `archive_files` | File lampiran arsip | `archive_id`, `original_name`, `file_path`, `mime_type`, `file_size`, `uploaded_by` |
| `archive_verifications` | Riwayat verifikasi | `archive_id`, `user_id`, `status` (terverifikasi/perlu_perbaikan), `notes` |

**B. Tabel Master (referensi)**

| Tabel | Fungsi | Isi |
|---|---|---|
| `units` | Unit kerja hierarkis | `parent_id`, `level` (1–3), `name`, `code`, `is_active` |
| `archive_categories` | Kategori arsip | `name`, `code` (SUR/KEU/KEP/ASET), `is_active` |
| `archive_types` | Jenis arsip per kategori | `category_id`, `name` |
| `roles` | Role pengguna | super_admin, super_user, admin |

**C. Tabel Pengguna & Otentikasi**

| Tabel | Fungsi |
|---|---|
| `users` | Akun pengguna: `name`, `email` (unik), `password` (bcrypt), `role_id`, `unit_id`, `is_active`, soft delete |
| `password_reset_tokens` | Token reset password |
| `sessions` | Sesi login (driver database) |

**D. Tabel Audit/Log**

| Tabel | Fungsi |
|---|---|
| `activity_logs` | Jejak audit: `user_id`, `action` (login/create/update/delete/upload/download/verify), `module`, `description`, `ip_address`, `user_agent`, `created_at` |
| `system_logs` | Log teknis sistem: `level` (info/warning/error/critical), `message`, `context` (JSON) |

**E. Tabel Konfigurasi & Infrastruktur Laravel**

| Tabel | Fungsi |
|---|---|
| `system_settings` | Konfigurasi aplikasi (key–value) |
| `migrations` | Riwayat migrasi skema |
| `cache`, `cache_locks` | Cache aplikasi |
| `jobs`, `job_batches`, `failed_jobs` | Antrean pekerjaan latar belakang |

### 5.2 Relasi Antar Tabel

```
roles 1───* users *───1 units ─┐(parent_id ke units, hierarki 3 level)
              │                 │
              │ 1               │ 1
              │                 │
              ▼ *               ▼ *
          archives *───1 archive_categories
              │  \____*───1 archive_types
              │ 1
              ├──* archive_files
              └──* archive_verifications *───1 users (verifikator)

          activity_logs *───1 users (nullable)
```

Relasi penting:
- `users.unit_id` → `units.id` — setiap user milik satu unit.
- `archives.unit_id` → `units.id` — **kunci isolasi data**.
- `archive_types.category_id` → `archive_categories.id` — jenis terikat kategori.
- `archive_files.archive_id` → `archives.id` (cascade delete).
- Semua aksi penting merekam `created_by` / `updated_by` / `verified_by` → `users.id`.

### 5.3 Indeks & Performa
- `archives`: indeks gabungan `(unit_id, status)` dan `(unit_id, year)` — mendukung filter paling sering dipakai sekaligus isolasi data.
- `activity_logs`: indeks `(module, action)` dan `created_at` — mendukung filter log & retensi.

---

## 6. Flowchart Proses Bisnis

### 6.1 Flowchart Alur Arsip (proses utama)

```
                ┌──────────────┐
                │     MULAI    │
                └──────┬───────┘
                       ▼
        ┌──────────────────────────────┐
        │ Admin unit login             │
        └──────────────┬───────────────┘
                       ▼
        ┌──────────────────────────────┐
        │ Isi metadata arsip + upload  │
        │ file (PDF/Word/Excel/Gambar, │
        │ maks 20MB/file)              │
        └──────────────┬───────────────┘
                       ▼
        ┌──────────────────────────────┐
        │ Sistem validasi & beri nomor │
        │ arsip otomatis               │
        │ ARS/{unit}/{urut}/{tahun}    │
        └──────────────┬───────────────┘
                       ▼
              ┌─────────────────┐
              │ STATUS:         │
              │ menunggu_       │
              │ verifikasi      │
              └────────┬────────┘
                       ▼
        ┌──────────────────────────────┐
        │ Super User / Super Admin     │
        │ membuka menu Verifikasi      │
        └──────────────┬───────────────┘
                       ▼
                ╱═══════════════╲
               ╱  Keputusan       ╲        ┌──────────────────────┐
              ▏   verifikasi?      ▚──────▶│ TERVERIFIKASI        │
               ╲                  ╱        │ (arsip sah, terkunci │
                ╲═══════════════╱         │  dari edit admin)    │
                       │                  └──────────┬───────────┘
                       │ ditolak                     ▼
                       ▼                      (kelengkapan arsip)
        ┌──────────────────────────────┐
        │ STATUS: perlu_perbaikan      │
        │ + catatan perbaikan          │
        └──────────────┬───────────────┘
                       ▼
        ┌──────────────────────────────┐
        │ Admin memperbaiki arsip      │
        │ → status kembali ke          │
        │   menunggu_verifikasi        │
        └──────────────┬───────────────┘
                       └────────────▶ (kembali ke proses verifikasi)
```

### 6.2 Flowchart Otorisasi Akses (setiap permintaan)

```
        ┌───────────────────────┐
        │ Permintaan HTTP masuk │
        └───────────┬───────────┘
                    ▼
        ┌───────────────────────┐   Tidak   ┌────────────┐
        │ Sudah login? (auth)   ├──────────▶│ Redirect   │
        └───────────┬───────────┘           │ ke /login  │
                    │ Ya                    └────────────┘
                    ▼
        ┌───────────────────────┐  Tidak    ┌────────────┐
        │ Role sesuai rute?     ├──────────▶│ 403 Ditolak│
        │ (middleware role)     │           └────────────┘
        └───────────┬───────────┘
                    │ Ya
                    ▼
        ┌───────────────────────┐  Tidak    ┌────────────┐
        │ Unit sesuai? (policy) ├──────────▶│ 403 Ditolak│
        │ visibleTo / authorize │           └────────────┘
        └───────────┬───────────┘
                    │ Ya
                    ▼
        ┌───────────────────────┐
        │ Proses + catat log    │
        └───────────────────────┘
```

### 6.3 Diagram Status Arsip

```
   [draft]                (dibuat via form; sistem langsung set)
      │
      ▼
[menunggu_verifikasi] ◀──────────────────────┐
      │                 (admin memperbaiki)  │
      ├──── setujui ────▶ [terverifikasi]    │
      │                        │             │
      └──── tolak ────▶ [perlu_perbaikan] ───┘
                               │
              [terverifikasi] ─┴────▶ [diarsipkan]
                                     (final; tidak bisa dihapus admin)
```

### 6.4 Flowchart Penyimpanan File

```
   Upload file arsip
          │
          ▼
  Validasi: tipe (pdf/doc/docx/xls/xlsx/jpg/jpeg/png),
  ukuran maks 20MB
          │
          ▼
  Baca FILESYSTEM_ARCHIVE_DISK (.env)
          │
   ┌──────┴───────┐
   ▼              ▼
 local          google
   │              │
   ▼              ▼
storage/app/    Google Drive
private/...     folder: Arsip Digital/
 struktur:      {Unit}/archives/{id - Judul}/
 {Unit}/        (folder dibuat otomatis
 archives/{id-Judul}/ mengikuti unit)
   │              │
   └──────┬───────┘
          ▼
  Metadata file disimpan
  di tabel archive_files
  (nama asli, path, mime, ukuran)
          │
          ▼
  Aktivitas dicatat di activity_logs
```

---

## 7. Matriks Hak Akses

| Fitur / Menu | Super Admin | Super User | Admin (unit sendiri) |
|---|:---:|:---:|:---:|
| Dashboard statistik | ✔ semua unit | ✔ semua unit | ✔ unit sendiri |
| Lihat daftar arsip | ✔ semua | ✔ semua | ✔ unit sendiri |
| Tambah arsip | ✔ (pilih unit) | ✖ | ✔ (unit sendiri) |
| Edit arsip | ✔ semua | ✖ | ✔ hanya status draft/perlu_perbaikan |
| Hapus arsip | ✔ semua | ✖ | ✔ kecuali status diarsipkan (file & folder di disk/Drive ikut dihapus) |
| Unduh file lampiran | ✔ semua | ✔ semua | ✔ unit sendiri |
| Verifikasi arsip | ✔ | ✔ | ✖ |
| Activity Log | ✔ semua modul | ✔ hanya modul arsip | ✖ |
| Ekspor Activity Log | ✔ | ✖ | ✖ |
| Master Unit/Kategori/Jenis | ✔ | ✖ | ✖ |
| Manajemen Pengguna | ✔ | ✖* | ✖ |
| Reset password user | ✔ | ✖ | ✖ |
| Hapus user (role di bawahnya) | ✔ | ✖ | ✖ |

\* Super User secara sengaja tidak diberi akses manajemen user; sistem juga memblokir Super User menetapkan role Super Admin.

**Safeguards tambahan:**
- `unit_id` arsip **tidak pernah** diambil dari input Admin — selalu dipaksa dari akun yang login.
- Deaktivasi user (`is_active = false`) langsung mencabut akses login.
- Penghapusan user & arsip bersifat **soft delete** (data tidak hilang fisik).

---

## 8. Keamanan Aplikasi

Ringkasan hasil audit keamanan (detail lengkap: `SECURITY-AUDIT.md`):

| Aspek | Status | Keterangan |
|---|---|---|
| Autentikasi | ✔ | Laravel Breeze, password bcrypt, throttling login (blokir brute-force); pendaftaran mandiri (registrasi) dinonaktifkan — akun hanya dibuat Super Admin |
| MFA / Verifikasi Dua Langkah | ✔ | TOTP (RFC 6238, kompatibel Google Authenticator/Authy) wajib bagi semua user; kode pemulihan sekali pakai; anti-replay (kode bekas ditolak); secret terenkripsi di database; reset 2FA oleh Super Admin dari menu Users |
| Akun nonaktif | ✔ | User yang dinonaktifkan Super Admin langsung ditolak saat login (perbaikan hasil audit) |
| Otorisasi | ✔ | Middleware role + Policy per objek; uji IDOR (ubah ID URL) terbukti ditolak 403 |
| Isolasi data unit | ✔ | Ditegakkan di query database (`visibleTo`), bukan di tampilan |
| CSRF | ✔ | Token pada semua form POST |
| SQL Injection | ✔ | Eloquent ORM + parameter binding |
| XSS | ✔ | Escaping otomatis Blade |
| Upload file | ✔ | Validasi tipe (MIME whitelist) & ukuran (20 MB); nama file di-random (UUID) |
| Audit trail | ✔ | Activity log dengan IP & user agent, tidak dapat dihapus dari UI |
| Catatan | ⚠ | Untuk produksi: MySQL sebaiknya tidak lagi tanpa password root, `APP_DEBUG=false`, HTTPS diaktifkan |

---

## 9. Penyimpanan File

### 9.1 Lokal (default)
File tersimpan di server: `storage/app/private/{Unit}/archives/{id - Judul}/{nama asli}.ext` — folder memuat judul arsip, nama file asli dipertahankan; file dengan nama sama otomatis diberi akhiran (2), (3), dst.

### 9.2 Google Drive (aktif saat ini)
Mengubah satu variabel `FILESYSTEM_ARCHIVE_DISK=google` memindahkan seluruh penyimpanan arsip ke Google Drive tanpa perubahan kode. Struktur folder mengikuti hierarki unit kerja dan dipetakan lewat `php artisan gdrive:setup-folders`:

```
Arsip Digital/
├── Bapenda Provinsi Kalimantan Utara/          (level 1)
│   ├── Sub Bagian Umum/archives/{id - Judul}/
│   ├── Bidang Perencanaan/archives/{id - Judul}/
│   ├── Bidang Pengelolaan/archives/{id - Judul}/
│   └── Bidang Evaluasi/archives/{id - Judul}/
├── UPTD Bapenda Bulungan/                      (level 1, setara Bapenda)
│   ├── Sub Bagian Umum/archives/{id - Judul}/
│   ├── Seksi Penagihan/archives/{id - Judul}/
│   └── Seksi Pendataan/archives/{id - Judul}/
└── UPTD Bapenda Tarakan / Nunukan / Malinau / Tana Tidung (pola sama)
```

Perintah pendukung pemetaan:

| Perintah | Fungsi |
|---|---|
| `php artisan gdrive:setup-folders` | Buat/petakan semua folder unit di Drive (idempoten) & simpan ID-nya ke tabel units |
| `php artisan gdrive:restructure-files` | Pindahkan file lama ke struktur folder terbaru bila nama unit berubah |
| `php artisan gdrive:cleanup-folders` | Rapikan folder unit lama setelah perubahan struktur (pindah ke "Lama (Struktur Tua)" / hapus folder kosong) |
| `php artisan gdrive:test` | Tes koneksi Drive (upload → baca → hapus) |

---

## 10. Panduan Operasional Singkat

### 10.1 Alur kerja harian
1. **Admin unit** login → menu Data Arsip → tambah arsip (metadata + file) → status otomatis *menunggu verifikasi*.
2. **Super User/Super Admin** login → menu Verifikasi → setujui atau minta perbaikan (dengan catatan).
3. Admin memperbaiki (jika diminta) → arsip kembali ke antrean verifikasi → setujui → **terverifikasi**.
4. Arsip terverifikasi siap dirujuk/diunduh kapan pun; seluruh aktivitas terekam di Activity Log.

### 10.2 Urutan pembekalan sistem pertama kali (Super Admin)
1. Buat struktur unit kerja (Master Unit) mengikuti organisasi: Unit Induk (Bapenda/UPTD) → Sub Bagian/Seksi/Bidang.
2. Isi Kategori & Jenis Arsip sesuai klasifikasi arsip instansi.
3. Buat akun untuk tiap Admin unit (role *admin* + unit masing-masing) dan akun verifikator (role *super_user*) dari menu **Users** — ini satu-satunya jalur pembuatan akun karena pendaftaran mandiri dinonaktifkan.
4. Minta setiap pengguna mengganti password setelah login pertama.
5. Saat login pertama, sistem otomatis meminta pengguna mengaktifkan **Verifikasi Dua Langkah**: pindai QR dengan aplikasi authenticator (Google Authenticator/Authy), lalu simpan 8 kode pemulihan yang ditampilkan sekali.
6. Pantau Activity Log secara berkala; unduh arsip log (CSV/Excel/JSON) untuk dokumentasi.

### 10.3 Perintah teknis yang berguna

| Perintah | Fungsi |
|---|---|
| `php artisan migrate --seed` | Setup database awal |
| `php artisan gdrive:test` | Tes koneksi Google Drive |
| `php artisan test` | Jalankan pengujian otomatis (43 test) |
| `php artisan serve` | Menjalankan server pengembangan |

---

## 11. Keterbatasan & Rencana Pengembangan

| Keterbatasan Saat Ini | Usulan Pengembangan |
|---|---|
| Status `diarsipkan` belum memiliki aksi khusus di UI | Tombol "Arsipkan" pada arsip terverifikasi |
| Pencarian belum mencari isi file (hanya metadata) | Pencarian full-text isi dokumen PDF |
| Belum ada retensi/jadwal penyusutan arsip | Modul retensi sesuai JRA (Jadwal Retensi Arsip) |
| Ekspor terbatas pada Activity Log | Ekspor/rekap arsip ke Excel/PDF |
| Belum ada notifikasi otomatis | Email/notifikasi saat arsip menunggu verifikasi |
| Backup manual | Backup database otomatis terjadwal |

---

*Dokumen ini dihasilkan dari pemeriksaan langsung kode sumber aplikasi (controller, model, policy, middleware, migrasi, seeder, dan rute) per tanggal tercantum di atas.*
