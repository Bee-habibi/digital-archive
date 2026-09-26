<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('archives', function (Blueprint $table) {
            $table->id();
            $table->string('archive_number')->unique();
            $table->string('document_number')->nullable();
            $table->string('title');
            $table->foreignId('category_id')->constrained('archive_categories');
            $table->foreignId('type_id')->constrained('archive_types');
            // unit_id is the core of data isolation - EVERY query must filter by this
            $table->foreignId('unit_id')->constrained('units');
            $table->date('document_date')->nullable();
            $table->unsignedSmallInteger('year');
            $table->text('description')->nullable();
            $table->enum('status', [
                'draft', 'menunggu_verifikasi', 'terverifikasi', 'perlu_perbaikan', 'diarsipkan',
            ])->default('draft');
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->timestamp('verified_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            // index that matters most for isolation + search performance
            $table->index(['unit_id', 'status']);
            $table->index(['unit_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archives');
    }
};
