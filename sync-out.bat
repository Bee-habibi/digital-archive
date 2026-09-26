@echo off
setlocal
REM ============================================================
REM  sync-out.bat — EXPORT data untuk pindah PC
REM  Proyek : digital-archive (Laravel)
REM  Output : %SYNC_DIR%\digital_archive.sql  (dump database)
REM           %SYNC_DIR%\storage_public\      (file upload)
REM  Jalankan dari folder proyek ini SEBELUM pindah kerja.
REM ============================================================

REM ==== KONFIGURASI (ubah di sini kalau perlu) ====
set "SYNC_DIR=E:\sync"
set "MYSQL_BIN=C:\xampp\mysql\bin"
set "DB_NAME=digital_archive"
set "DB_USER=root"
set "DB_HOST=127.0.0.1"
REM ==== AKHIR KONFIGURASI ====

cd /d "%~dp0"

echo.
echo [1/3] Export database %DB_NAME% ...
if not exist "%SYNC_DIR%" mkdir "%SYNC_DIR%"
"%MYSQL_BIN%\mysqldump.exe" -h %DB_HOST% -u %DB_USER% %DB_NAME% > "%SYNC_DIR%\digital_archive.sql"
if errorlevel 1 (
    echo   GAGAL export database! Cek apakah MySQL sudah jalan.
    goto :fail
)
for %%F in ("%SYNC_DIR%\digital_archive.sql") do echo   OK - %%~zF bytes

echo [2/3] Salin file upload (storage/app/public) ...
robocopy "storage\app\public" "%SYNC_DIR%\storage_public" /E /NFL /NDL /NJH /NJS /NP
REM robocopy: exit code 1 = file tersalin (bukan error)
if errorlevel 8 (
    echo   GAGAL menyalin file upload!
    goto :fail
)
echo   OK

echo [3/3] Info file terakhir:
dir "%SYNC_DIR%" | findstr /i "digital_archive.sql"
echo.
echo ============================================
echo  SELESAI! Bawa folder %SYNC_DIR% ke PC tujuan,
echo  lalu jalankan sync-in.bat di sana.
echo ============================================
goto :end

:fail
echo.
echo *** GAGAL — perbaiki dulu, datamu belum lengkap! ***
pause
exit /b 1

:end
pause
exit /b 0
