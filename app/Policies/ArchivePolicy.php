<?php

namespace App\Policies;

use App\Models\Archive;
use App\Models\User;

class ArchivePolicy
{
    /**
     * Ini yang menjawab requirement paling penting di spek:
     * "Admin Bidang 1 tidak boleh dapat mengakses data Bidang 2
     *  hanya dengan mengubah ID pada URL."
     *
     * Middleware role cuma cek "kamu admin atau bukan".
     * Policy ini yang cek "arsip ID 25 ini betul milik unit kerja kamu atau bukan",
     * dipanggil di controller lewat $this->authorize('view', $archive) dst.
     */
    public function view(User $user, Archive $archive): bool
    {
        return $user->canAccessAllUnits() || $archive->unit_id === $user->unit_id;
    }

    public function update(User $user, Archive $archive): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isAdmin()) {
            // Admin cuma boleh edit arsip unitnya sendiri, dan hanya saat
            // statusnya masih draft / perlu_perbaikan (bukan yang sudah terverifikasi/diarsipkan)
            return $archive->unit_id === $user->unit_id
                && in_array($archive->status, [Archive::STATUS_DRAFT, Archive::STATUS_PERLU_PERBAIKAN], true);
        }

        return false; // super_user hanya memverifikasi, bukan mengubah isi arsip
    }

    public function delete(User $user, Archive $archive): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isAdmin()) {
            return $archive->unit_id === $user->unit_id
                && $archive->status !== Archive::STATUS_DIARSIPKAN;
        }

        return false;
    }

    public function verify(User $user, Archive $archive): bool
    {
        // Hanya Super User & Super Admin yang boleh mengubah status verifikasi
        return $user->isSuperUser() || $user->isSuperAdmin();
    }

    public function uploadFile(User $user, Archive $archive): bool
    {
        return $this->update($user, $archive);
    }
}
