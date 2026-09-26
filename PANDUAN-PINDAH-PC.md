# 🖥️ Panduan Pindah PC (Rumah ⇄ Kantor) — Arsip Digital Bapenda Kaltara

> Ditulis oleh Buffy (Codebuff), 26 Sep 2026.
> Menjawab: *"biar gak terlalu banyak setup ulang kalau pindah PC, pakai Docker atau apa?"*

---

## 1. Jawaban dulu: Docker atau bukan?

| | **Opsi A: XAMPP + Git (RECOMMENDED)** | **Opsi B: Docker** |
|---|---|---|
| Setup awal di PC baru | ± 30 menit, sekali | 1–2 jam (install Docker Desktop + WSL2) |
| Butuh izin IT kantor | Tidak perlu (installer biasa) | Sering perlu (Docker Desktop butuh virtualisasi & admin) |
| Komputer kantor jadul/lop | ✅ jalan di spek rendah | ❌ WSL2 butuh Windows 10/11 64-bit + RAM 8GB+ |
| Pindah kode | `git pull` — 1 menit | `git pull` juga |
| Pindah **data database** | 1 file `.sql` (cara di bab 4) | volume bisa dicopy, tapi ribet di Windows |
| Risiko error aneh | Versi PHP bisa beda tipis (pakai 8.4 semua, aman) | Konsisten 100% |
| Cocok untuk | App internal 1 instansi, tim kecil, shared hosting nanti | Tim besar, banyak developer, staging/produksi seragam |

**Kesimpulan gue:** untuk kasus lu (app internal instansi, PC hanya 2, XAMPP udah jalan matang di rumah), **XAMPP + Git** itu pilihan paling rasional. Docker bagus kalau tim lu nanti udah 5+ orang atau udah deploy ke server Linux — catat untuk nanti, jangan sekarang.

> 💡 Ada juga jalan ketiga: **Laravel Herd** (setup PHP super cepat di Windows) — tapi dia tidak bawa MySQL/Apache, jadi masih perlu install DB terpisah. Untuk konsistensi dengan rumah, tetap XAMPP.

---

## 2. Fondasi: simpan proyek di Git (WAJIB sebelum apapun)

Saat ini folder `C:\xampp\htdocs\digital-archive` **belum repo Git** — berarti satu-satunya salinan kode ada di PC rumah. Ini risiko lebih besar daripada soal Docker: kalau disk rusak, selesai.

### 2a. Siapkan repo remote (pilih satu)

- **GitHub/GitLab privat (gratis)** — paling gampang. Kalau kode instansi nggak boleh ke cloud publik, tetap pakai repo **private**.
- **Server kantor / NAS + Git bare repo** — kalau kebijakan instansi melarang cloud.

### 2b. Inisialisasi repo (jalankan sekali di PC rumah)

```bash
cd C:\xampp\htdocs\digital-archive
git init
```

Buat file `.gitignore` (biasanya sudah ada dari skeleton Laravel — cek dulu; kalau belum, buat):

```gitignore
/vendor
/node_modules
/public/build
/public/hot
/public/storage
/storage/*.key
/storage/pail
/.env
/.env.backup
/.phpunit.result.cache
npm-debug.log
yarn-error.log
```

> Yang **tidak ikut repo** (sengaja): `.env` (berisi config mesin), `vendor/`, `node_modules/`, `public/build/`. Semua itu bisa dibangun ulang dari `composer.lock` & `package-lock.json` — makanya **kedua lock file WAJIB ikut repo**.

Lalu commit & push:

```bash
git add .
git commit -m "Initial commit: app arsip digital + template Adminator + panduan"
git branch -M main
git remote add origin https://github.com/USERNAME/digital-archive.git
git push -u origin main
```

### 2c. Simpan `.env` di tempat aman (terpisah)

`.env` tidak masuk repo, tapi lu butuh isinya di PC kantor. Pilihannya:

1. **(Paling gampang)** Simpan salinan `.env.example` yang sudah dilengkapi di repo, TANPA nilai rahasia (app ini nggak punya secret khusus selain APP_KEY):

   ```bash
   # di PC rumah
   cp .env .env.template
   # edit .env.template: kosongkan APP_KEY= (biar diisi ulang di PC baru)
   git add .env.template && git commit -m "add env template" && git push
   ```

