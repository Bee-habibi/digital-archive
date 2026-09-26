<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArchiveVerification extends Model
{
    public $timestamps = false;

    protected $fillable = ['archive_id', 'user_id', 'status', 'notes', 'created_at'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function archive()
    {
        return $this->belongsTo(Archive::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
