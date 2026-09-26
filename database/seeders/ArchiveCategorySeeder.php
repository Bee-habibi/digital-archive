<?php

namespace Database\Seeders;

use App\Models\ArchiveCategory;
use App\Models\ArchiveType;
use Illuminate\Database\Seeder;

class ArchiveCategorySeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            'SUR' => ['name' => 'Surat Menyurat', 'types' => ['Surat Masuk', 'Surat Keluar', 'Nota Dinas']],
            'KEU' => ['name' => 'Keuangan', 'types' => ['Laporan Keuangan', 'Kwitansi', 'SPJ']],
            'KEP' => ['name' => 'Kepegawaian', 'types' => ['SK Pegawai', 'Cuti', 'Absensi']],
            'ASET' => ['name' => 'Aset & Barang', 'types' => ['Berita Acara Aset', 'Inventaris']],
        ];

        foreach ($data as $code => $cat) {
            $category = ArchiveCategory::firstOrCreate(['code' => $code], ['name' => $cat['name']]);
            foreach ($cat['types'] as $typeName) {
                ArchiveType::firstOrCreate(['category_id' => $category->id, 'name' => $typeName]);
            }
        }
    }
}
