<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jalur file arsip kini memuat nama folder berjudul ("{id} - {judul}") yang
 * bisa panjang — kolom file_path diperlebar dari varchar(255) ke varchar(500).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('archive_files', function (Blueprint $table) {
            $table->string('file_path', 500)->change();
        });
    }

    public function down(): void
    {
        Schema::table('archive_files', function (Blueprint $table) {
            $table->string('file_path', 255)->change();
        });
    }
};
