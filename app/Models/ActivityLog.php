<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'action', 'module', 'description', 'ip_address', 'user_agent', 'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    // Log tidak boleh dihapus oleh Admin biasa -> tidak ada mass-assignment untuk delete,
    // dan route hapus/delete API sengaja tidak dibuatkan endpoint sama sekali.

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
