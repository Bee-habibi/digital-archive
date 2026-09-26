<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            // Struktur asli cuma 4 unit flat (Sekretariat, Bidang 1-3).
            // Diubah jadi hierarkis supaya bisa mengikuti struktur real instansi:
            // Badan -> Bidang/UPTD -> Kasi/Seksi (3 level), sesuai contoh yang diberikan.
            $table->foreignId('parent_id')->nullable()->after('id')->constrained('units')->nullOnDelete();
            $table->unsignedTinyInteger('level')->default(1)->after('parent_id'); // 1=Badan, 2=Bidang/UPTD, 3=Kasi/Seksi
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn('level');
        });
    }
};