2. **APP_KEY dipindah manual** — salin nilai `APP_KEY` dari `.env` rumah ke kantor (simpan di password manager / catatan aman). Kalau APP_KEY beda, **data terenkripsi lama (termasuk two_factor_secret) tidak bisa didekripsi** di PC baru! Ini penting banget buat app lu yang pakai MFA.

---

## 3. Setup PC kantor (sekali, ± 30 menit)

Checklist urut:

```text
[ ] 1. Install XAMPP (versi PHP 8.4.x — sama dengan rumah)
[ ] 2. Ekstrak PHP 8.4 bila installer XAMPP kantor masih 8.1
       (salin folder C:\xampp\php dari rumah + httpd-xampp.conf bisa,
        atau ikuti cara upgrade yang kemarin kita lakukan)
[ ] 3. git clone https://github.com/USERNAME/digital-archive.git
       → taruh di C:\xampp\htdocs\digital-archive
[ ] 4. cd digital-archive
       composer install
       npm install
       npm run build
[ ] 5. cp .env.template .env
       php artisan key:generate      # isi APP_KEY baru...
       # ...lalu TIMPA dengan APP_KEY dari PC rumah (bab 2c) — PENTING untuk MFA
[ ] 6. Import database (bab 4 di bawah)
[ ] 7. Salin konfigurasi Apache dari rumah:
       - bagian Alias /digital-archive di C:\xampp\apache\conf\extra\httpd-xampp.conf
       - public/.htaccess dengan [END] (sudah di repo, otomatis ikut)
[ ] 8. php artisan storage:link
[ ] 9. Start Apache + MySQL dari XAMPP Control Panel
[ ] 10. Buka http://localhost/digital-archive → login biasa
```

> Catatan: `composer` & `git` & `npm` harus terinstall di PC kantor.
> Composer: `https://getcomposer.org/download/` (installer Windows).
> Git: `https://git-scm.com/download/win`.
> Node LTS: `https://nodejs.org` (atau pakai versi sama dengan rumah).

**Kalau PC kantor tak ada internet** — di rumah jalankan:

```bash
composer install --no-dev --prefer-dist   # vendor lengkap
```

lalu **pin `vendor/` ke dalam folder yang dicopy** (flashdisk/zip) ke kantor; jangan pakai repo (besar). Sama untuk `node_modules` tidak perlu — cukup hasil `npm run build` (folder `public/build`).

---

## 4. Sinkronisasi DATABASE antar PC (ini yang sering bikin bingung)

Kode bisa lewat Git, tapi data di MySQL tidak. Aturannya:

> **Satu PC jadi "master" saat itu juga.** Sebelum pindah kerja, export; setelah sampai, import. Jangan edit dua PC dalam kondisi data beda — pilih yang terbaru.

### 4a. Export (di PC yang datanya paling baru)

```bash
C:\xampp\mysql\bin\mysqldump.exe -h 127.0.0.1 -u root digital_archive > C:\sync\digital_archive.sql
```

