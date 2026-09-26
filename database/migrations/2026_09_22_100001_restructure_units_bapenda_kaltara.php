<?php

use App\Models\Unit;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menata ulang struktur unit mengikuti organisasi Bapenda Provinsi Kaltara yang sebenarnya:
 *
 *   Bapenda (level 1)
 *   ├── Sub Bagian Umum / Sekretariat          (level 2)
 *   ├── Bidang Perencanaan                     (level 2)
 *   ├── Bidang Pengelolaan & Pelayanan         (level 2)
 *   ├── Bidang Pengendalian & Evaluasi         (level 2)
 *   └── UPTD Bulungan / Tarakan / Nunukan /    (level 2)
 *       Malinau / Tana Tidung
 *           ├── Sub Bagian Umum                (level 3)
 *           ├── Seksi Penagihan                (level 3)
 *           └── Seksi Pendataan                (level 3)
 *
 * Data lama dipertahankan: unit lama yang masih dipakai hanya DIGANTI NAMA
 * (unit_id arsip & user tidak berubah), unit lama yang tidak ada padanannya
 * digabung ke unit baru lalu baris lamanya dihapus. Kolom google_folder_id
 * dicatat agar folder Drive per unit bisa dikelola via gdrive:setup-folders.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('units', 'google_folder_id')) {
            Schema::table('units', function (Blueprint $table) {
                $table->string('google_folder_id')->nullable()->after('is_active')
                    ->comment('ID folder Google Drive untuk unit ini (isi via gdrive:setup-folders)');
            });
        }

        // Sudah pernah dimigrasi? (penanda: bidang perencanaan sudah ada)
        if (Unit::where('code', 'BID_PERENCANAAN')->exists()) {
            return;
        }

        $badan = Unit::firstOrCreate(
            ['code' => 'BADAN'],
            ['name' => 'Bapenda Provinsi Kalimantan Utara', 'level' => 1, 'parent_id' => null]
        );

        // --- Level 2: ganti nama unit yang ada --------------------------------

        Unit::where('code', 'SEKRETARIAT')->update(['name' => 'Sub Bagian Umum / Sekretariat']);
        Unit::where('code', 'BID_PENGENDALIAN')->update(['name' => 'Bidang Pengendalian & Evaluasi']);

        // Bidang lama "Pendataan & Penetapan" tidak ada di struktur baru ->
        // kodenya dipakai ulang menjadi Bidang Pengelolaan & Pelayanan.
        Unit::where('code', 'BID_PENDATAAN')->update([
            'code' => 'BID_PENGELOLAAN',
            'name' => 'Bidang Pengelolaan & Pelayanan',
        ]);

        // Bidang Penagihan (level badan) tidak ada di struktur baru -> nonaktif
        // (arsip lama tetap aman karena barisnya dipertahankan).
        Unit::where('code', 'BID_PENAGIHAN')->update(['is_active' => false]);

        Unit::firstOrCreate(
            ['code' => 'BID_PERENCANAAN'],
            ['name' => 'Bidang Perencanaan', 'level' => 2, 'parent_id' => $badan->id, 'is_active' => true]
        );

        // --- 5 UPTD -----------------------------------------------------------

        $uptds = [
            'UPTD_BULUNGAN' => 'UPTD Bapenda Bulungan',
            'UPTD_TARAKAN' => 'UPTD Bapenda Tarakan',
            'UPTD_NUNUKAN' => 'UPTD Bapenda Nunukan',
            'UPTD_MALINAU' => 'UPTD Bapenda Malinau',
            'UPTD_TANA_TIDUNG' => 'UPTD Bapenda Tana Tidung',
        ];

        $uptdIds = [];
        foreach ($uptds as $code => $name) {
            $unit = Unit::firstOrCreate(
                ['code' => $code],
                ['name' => $name, 'level' => 2, 'parent_id' => $badan->id, 'is_active' => true]
            );

            // Bila unit sudah ada tapi namanya beda, samakan.
            if ($unit->name !== $name) {
                $unit->update(['name' => $name, 'level' => 2, 'parent_id' => $badan->id, 'is_active' => true]);
            }

            $uptdIds[$code] = $unit->id;
        }

        // --- Level 3: Sub Bagian Umum + 2 Seksi di TIAP UPTD -------------------

        foreach ($uptds as $code => $name) {
            $parentId = $uptdIds[$code];
            $slug = strtolower(str_replace('UPTD_', '', $code)); // bulungan, tarakan, ...

            foreach ([
                ['code' => "UPTD_{$slug}_SUBUMUM", 'name' => 'Sub Bagian Umum'],
                ['code' => "UPTD_{$slug}_SEKSI_PENAGIHAN", 'name' => 'Seksi Penagihan'],
                ['code' => "UPTD_{$slug}_SEKSI_PENDATAAN", 'name' => 'Seksi Pendataan'],
            ] as $sub) {
                Unit::firstOrCreate(
                    ['code' => $sub['code']],
                    ['name' => $sub['name'], 'level' => 3, 'parent_id' => $parentId, 'is_active' => true]
                );
            }
        }

        // --- Konsolidasi unit level-3 lama (eks Kasi di UPTD Tarakan) ----------
        // Data (user & arsip) dipindah ke unit baru, lalu baris unit lama dihapus.
        $tarakanId = $uptdIds['UPTD_TARAKAN'];

        foreach ([
            'UPTD_TARAKAN_KASI_PENDATAAN' => 'UPTD_tarakan_SEKSI_PENDATAAN',
            'UPTD_TARAKAN_KASI_PENAGIHAN' => 'UPTD_tarakan_SEKSI_PENAGIHAN',
            'UPTD_TARAKAN_KASI_TU' => 'UPTD_tarakan_SUBUMUM',
        ] as $oldCode => $newCode) {
            $legacy = Unit::where('code', $oldCode)->first();
            $target = Unit::where('code', $newCode)->where('parent_id', $tarakanId)->first();

            if ($legacy && $target && $legacy->id !== $target->id) {
                DB::table('users')->where('unit_id', $legacy->id)->update(['unit_id' => $target->id]);
                DB::table('archives')->where('unit_id', $legacy->id)->update(['unit_id' => $target->id]);
                DB::table('units')->where('id', $legacy->id)->delete();
            }
        }
    }

    public function down(): void
    {
        // Restrukturisasi organisasi tidak di-rollback otomatis (data riil sudah dipindah).
        // Kolom google_folder_id tetap aman dihapus:
        if (Schema::hasColumn('units', 'google_folder_id')) {
            Schema::table('units', function (Blueprint $table) {
                $table->dropColumn('google_folder_id');
            });
        }
    }
};
