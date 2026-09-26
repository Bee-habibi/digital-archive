<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArchiveFile extends Model
{
    protected $fillable = [
        'archive_id', 'original_name', 'stored_name', 'file_path',
        'mime_type', 'file_size', 'uploaded_by',
    ];

    public function archive()
    {
        return $this->belongsTo(Archive::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function humanSize(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 1) . ' ' . $units[$i];
    }
}
