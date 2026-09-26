<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    /**
     * Struktur organisasi Bapenda Provinsi Kalimantan Utara (2 tingkat):
     *
     *   Level 1  Bapenda Provinsi Kalimantan Utara (root)
     *   Level 1  ├── Sub Bagian Umum               (level 2)
     *            ├── Bidang Perencanaan            (level 2)
     *            ├── Bidang Pengelolaan            (level 2)
     *            └── Bidang Evaluasi               (level 2)
     *   Level 1  UPTD Bapenda Bulungan (root) ── Sub Bagian Umum, Seksi Penagihan, Seksi Pendataan (level 2)
     *   Level 1  UPTD Bapenda Tarakan / Nunukan / Malinau / Tana Tidung (pola sama)
     *
     * Artinya: Bapenda dan 5 UPTD adalah root setara; semua sub unit berada di level 2.
     * Nama unit ini sekaligus menjadi struktur folder di Google Drive
     * (lihat Unit::driveFolderPath()).
     */
    public function run(): void
    {
        // ----- Root level 1 -----
        $badan = Unit::firstOrCreate(
            ['code' => 'BADAN'],
            ['name' => 'Bapenda Provinsi Kalimantan Utara', 'level' => 1, 'parent_id' => null]
        );

        $uptds = [];
        foreach ([
            'BULUNGAN' => 'UPTD Bapenda Bulungan',
            'TARAKAN' => 'UPTD Bapenda Tarakan',
            'NUNUKAN' => 'UPTD Bapenda Nunukan',
            'MALINAU' => 'UPTD Bapenda Malinau',
            'TANA_TIDUNG' => 'UPTD Bapenda Tana Tidung',
        ] as $slug => $name) {
            $uptds[$slug] = Unit::firstOrCreate(
                ['code' => "UPTD_{$slug}"],
                ['name' => $name, 'level' => 1, 'parent_id' => null]
            );
        }

        // ----- Level 2 di bawah Bapenda -----
        // Kode BID_PENGENDALIAN dipertahankan agar konsisten dengan data yang sudah ada
        // (unit ini dulu bernama "Bidang Pengendalian & Evaluasi", kini "Bidang Evaluasi").
        foreach ([
            ['code' => 'SEKRETARIAT', 'name' => 'Sub Bagian Umum', 'parent' => $badan->id],
            ['code' => 'BID_PERENCANAAN', 'name' => 'Bidang Perencanaan', 'parent' => $badan->id],
            ['code' => 'BID_PENGELOLAAN', 'name' => 'Bidang Pengelolaan', 'parent' => $badan->id],
            ['code' => 'BID_PENGENDALIAN', 'name' => 'Bidang Evaluasi', 'parent' => $badan->id],
        ] as $unit) {
            Unit::firstOrCreate(
                ['code' => $unit['code']],
                ['name' => $unit['name'], 'level' => 2, 'parent_id' => $unit['parent']]
            );
        }

        // ----- Level 2 di bawah tiap UPTD -----
        foreach ($uptds as $slug => $uptd) {
            $s = strtolower($slug); // bulungan, tarakan, ...

            foreach ([
                ['code' => "UPTD_{$s}_SUBUMUM", 'name' => 'Sub Bagian Umum'],
                ['code' => "UPTD_{$s}_SEKSI_PENAGIHAN", 'name' => 'Seksi Penagihan'],
                ['code' => "UPTD_{$s}_SEKSI_PENDATAAN", 'name' => 'Seksi Pendataan'],
            ] as $sub) {
                Unit::firstOrCreate(
                    ['code' => $sub['code']],
                    ['name' => $sub['name'], 'level' => 2, 'parent_id' => $uptd->id]
                );
            }
        }
    }
}
