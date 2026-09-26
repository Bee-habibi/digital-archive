<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        Role::firstOrCreate(['name' => Role::SUPER_ADMIN], ['description' => 'Akses penuh & maintenance sistem']);
        Role::firstOrCreate(['name' => Role::SUPER_USER], ['description' => 'Kontrol & verifikasi arsip lintas unit']);
        Role::firstOrCreate(['name' => Role::ADMIN], ['description' => 'Input & kelola arsip unit sendiri']);
    }
}
