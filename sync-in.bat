@echo off
setlocal
REM ============================================================
REM  sync-in.bat — IMPORT data di PC tujuan
REM  Sumber : %SYNC_DIR%\digital_archive.sql + storage_public\
REM  Target : database %DB_NAME% + storage\app\public\
REM  Jalankan di PC tujuan SETELAH folder sync tersedia.
REM  Aman diulang: backup dulu isi lama sebelum menimpa.
REM ============================================================

REM ==== KONFIGURASI (ubah di sini kalau perlu) ====
set "SYNC_DIR=E:\sync"
set "MYSQL_BIN=C:\xampp\mysql\bin"
set "PHP_BIN=C:\xampp\php"
set "DB_NAME=digital_archive"
set "DB_USER=root"
set "DB_HOST=127.0.0.1"
REM ==== AKHIR KONFIGURASI ====

cd /d "%~dp0"

if not exist "%SYNC_DIR%\digital_archive.sql" (
    echo File %SYNC_DIR%\digital_archive.sql tidak ditemukan!
    echo Sambungkan dulu drive/flashdisk yang berisi folder sync.
    goto :fail
)

echo.
echo [1/4] Backup keadaan database saat ini (jaga-jaga) ...
if not exist "%SYNC_DIR%\backup_before_import" mkdir "%SYNC_DIR%\backup_before_import"
"%MYSQL_BIN%\mysqldump.exe" -h %DB_HOST% -u %DB_USER% %DB_NAME% > "%SYNC_DIR%\backup_before_import\digital_archive_before_import.sql" 2>nul
echo   OK (kalau DB belum ada, backup boleh gagal — tidak masalah)

echo [2/4] Salin file upload lama ke backup ...
robocopy "storage\app\public" "%SYNC_DIR%\backup_before_import\storage_public" /E /NFL /NDL /NJH /NJS /NP >nul
echo   OK

echo [3/4] Import database %DB_NAME% ...
"%MYSQL_BIN%\mysql.exe" -h %DB_HOST% -u %DB_USER% -e "CREATE DATABASE IF NOT EXISTS %DB_NAME% CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
"%MYSQL_BIN%\mysql.exe" -h %DB_HOST% -u %DB_USER% %DB_NAME% < "%SYNC_DIR%\digital_archive.sql"
if errorlevel 1 (
    echo   GAGAL import! Restore manual: lihat backup_before_import\
    goto :fail
)
echo   OK

echo [4/4] Salin file upload baru ke storage\app\public ...
robocopy "%SYNC_DIR%\storage_public" "storage\app\public" /E /NFL /NDL /NJH /NJS /NP
if errorlevel 8 (
    echo   GAGAL menyalin file upload!
    goto :fail
)
echo   OK

echo [bonus] Refresh storage link + cache ...
"%PHP_BIN%\php.exe" artisan storage:link 2>nul
"%PHP_BIN%\php.exe" artisan optimize:clear >nul 2>&1

echo.
echo ============================================
echo  SELESAI! Data dari %SYNC_DIR% sudah diterapkan.
echo  Cek aplikasi: http://localhost/digital-archive/public
echo ============================================
goto :end

:fail
echo.
echo *** GAGAL — datamu aman di backup_before_import ***
pause
exit /b 1

:end
pause
exit /b 0
