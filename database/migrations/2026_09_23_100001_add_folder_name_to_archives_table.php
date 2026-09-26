<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Nama folder arsip yang manusiawi di disk (Google Drive / lokal).
 *
 * Format: "{id} - {judul}" (id tetap disertakan agar unik & stabil),
 * contoh: "27 - SURAT MCSP 2026 OKEY". Disimpan sekali saat arsip dibuat
 * agar tidak berubah ketika judul arsip kemudian diedit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('archives', function (Blueprint $table) {
            $table->string('folder_name')->nullable()->after('archive_number');
        });

        // Backfill arsip yang sudah ada: "{id} - {judul}" (dibersihkan dari karakter folder terlarang).
        DB::table('archives')->whereNull('folder_name')->orderBy('id')->each(function ($archive) {
            $base = trim(preg_replace('/[\\\\\/\:\*\?\"\<\>\|]+/', '-', (string) $archive->title)) ?: 'arsip';
            DB::table('archives')->where('id', $archive->id)->update([
                'folder_name' => mb_substr($archive->id.' - '.$base, 0, 100),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('archives', function (Blueprint $table) {
            $table->dropColumn('folder_name');
        });
    }
};
