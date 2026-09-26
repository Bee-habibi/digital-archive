<?php

namespace App\Traits;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request as RequestFacade;

trait LogsActivity
{
    /**
     * Dipakai di semua controller untuk mencatat aktivitas penting:
     * login, logout, tambah/ubah/hapus arsip, upload/download file,
     * verifikasi, perubahan status, perubahan user, perubahan konfigurasi.
     */
    protected function logActivity(string $action, string $module, ?string $description = null): void
    {
        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'module' => $module,
            'description' => $description,
            'ip_address' => RequestFacade::ip(),
            'user_agent' => RequestFacade::userAgent(),
            'created_at' => now(),
        ]);
    }
}
