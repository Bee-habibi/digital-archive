<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    // Password default HANYA untuk development, wajib diganti setelah login pertama.
    public function run(): void
    {
        $superAdmin = Role::where('name', Role::SUPER_ADMIN)->first();
        $superUser = Role::where('name', Role::SUPER_USER)->first();
        $admin = Role::where('name', Role::ADMIN)->first();

        $sekretariat = Unit::where('code', 'SEKRETARIAT')->first();
        $bidPengendalian = Unit::where('code', 'BID_PENGENDALIAN')->first();
        $uptdTarakan = Unit::where('code', 'UPTD_TARAKAN')->first();
        $seksiPendataan = Unit::where('code', 'UPTD_tarakan_SEKSI_PENDATAAN')->first();

        $accounts = [
            ['name' => 'Super Admin', 'email' => 'superadmin@arsip.local', 'role_id' => $superAdmin->id, 'unit_id' => $sekretariat->id],
            ['name' => 'Super User', 'email' => 'superuser@arsip.local', 'role_id' => $superUser->id, 'unit_id' => $sekretariat->id],
            ['name' => 'Admin Sekretariat Badan', 'email' => 'admin.sekretariat@arsip.local', 'role_id' => $admin->id, 'unit_id' => $sekretariat->id],
            ['name' => 'Admin Bidang Pengendalian', 'email' => 'admin.pengendalian@arsip.local', 'role_id' => $admin->id, 'unit_id' => $bidPengendalian->id],
            ['name' => 'Admin UPTD Tarakan', 'email' => 'admin.uptdtarakan@arsip.local', 'role_id' => $admin->id, 'unit_id' => $uptdTarakan->id],
            ['name' => 'Admin Seksi Pendataan UPTD Tarakan', 'email' => 'admin.kasipendataan@arsip.local', 'role_id' => $admin->id, 'unit_id' => $seksiPendataan->id],
        ];

        foreach ($accounts as $acc) {
            User::firstOrCreate(
                ['email' => $acc['email']],
                [
                    'name' => $acc['name'],
                    'password' => Hash::make('password123'), // GANTI setelah login pertama!
                    'role_id' => $acc['role_id'],
                    'unit_id' => $acc['unit_id'],
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );
        }
    }
}
