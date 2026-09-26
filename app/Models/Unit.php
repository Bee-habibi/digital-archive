<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    protected $fillable = ['parent_id', 'level', 'name', 'code', 'description', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function archives()
    {
        return $this->hasMany(Archive::class);
    }

    public function parent()
    {
        return $this->belongsTo(Unit::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Unit::class, 'parent_id');
    }

    /**
     * Semua id unit di bawah unit ini (termasuk dirinya sendiri).
     * Dipakai supaya, misalnya, admin di level "UPTD Tarakan" otomatis
     * bisa melihat arsip semua Kasi di bawahnya (kalau kebijakannya begitu).
     * Untuk Admin biasa yang login di level paling bawah (Kasi), ini cuma
     * balikin id dirinya sendiri.
     */
    public function descendantIds(): array
    {
        $ids = [$this->id];
        foreach ($this->children as $child) {
            $ids = array_merge($ids, $child->descendantIds());
        }
        return $ids;
    }

    /**
     * Bangun path folder Google Drive berdasarkan nama unit dari root sampai unit ini,
     * contoh: "UPTD Tarakan/Kasi Pendataan"
     * Adapter Flysystem Google Drive akan otomatis bikin folder ini kalau belum ada,
     * jadi TIDAK perlu bikin folder manual satu-satu di Drive.
     */
    public function driveFolderPath(): string
    {
        $names = [];
        $unit = $this;
        while ($unit) {
            array_unshift($names, $this->sanitizeFolderName($unit->name));
            $unit = $unit->parent;
        }
        return implode('/', $names);
    }

    private function sanitizeFolderName(string $name): string
    {
        return trim(str_replace(['/', '\\'], '-', $name));
    }
}
