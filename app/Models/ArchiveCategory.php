<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArchiveCategory extends Model
{
    protected $fillable = ['name', 'code', 'description', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function types()
    {
        return $this->hasMany(ArchiveType::class, 'category_id');
    }

    public function archives()
    {
        return $this->hasMany(Archive::class, 'category_id');
    }
}
