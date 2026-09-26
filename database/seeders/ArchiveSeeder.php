<?php

namespace Database\Seeders;

use App\Models\Archive;
use App\Models\ArchiveCategory;
use App\Models\User;
use Illuminate\Database\Seeder;

class ArchiveSeeder extends Seeder
{
    // Data contoh biar dashboard & daftar arsip nggak kosong pas demo.
    // Sengaja tidak upload file fisik di sini - fokus ke data record dulu.
    public function run(): void
    {
        $categories = ArchiveCategory::with('types')->get();
        if ($categories->isEmpty()) return;

        $admins = User::whereHas('role', fn ($q) => $q->where('name', 'admin'))->get();
        if ($admins->isEmpty()) return;

        $statuses = [
            Archive::STATUS_DRAFT,
            Archive::STATUS_MENUNGGU_VERIFIKASI,
            Archive::STATUS_TERVERIFIKASI,
            Archive::STATUS_PERLU_PERBAIKAN,
            Archive::STATUS_DIARSIPKAN,
        ];

        $judul = [
            'Laporan Realisasi Pajak Daerah', 'Surat Keputusan Penetapan Pajak',
            'Nota Dinas Koordinasi Bidang', 'Laporan Pertanggungjawaban Keuangan',
            'SK Kenaikan Pangkat Pegawai', 'Berita Acara Serah Terima Aset',
            'Surat Masuk dari Dinas Terkait', 'Laporan Bulanan Penagihan Retribusi',
        ];

        foreach ($admins as $admin) {
            $unit = $admin->unit;
            if (! $unit) continue;

            for ($i = 1; $i <= 6; $i++) {
                $category = $categories->random();
                $type = $category->types->isNotEmpty() ? $category->types->random() : null;
                if (! $type) continue;

                $year = now()->year - rand(0, 1);

                Archive::create([
                    'archive_number' => sprintf('ARS/%d/%03d/%d', $unit->id, $i, $year),
                    'document_number' => 'DOC/' . rand(100, 999) . '/' . $year,
                    'title' => $judul[array_rand($judul)] . ' - ' . $unit->name,
                    'category_id' => $category->id,
                    'type_id' => $type->id,
                    'unit_id' => $unit->id,
                    'document_date' => now()->subDays(rand(1, 300)),
                    'year' => $year,
                    'description' => 'Data contoh untuk keperluan demo aplikasi.',
                    'status' => $statuses[array_rand($statuses)],
                    'created_by' => $admin->id,
                ]);
            }
        }
    }
}
