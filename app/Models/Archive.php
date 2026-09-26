<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Archive extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'archive_number', 'folder_name', 'document_number', 'title', 'category_id', 'type_id',
        'unit_id', 'document_date', 'year', 'description', 'status',
        'created_by', 'updated_by', 'verified_by', 'verified_at',
    ];

    protected $casts = [
        'document_date' => 'date',
        'verified_at' => 'datetime',
    ];

    public const STATUS_DRAFT = 'draft';
    public const STATUS_MENUNGGU_VERIFIKASI = 'menunggu_verifikasi';
    public const STATUS_TERVERIFIKASI = 'terverifikasi';
    public const STATUS_PERLU_PERBAIKAN = 'perlu_perbaikan';
    public const STATUS_DIARSIPKAN = 'diarsipkan';

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function category()
    {
        return $this->belongsTo(ArchiveCategory::class, 'category_id');
    }

    public function type()
    {
        return $this->belongsTo(ArchiveType::class, 'type_id');
    }

    public function files()
    {
        return $this->hasMany(ArchiveFile::class);
    }

    public function verifications()
    {
        return $this->hasMany(ArchiveVerification::class)->latest();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * Nama folder arsip yang manusiawi di disk: "{id} - {judul}".
     * Dibuat SEKALI (saat file pertama diunggah) dan disimpan ke database,
     * sehingga tidak berubah walau judul arsip kemudian diedit.
     */
    public function ensureFolderName(): string
    {
        if (! $this->folder_name) {
            $base = trim(preg_replace('/[\\\\\/\:\*\?\"\<\>\|]+/', '-', (string) $this->title)) ?: 'arsip';
            $base = trim($base, " -");
            $this->forceFill(['folder_name' => mb_substr($this->id.' - '.$base, 0, 100)])->save();
        }

        return $this->folder_name;
    }

    /**
     * INTI dari isolasi data antar unit kerja.
     * Query ini WAJIB dipakai di semua controller yang mengambil data arsip,
     * jangan pernah query Archive::all() atau Archive::find($id) polos
     * untuk role admin biasa, karena itu bisa ditembus lewat manipulasi URL.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->canAccessAllUnits()) {
            return $query; // Super Admin & Super User lihat semua unit
        }

        return $query->where('unit_id', $user->unit_id);
    }
}
