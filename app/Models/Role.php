<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $fillable = ['name', 'description'];

    // Nama role dibakukan supaya konsisten dipakai di middleware & policy
    public const SUPER_ADMIN = 'super_admin';
    public const SUPER_USER = 'super_user';
    public const ADMIN = 'admin';

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
