<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Restrukturisasi tingkat unit sesuai struktur organisasi Bapenda Kaltara:
 *
 *   Level 1  Bapenda Provinsi Kalimantan Utara  ─┬─ Sub Bagian Umum        (level 2)
 *                                                ├─ Bidang Perencanaan     (level 2)
 *                                                ├─ Bidang Pengelolaan     (level 2)
 *                                                └─ Bidang Evaluasi        (level 2)
 *   Level 1  UPTD Bapenda Bulungan  ─┬─ Sub Bagian Umum   (level 2)
 *   Level 1  UPTD Bapenda Tarakan    ├─ Seksi Penagihan   (level 2)
 *   Level 1  UPTD Bapenda Nunukan    └─ Seksi Pendataan   (level 2)
 *   Level 1  UPTD Bapenda Malinau
 *   Level 1  UPTD Bapenda Tana Tidung
 *
 * Artinya: 5 UPTD adalah root setara Bapenda (bukan anak Bapenda),
 * dan semua sub unit (Sub Bagian/Seksi/Bidang) berada di level 2.
 *
 * ID folder Google Drive yang tersimpan di units.google_folder_id
 * direset karena jalur folder berubah (dibuat ulang oleh gdrive:setup-folders).
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Pastikan root Bapenda ada (idempoten untuk instalasi baru).
        $badan = DB::table('units')->where('code', 'BADAN')->first();
        if (! $badan) {
            DB::table('units')->insert([
                'name' => 'Bapenda Provinsi Kalimantan Utara', 'code' => 'BADAN',
                'level' => 1, 'parent_id' => null, 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $badan = DB::table('units')->where('code', 'BADAN')->first();
        }

        // 2. UPTD (level 2 lama) menjadi root level 1 tanpa induk.
        $uptdIds = DB::table('units')
            ->where('level', 2)->where('code', 'like', 'UPTD%')
            ->pluck('id');
        if ($uptdIds->isNotEmpty()) {
            DB::table('units')->whereIn('id', $uptdIds)->update([
                'level' => 1, 'parent_id' => null, 'updated_at' => now(),
            ]);

            // Anak-anak UPTD (level 3 lama) naik menjadi level 2.
            DB::table('units')->whereIn('parent_id', $uptdIds)->update([
                'level' => 2, 'updated_at' => now(),
            ]);
        }

        // 3. Rapikan nama sub unit Bapenda sesuai struktur resmi.
        DB::table('units')->where('code', 'SEKRETARIAT')->update([
            'name' => 'Sub Bagian Umum', 'level' => 2, 'parent_id' => $badan->id, 'updated_at' => now(),
        ]);
        DB::table('units')->where('code', 'BID_PERENCANAAN')->update([
            'name' => 'Bidang Perencanaan', 'level' => 2, 'parent_id' => $badan->id, 'updated_at' => now(),
        ]);
        DB::table('units')->where('code', 'BID_PENGELOLAAN')->update([
            'name' => 'Bidang Pengelolaan', 'level' => 2, 'parent_id' => $badan->id, 'updated_at' => now(),
        ]);
        DB::table('units')->where('code', 'BID_PENGENDALIAN')->update([
            'name' => 'Bidang Evaluasi', 'level' => 2, 'parent_id' => $badan->id, 'updated_at' => now(),
        ]);

        // Unit lama yang tidak ada di struktur resmi tetap dinonaktifkan (arsip lamanya aman).
        DB::table('units')->where('code', 'BID_PENAGIHAN')->update(['is_active' => false]);

        // 4. Reset ID folder Drive yang tersimpan (jalur lama tidak berlaku lagi).
        DB::table('units')->whereNotNull('google_folder_id')->update(['google_folder_id' => null]);
    }

    public function down(): void
    {
        // Kembalikan ke struktur "semua di bawah Bapenda" (sebelum restrukturisasi ini).
        $badan = DB::table('units')->where('code', 'BADAN')->first();

        $uptdIds = DB::table('units')
            ->where('level', 1)->where('code', 'like', 'UPTD%')
            ->where('id', '!=', $badan?->id ?? 0)
            ->pluck('id');
        if ($uptdIds->isNotEmpty()) {
            DB::table('units')->whereIn('parent_id', $uptdIds)->update(['level' => 3, 'updated_at' => now()]);
            DB::table('units')->whereIn('id', $uptdIds)->update([
                'level' => 2, 'parent_id' => $badan?->id, 'updated_at' => now(),
            ]);
        }

        DB::table('units')->whereIn('code', ['SEKRETARIAT', 'BID_PERENCANAAN', 'BID_PENGELOLAAN', 'BID_PENGENDALIAN'])
            ->update(['level' => 2, 'parent_id' => $badan?->id, 'updated_at' => now()]);
    }
};
