@echo off
setlocal
REM ============================================================
REM  sync-out.bat — EXPORT data untuk pindah PC / backup harian
REM  Proyek : digital-archive (Laravel)
REM  Output : %SYNC_DIR%\digital_archive.sql  (dump database)
REM           %SYNC_DIR%\storage_public\      (file upload)
REM           %SYNC_DIR%\logs\sync-out.log    (log mode auto)
REM
REM  Pemakaian:
REM    sync-out.bat          -> mode manual (ada pause di akhir)
REM    sync-out.bat auto     -> mode scheduled task (tanpa pause, log)
REM ============================================================

REM ==== KONFIGURASI (ubah di sini kalau perlu) ====
set "SYNC_DIR=E:\sync"
set "MYSQL_BIN=C:\xampp\mysql\bin"
set "DB_NAME=digital_archive"
set "DB_USER=root"
set "DB_HOST=127.0.0.1"
REM ==== AKHIR KONFIGURASI ====

set "MODE=%~1"
set "AUTO=0"
if /i "%MODE%"=="auto" set "AUTO=1"

cd /d "%~dp0"

if "%AUTO%"=="1" (
    if not exist "%SYNC_DIR%\logs" mkdir "%SYNC_DIR%\logs"
    call :log "=== sync-out auto mulai ==="
) else (
    echo.
)

REM MySQL harus hidup; kalau mati, coba nyalakan dulu (berguna utk task malam)
REM Catatan: pakai findstr (bukan find) dan ping (bukan timeout) supaya tidak
REM bentrok dengan GNU tools dari Git Bash yang mungkin ada di PATH.
tasklist /FI "IMAGENAME eq mysqld.exe" 2>nul | findstr /I "mysqld.exe" >nul
if errorlevel 1 (
    if "%AUTO%"=="1" call :log "MySQL mati - mencoba menyalakan..."
    if "%AUTO%"=="0" echo MySQL tidak jalan - mencoba menyalakan...
    start "" /MIN "%MYSQL_BIN%\mysqld.exe" --defaults-file=C:/xampp/mysql/bin/my.ini --standalone
    REM MariaDB perlu waktu startup + recovery; 25 detik terbukti cukup di mesin ini
    ping -n 26 127.0.0.1 >nul
    tasklist /FI "IMAGENAME eq mysqld.exe" 2>nul | findstr /I "mysqld.exe" >nul
    if errorlevel 1 (
        REM belum hidup juga: coba sekali lagi
        if "%AUTO%"=="1" call :log "percobaan kedua menyalakan MySQL..."
        start "" /MIN "%MYSQL_BIN%\mysqld.exe" --defaults-file=C:/xampp/mysql/bin/my.ini --standalone
        ping -n 26 127.0.0.1 >nul
    )
)

echo [1/3] Export database %DB_NAME% ...
if "%AUTO%"=="1" call :log "export database"
if not exist "%SYNC_DIR%" mkdir "%SYNC_DIR%"
"%MYSQL_BIN%\mysqldump.exe" -h %DB_HOST% -u %DB_USER% %DB_NAME% > "%SYNC_DIR%\digital_archive.sql" 2>nul
if errorlevel 1 (
    if "%AUTO%"=="1" (call :log "GAGAL export database") else (echo   GAGAL export database! Cek apakah MySQL sudah jalan.)
    goto :fail
)
for %%F in ("%SYNC_DIR%\digital_archive.sql") do (
    if "%AUTO%"=="1" (call :log "dump OK - %%~zF bytes") else (echo   OK - %%~zF bytes)
)

echo [2/3] Salin file upload (storage/app/public) ...
if "%AUTO%"=="1" call :log "salin storage"
robocopy "storage\app\public" "%SYNC_DIR%\storage_public" /E /NFL /NDL /NJH /NJS /NP >nul
REM robocopy: exit code 1 = file tersalin (bukan error)
if errorlevel 8 (
    if "%AUTO%"=="1" call :log "GAGAL salin storage"
    if "%AUTO%"=="0" echo   GAGAL menyalin file upload!
    goto :fail
)
if "%AUTO%"=="0" echo   OK

if "%AUTO%"=="1" (
    call :log "=== sync-out auto SELESAI ==="
    exit /b 0
)

echo [3/3] Info file terakhir:
dir "%SYNC_DIR%" | findstr /i "digital_archive.sql"
echo.
echo ============================================
echo  SELESAI! Bawa folder %SYNC_DIR% ke PC tujuan,
echo  lalu jalankan sync-in.bat di sana.
echo ============================================
goto :end

:fail
if "%AUTO%"=="1" (call :log "=== sync-out auto GAGAL ===" & exit /b 1)
echo.
echo *** GAGAL — perbaiki dulu, datamu belum lengkap! ***
pause
exit /b 1

:end
pause
exit /b 0

:log
>> "%SYNC_DIR%\logs\sync-out.log" echo [%date% %time%] %~1
exit /b 0
