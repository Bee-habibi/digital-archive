# ============================================================
#  setup-backup-task.ps1 — daftarkan Windows Scheduled Task
#  untuk menjalankan sync-out.bat (mode auto) tiap malam 21:00
#  sebagai backup harian proyek digital-archive.
#
#  Pemakaian (sekali per PC):
#    powershell -ExecutionPolicy Bypass -File setup-backup-task.ps1
#
#  Hasilnya: task "DigitalArchive-BackupHarian" berjalan tiap
#  21:00 (baterai laptop pun tetap jalan). Cek/ubah di
#  Task Scheduler GUI kalau perlu.
# ============================================================

$ErrorActionPreference = 'Stop'

$projectDir = 'C:\xampp\htdocs\digital-archive'
$batPath    = Join-Path $projectDir 'sync-out.bat'
$taskName   = 'DigitalArchive-BackupHarian'

if (-not (Test-Path $batPath)) {
    Write-Error "sync-out.bat tidak ditemukan di $batPath"
    exit 1
}

$action = New-ScheduledTaskAction -Execute $batPath -Argument 'auto' -WorkingDirectory $projectDir
$trigger = New-ScheduledTaskTrigger -Daily -At '21:00'
$settings = New-ScheduledTaskSettingsSet `
    -StartWhenAvailable `
    -AllowStartIfOnBatteries `
    -DontStopIfGoingOnBatteries `
    -ExecutionTimeLimit (New-TimeSpan -Hours 1) `
    -MultipleInstances IgnoreNew

# Hapus task lama kalau ada (idempotent)
$existing = Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue
if ($existing) {
    Unregister-ScheduledTask -TaskName $taskName -Confirm:$false
    Write-Host "Task lama dihapus, mendaftarkan ulang..."
}

Register-ScheduledTask -TaskName $taskName `
    -Action $action `
    -Trigger $trigger `
    -Settings $settings `
    -Description "Backup harian digital-archive (DB + storage) ke folder sync, jam 21:00" | Out-Null

Write-Host ""
Write-Host "OK - Task '$taskName' terdaftar: tiap hari 21:00 -> sync-out.bat auto" -ForegroundColor Green
Write-Host ""
Write-Host 'Uji jalan sekarang (opsional):  schtasks /Run /TN "$taskName"'
Write-Host 'Lihat log hasil:                type E:\sync\logs\sync-out.log'
Write-Host 'Hapus task:                     schtasks /Delete /TN "$taskName" /F'