> Buat folder kerja sync, misal `C:\sync\` — atau simpan file dump ke flashdisk/cloud drive.

### 4b. Import (di PC tujuan)

```bash
C:\xampp\mysql\bin\mysql.exe -h 127.0.0.1 -u root -e "CREATE DATABASE IF NOT EXISTS digital_archive CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
C:\xampp\mysql\bin\mysql.exe -h 127.0.0.1 -u root digital_archive < C:\sync\digital_archive.sql
```

### 4c. Jangan lupa FILE UPLOAD

Database cuma nyimpen path-nya. File fisiknya di `storage/app/public/`. Salin juga folder ini saat pindah:

```bash
robocopy C:\xampp\htdocs\digital-archive\storage\app\public E:\sync\storage_public //E
# (kebalikannya saat sampai di kantor)
```

> Alternatif: simpan dump SQL + zip `storage/app/public` ke cloud drive (Google Drive — apalagi lu sudah pakai Flysystem Google Drive di proyek ini!) otomatis tiap malam.

### 4d. Script sync sudah disediakan di repo

Dua script sudah ada di root proyek (dan teruji):

- **`sync-out.bat`** — export dump database + salin `storage/app/public` ke folder sync (`E:\sync`). Jalankan di PC yang datanya paling baru **sebelum pindah kerja**.
- **`sync-in.bat`** — di PC tujuan: backup dulu keadaan lama ke `backup_before_import\`, baru import dump + file, lalu refresh storage link & cache.

Konfigurasi (lokasi folder sync, kredensial DB) ada di bagian atas masing-masing file. Alur:

```text
PC lama : sync-out.bat  →  bawa E:\sync (flashdisk / cloud)
PC baru : sync-in.bat   →  kerja
```

Jika bukan di E:\, ubah `set "SYNC_DIR=..."` di kedua file.

### 4e. Backup otomatis tiap malam 21:00 (Windows Task Scheduler)

Task `DigitalArchive-BackupHarian` sudah terdaftar di PC ini: tiap hari jam
21:00 menjalankan `sync-out.bat auto` (mode tanpa pause, mencatat log ke
`E:\sync\logs\sync-out.log`, dan **menyalakan MySQL sendiri kalau lagi mati**
— percobaan 2x @25 detik).

Untuk mendaftarkan ulang di PC baru (sekali saja):

```powershell
powershell -ExecutionPolicy Bypass -File C:\xampp\htdocs\digital-archive\setup-backup-task.ps1
```

Perintah berguna:

```text
Uji jalan sekarang : schtasks /Run /TN DigitalArchive-BackupHarian
Status & jadwal    : schtasks /Query /TN DigitalArchive-BackupHarian /V /FO LIST
Lihat log          : type E:\sync\logs\sync-out.log
Hapus task         : schtasks /Delete /TN DigitalArchive-BackupHarian /F
```

Backup otomatis ini sekaligus jaring pengaman: walau kamu lupa export manual
sebelum pindah PC, dump kemarin jam 21:00 selalu tersedia di E:\sync.

---

## 5. Git di hari-hari: alur rutin

```bash
# sebelum mulai kerja di PC baru (kantor/rumah):
git pull

# setelah selesai kerja:
git add .
git commit -m "deskripsi perubahan hari ini"
git push
```

Rutinitas lengkap tiap pindah:

```text
PC lama: git push + sync-out.bat + (bawa flashdisk / pastikan cloud sinkron)
PC baru: git pull + sync-in.bat + mulai kerja
```

Kalau tim ada yang lain juga commit — biasakan `git pull --rebase` dulu sebelum push biar history bersih.

---

## 6. Kalau nanti memang mau Docker (untuk pengetahuan)

Buat `Dockerfile` + `docker-compose.yml` dengan service: `php-fpm`, `nginx`/`apache`, `mysql`, `phpmyadmin`, `reverb`. Kelebihannya: semua versi PHP/extension terkunci, PC kantor cukup install Docker saja. Kekurangannya: butuh WSL2 + spek, dan lu harus belajar konsep image/volume.

Cuek dulu — tapi kalau jadi butuh, tanyakan ke gue dan kita buatkan. Konfigurasi `.env` app sudah container-friendly (pakai `DB_HOST=127.0.0.1` yang tinggal diganti `DB_HOST=mysql`).

---

## 7. Checklist "pindah PC baru" versi super ringkas

1. Install XAMPP 8.4 + Git + Composer + Node
2. `git clone` → `htdocs/digital-archive`
3. `composer install` + `npm install && npm run build`
4. Salin `.env` (termasuk **APP_KEY** yang sama!)
5. Import DB (`sync-in.bat`) + `php artisan storage:link`
6. Salin Alias `httpd-xampp.conf` dari rumah
7. Start Apache & MySQL → `http://localhost/digital-archive`

---

*Terakhir diupdate 26 Sep 2026 oleh Buffy (Codebuff). File pendamping: `PANDUAN-FRONTEND.md` (stack interaktif) — keduanya saling melengkapi.*
